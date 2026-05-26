<?php
/* ============================================================
   FYLCAD — Módulo Topográfico Profesional
   proyecto.php  — versión limpia
============================================================ */
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php'); exit;
}

$usuarioPlan   = $_SESSION['usuario_plan']   ?? 'free';
$usuarioNombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuarioId     = $_SESSION['usuario_id'];

/* ── Cargar proyecto guardado desde DB ── */
$proyectoCargado = null;
if (isset($_GET['cargar']) && is_numeric($_GET['cargar'])) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, a.contenido AS csv_contenido, a.nombre AS csv_nombre
        FROM proyectos p
        LEFT JOIN archivos a ON a.proyecto_id = p.id
        WHERE p.id = ? AND p.usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$_GET['cargar'], $usuarioId]);
    $row = $stmt->fetch();
    if ($row && $row['csv_contenido']) {
        $proyectoCargado = [
            'id'      => $row['id'],
            'nombre'  => $row['nombre'],
            'archivo' => $row['csv_nombre'] ?? 'proyecto.csv',
            'csv'     => $row['csv_contenido'],
        ];
    }
}

/* ── Endpoint: procesar CSV ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    header('Content-Type: application/json');
    if ($_FILES['archivo']['error'] !== 0) {
        echo json_encode(['error' => 'Error al subir el archivo.']); exit;
    }
    $lineas  = file($_FILES['archivo']['tmp_name']);
    $puntos  = [];
    $limite  = $usuarioPlan === 'premium' ? PHP_INT_MAX : 2000;

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') continue;
        $p = preg_split('/[\s,;]+/', $linea);
        if (count($p) < 3) continue;

        // Detectar formato: N,X,Y,Z,DESC  o  X,Y,Z
        if (count($p) >= 4
            && is_numeric($p[0])
            && (float)$p[0] == (int)$p[0]
            && abs((float)$p[0]) < 1000000
            && is_numeric($p[1]) && is_numeric($p[2]) && is_numeric($p[3])) {
            // N,X,Y,Z,[DESC]
            $punto = [
                'n'    => (int)$p[0],
                'x'    => (float)$p[1],
                'y'    => (float)$p[2],
                'z'    => (float)$p[3],
                'desc' => isset($p[4]) ? trim(implode(' ', array_slice($p, 4))) : '',
            ];
        } else if (is_numeric($p[0]) && is_numeric($p[1]) && is_numeric($p[2])) {
            // X,Y,Z
            $punto = ['x' => (float)$p[0], 'y' => (float)$p[1], 'z' => (float)$p[2], 'desc' => ''];
        } else {
            continue;
        }

        if ($punto['x'] == 0 && $punto['y'] == 0) continue;
        $puntos[] = $punto;
        if (count($puntos) >= $limite) break;
    }
    echo json_encode($puntos); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FYLCAD — Módulo Topográfico</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Mono:wght@300;400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<link rel="stylesheet" href="css/proyecto.css?v=8">
<style>
/* ── Extras específicos de este módulo ── */
:root {
  --paper: #F5F1E8;
  --ink:   #000080;
  --red:   #AA0000;
  --topo:  #00e5c0;
}

/* Hover label sobre el canvas */
#puntoHoverLabel {
  position: absolute;
  background: rgba(248,244,232,0.97);
  border: 1px solid var(--ink);
  border-left: 3px solid var(--ink);
  border-radius: 0 4px 4px 0;
  padding: 6px 10px 7px;
  font: 11px/1.5 'DM Mono', monospace;
  color: #111;
  pointer-events: none;
  display: none;
  z-index: 20;
  box-shadow: 2px 2px 8px rgba(0,0,0,0.25);
  min-width: 160px;
}
#puntoHoverLabel b   { color: var(--ink); font-size: 12px; }
#puntoHoverLabel .hz { color: var(--red); font-weight: 600; }
#puntoHoverLabel .hd { color: #555; font-size: 10px; font-style: italic; }

/* Toast */
#fylcad-toast {
  position: fixed; bottom: 28px; left: 50%;
  transform: translateX(-50%) translateY(20px);
  background: #0c1120; border: 1px solid rgba(255,255,255,.12);
  border-radius: 10px; padding: 11px 22px;
  font: 500 13px/1 'DM Sans', sans-serif; color: #e8edf5;
  opacity: 0; pointer-events: none;
  transition: all .3s; z-index: 9999;
  box-shadow: 0 8px 32px rgba(0,0,0,.5);
}
#fylcad-toast.show               { opacity: 1; transform: translateX(-50%) translateY(0); }
#fylcad-toast.toast-success      { border-color: rgba(0,229,192,.4); color: #00e5c0; }
#fylcad-toast.toast-error        { border-color: rgba(239,68,68,.4);  color: #fca5a5; }

/* Modal guardar */
.modal-overlay {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,.72); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none; transition: opacity .25s;
}
.modal-overlay.open { opacity: 1; pointer-events: auto; }
.modal {
  background: #0c1120; border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px; padding: 32px; width: 400px; max-width: 92vw;
  transform: translateY(16px); transition: transform .25s;
}
.modal-overlay.open .modal { transform: translateY(0); }
.modal h3 { font: 800 18px/1 'Syne', sans-serif; color: #fff; margin-bottom: 20px; }
.modal label { display: block; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.modal input {
  width: 100%; background: #0a0f1c; border: 1px solid rgba(255,255,255,.08);
  border-radius: 8px; padding: 10px 14px; font: 14px 'DM Sans', sans-serif;
  color: #e8edf5; outline: none; margin-bottom: 16px;
  transition: border-color .2s;
}
.modal input:focus  { border-color: rgba(0,229,192,.4); }
.modal-btns         { display: flex; gap: 10px; margin-top: 4px; }
.mbtn { flex: 1; padding: 11px; border-radius: 8px; font: 600 13px 'DM Sans',sans-serif; cursor: pointer; border: none; transition: all .2s; }
.mbtn-cancel { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); color: #64748b; }
.mbtn-save   { background: #00e5c0; color: #020617; }
.mbtn-save:hover { background: #00ffda; box-shadow: 0 0 16px rgba(0,229,192,.3); }

/* Panel cálculos */
.csec   { font: 700 9px/1 'DM Sans',sans-serif; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; padding: 10px 0 6px; border-bottom: 1px solid rgba(255,255,255,.06); margin-bottom: 10px; }
.crow   { display: flex; gap: 8px; align-items: flex-end; margin-bottom: 10px; }
.cfield { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.cfield label { font: 500 9px/1 'DM Sans',sans-serif; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.cfield input, .cfield select {
  background: #0a0f1c; border: 1px solid rgba(255,255,255,.08);
  border-radius: 7px; padding: 8px 10px; font: 12px 'DM Mono',monospace;
  color: #e8edf5; outline: none; width: 100%; transition: border-color .2s;
}
.cfield input:focus { border-color: rgba(0,229,192,.4); }
.cbtn  { background: #00e5c0; color: #020617; border: none; border-radius: 7px; padding: 9px 12px; font: 700 12px 'DM Sans',sans-serif; cursor: pointer; white-space: nowrap; transition: all .2s; }
.cbtn:hover { background: #00ffda; box-shadow: 0 0 12px rgba(0,229,192,.25); }
.cbtn-full { width: 100%; margin-bottom: 0; }
.cbtn-sec { background: transparent; color: #00e5c0; border: 1px solid rgba(0,229,192,.3); border-radius: 7px; padding: 7px 10px; font: 600 11px 'DM Sans',sans-serif; cursor: pointer; transition: all .2s; }
.cbtn-sec:hover { background: rgba(0,229,192,.08); }
/* Resultados */
.cres {
  background: #0a0f1c; border: 1px solid rgba(0,229,192,.15);
  border-radius: 8px; padding: 10px 12px; margin-top: 8px;
}
.cres-row { display: flex; justify-content: space-between; font-size: 12px; padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,.04); }
.cres-row:last-child { border: none; }
.cres-lbl  { color: #64748b; }
.cres-val  { font: 500 12px 'DM Mono',monospace; color: #e8edf5; }
.cres-val.accent { color: #00e5c0; font-weight: 700; }
/* Grid 2 columnas para resultados */
.cres-grid2 {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 4px; margin: 6px 0;
}
.cres-cell {
  background: rgba(255,255,255,.02); border: 1px solid rgba(255,255,255,.04);
  border-radius: 5px; padding: 5px 7px; display: flex; flex-direction: column; gap: 2px;
}
.cres-cell.accent { border-color: rgba(0,229,192,.1); background: rgba(0,229,192,.03); }
.cres-cell .cres-lbl { font-size: 9px; color: #475569; text-transform: uppercase; letter-spacing: .04em; }
.cres-cell .cres-val { font: 600 11px 'DM Mono',monospace; color: #e8edf5; }
.cres-cell.accent .cres-val { color: #00e5c0; }
.cres-cell .cres-val.accent { color: #00e5c0; }
.cres-divider {
  font: 700 9px 'DM Sans',sans-serif; letter-spacing: 1.2px; text-transform: uppercase;
  color: #475569; padding: 8px 0 4px; border-bottom: 1px solid rgba(255,255,255,.05);
  margin-bottom: 6px;
}
/* Mini canvas unificado */
.mini-canvas {
  width: 100%; border-radius: 6px; margin-top: 10px; display: block;
  border: 1px solid rgba(0,229,192,.1);
}
/* Sección mini header */
.csec-mini {
  font: 700 9px 'DM Sans',sans-serif; letter-spacing: 1px; text-transform: uppercase;
  color: #475569; margin-bottom: 6px;
}
/* Label tab activo */
.calc-tab-label {
  font: 600 10px 'DM Sans',sans-serif; color: #64748b;
  padding: 4px 12px; border-bottom: 1px solid rgba(255,255,255,.04);
  letter-spacing: .03em; text-transform: uppercase;
}
/* Sección de cubicación multi-sec */
.cr-sec-row { display: flex; gap: 6px; align-items: flex-end; margin-bottom: 6px; }

/* ══════════════════════════════════════════════
   TOOLKIT PRO — Nuevo UX de cálculos
══════════════════════════════════════════════ */

/* Zona de análisis selector */
.zona-selector {
  padding: 10px 12px;
  background: rgba(0,229,192,.04);
  border-bottom: 1px solid rgba(0,229,192,.12);
}
.zona-sel-header { display:flex; align-items:center; gap:9px; }
.zona-sel-ico { font-size:16px; opacity:.6; }
.zona-sel-title { font: 700 11px 'DM Sans',sans-serif; color: #e8edf5; }
.zona-sel-sub   { font: 400 10px 'DM Sans',sans-serif; color: #64748b; margin-top:1px; }
.zona-toggle-btn {
  margin-left:auto; background:transparent; color:#00e5c0;
  border:1px solid rgba(0,229,192,.4); border-radius:6px;
  font: 600 10px 'DM Sans',sans-serif; padding:4px 10px;
  cursor:pointer; transition:all .2s;
}
.zona-toggle-btn:hover { background:rgba(0,229,192,.1); }
.zona-toggle-btn.active { background:rgba(0,229,192,.18); color:#00ffda; border-color:rgba(0,229,192,.6); }
.zona-sel-bar {
  display:flex; gap:6px; align-items:center; margin-top:8px;
  padding-top:8px; border-top:1px solid rgba(255,255,255,.05);
}
.zona-stat { display:flex; flex-direction:column; align-items:center; flex:1;
  font: 500 9px 'DM Sans',sans-serif; color:#64748b; gap:1px; }
.zona-stat span:first-child { font: 700 11px 'DM Mono',monospace; color:#00e5c0; }
.zona-clear-btn {
  background:transparent; border:1px solid rgba(239,68,68,.3); color:#f87171;
  border-radius:5px; font:600 9px 'DM Sans',sans-serif; padding:3px 8px;
  cursor:pointer; transition:all .2s;
}
.zona-clear-btn:hover { background:rgba(239,68,68,.1); }

/* Buscador de herramienta */
.tk-search-wrap {
  display:flex; align-items:center; gap:8px;
  padding:9px 12px; border-bottom:1px solid rgba(255,255,255,.06);
  background:rgba(0,0,0,.15);
}
.tk-search-ico { font-size:12px; opacity:.4; }
.tk-search {
  flex:1; background:transparent; border:none; outline:none;
  font: 400 12px 'DM Sans',sans-serif; color:#e8edf5;
  caret-color:#00e5c0;
}
.tk-search::placeholder { color:#475569; }
.tk-search-hint {
  font: 400 9px 'DM Mono',monospace; color:#334155;
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
  padding:2px 6px; border-radius:4px;
}

/* Categorías */
.tk-cats {
  display:flex; gap:0; padding:6px 10px; gap:4px; flex-wrap:wrap;
  border-bottom:1px solid rgba(255,255,255,.05);
}
.tk-cat {
  background:transparent; border:1px solid rgba(255,255,255,.07);
  color:#64748b; border-radius:20px; padding:3px 10px;
  font: 500 10px 'DM Sans',sans-serif; cursor:pointer; transition:all .2s;
}
.tk-cat:hover  { color:#e8edf5; border-color:rgba(255,255,255,.15); }
.tk-cat.active { background:rgba(0,229,192,.12); color:#00e5c0; border-color:rgba(0,229,192,.3); }

/* Grid de cards */
.tk-grid {
  padding:8px 10px; display:flex; flex-direction:column; gap:4px;
  max-height:none; overflow-y:visible;
}
.tk-card {
  display:flex; align-items:center; gap:10px;
  padding:9px 10px; border-radius:8px;
  border:1px solid rgba(255,255,255,.06);
  background:rgba(255,255,255,.02);
  cursor:pointer; transition:all .18s;
}
.tk-card:hover {
  background:rgba(0,229,192,.06);
  border-color:rgba(0,229,192,.2);
  transform:translateX(2px);
}
.tk-card-new { border-color:rgba(168,85,247,.2); background:rgba(168,85,247,.03); }
.tk-card-new:hover { background:rgba(168,85,247,.08); border-color:rgba(168,85,247,.4); }
.tk-card-new .tk-card-title::after {
  content:'NUEVO'; font:700 7px 'DM Sans',sans-serif;
  background:#a855f7; color:#fff; border-radius:3px;
  padding:1px 4px; margin-left:5px; vertical-align:middle;
  letter-spacing:.05em;
}
.tk-card-ico { font-size:18px; width:28px; text-align:center; flex-shrink:0; }
.tk-card-info { flex:1; min-width:0; }
.tk-card-title { font: 600 12px 'DM Sans',sans-serif; color:#e8edf5; }
.tk-card-sub   { font: 400 10px 'DM Sans',sans-serif; color:#64748b; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tk-card-arrow { color:#334155; font-size:12px; flex-shrink:0; transition:all .18s; }
.tk-card:hover .tk-card-arrow { color:#00e5c0; transform:translateX(2px); }
.tk-card.hidden { display:none; }

/* Pane header con volver */
.tk-pane-header {
  display:flex; align-items:center; gap:8px;
  padding:8px 12px; border-bottom:1px solid rgba(255,255,255,.06);
  background:rgba(0,0,0,.2);
  position:sticky; top:0; z-index:5;
}
.tk-back-btn {
  background:transparent; border:1px solid rgba(255,255,255,.1);
  color:#94a3b8; border-radius:6px; padding:4px 9px;
  font:600 10px 'DM Sans',sans-serif; cursor:pointer; transition:all .2s;
}
.tk-back-btn:hover { background:rgba(255,255,255,.06); color:#e8edf5; }
.tk-pane-title { font:700 12px 'DM Sans',sans-serif; color:#e8edf5; flex:1; }
.tk-pane-zone-badge {
  font:600 9px 'DM Mono',monospace; color:#00e5c0;
  background:rgba(0,229,192,.1); border:1px solid rgba(0,229,192,.25);
  border-radius:5px; padding:2px 7px;
}

/* Ocultar panes en modo grid */
#tkPanes { overflow:hidden; }
.calc-pane { display:none; padding:12px; overflow-y:auto; }
.calc-pane.active { display:block; }

/* Info box */
.tk-info-box {
  background:rgba(96,165,250,.06); border:1px solid rgba(96,165,250,.15);
  border-radius:7px; padding:9px 11px; margin-bottom:10px;
  font:400 11px/1.5 'DM Sans',sans-serif; color:#94a3b8;
}
.tk-info-box strong { color:#93c5fd; }

/* Zona activa hint en herramienta */
.tk-zone-use-hint {
  display:flex; align-items:center; gap:6px;
  background:rgba(0,229,192,.07); border:1px solid rgba(0,229,192,.2);
  border-radius:6px; padding:7px 10px; margin-bottom:8px;
  font:600 10px 'DM Sans',sans-serif; color:#00e5c0;
}
.tk-pick-hint {
  font:400 10px 'DM Sans',sans-serif; color:#475569;
  margin-bottom:8px; padding:6px 10px;
  background:rgba(255,255,255,.02); border-radius:5px;
  border-left:2px solid rgba(0,229,192,.3);
}

/* Tags de descripción */
.desc-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.desc-tag {
  border: 1px solid rgba(255,255,255,.12); color: #aaa;
  font: 10px/1 'DM Mono',monospace; padding: 3px 8px 3px 6px;
  border-radius: 4px; cursor: pointer; transition: all .2s;
  display: flex; align-items: center; gap: 4px;
}
.desc-tag .dot { width: 7px; height: 7px; border-radius: 2px; flex-shrink: 0; }
.desc-tag:hover    { border-color: rgba(0,229,192,.4); color: #e8edf5; }
.desc-tag.selected { border-color: rgba(0,229,192,.6); background: rgba(0,229,192,.1); color: #00e5c0; }

/* ══════════════════════════════════════════════
   MÉTRICAS — Dashboard visual del terreno
══════════════════════════════════════════════ */
.terrain-badge-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 14px; background: rgba(0,0,0,.25);
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.terrain-badge-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: .06em; }
.terrain-badge {
  font: 700 11px 'DM Mono',monospace; padding: 3px 10px;
  border-radius: 20px; letter-spacing: .05em;
  background: rgba(0,229,192,.15); color: #00e5c0;
  border: 1px solid rgba(0,229,192,.3);
}
.terrain-badge.tb-plano    { background: rgba(34,197,94,.15);  color: #22c55e; border-color: rgba(34,197,94,.3); }
.terrain-badge.tb-ondulado { background: rgba(251,191,36,.15); color: #fbbf24; border-color: rgba(251,191,36,.3); }
.terrain-badge.tb-quebrado { background: rgba(249,115,22,.15); color: #f97316; border-color: rgba(249,115,22,.3); }
.terrain-badge.tb-escarpado{ background: rgba(239,68,68,.15);  color: #ef4444; border-color: rgba(239,68,68,.3); }

.kpi-grid {
  display: grid; grid-template-columns: 1fr 1fr 1fr 1fr;
  gap: 0; border-bottom: 1px solid rgba(255,255,255,.06);
}
.kpi-card {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 14px 6px 12px; position: relative; gap: 2px;
  border-right: 1px solid rgba(255,255,255,.06);
}
.kpi-card:last-child { border-right: none; }
.kpi-icon { font-size: 14px; opacity: .5; margin-bottom: 2px; }
.kpi-val  { font: 700 18px 'DM Mono',monospace; line-height: 1; }
.kpi-lbl  { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .05em; text-align: center; }
.kpi-blue  { } .kpi-blue  .kpi-val { color: #60a5fa; }
.kpi-teal  { } .kpi-teal  .kpi-val { color: #00e5c0; }
.kpi-amber { } .kpi-amber .kpi-val { color: #fbbf24; }
.kpi-red   { } .kpi-red   .kpi-val { color: #f87171; }

.elev-bar-wrap { padding: 12px 14px 6px; }
.elev-bar-label-top,
.elev-bar-label-bot { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 3px; }
.elev-bar-label-top span:first-child,
.elev-bar-label-bot span:first-child { color: #64748b; }
.elev-bar-label-top span:last-child,
.elev-bar-label-bot span:last-child  { font: 600 10px 'DM Mono',monospace; color: #e8edf5; }
.elev-bar-label-bot { margin-top: 3px; margin-bottom: 0; }
.elev-bar-track {
  height: 10px; border-radius: 5px; position: relative;
  background: linear-gradient(90deg, #4ade80 0%, #fbbf24 40%, #f97316 70%, #ef4444 100%);
}
.elev-bar-fill {
  position: absolute; left: 0; top: 0; bottom: 0; border-radius: 5px;
  background: rgba(0,0,0,.5); transition: width .5s ease;
}
.elev-bar-mid {
  position: absolute; top: -3px; bottom: -3px; width: 3px;
  background: #fff; border-radius: 2px; transform: translateX(-50%);
  box-shadow: 0 0 6px rgba(255,255,255,.5);
  transition: left .5s ease;
}

.metrics-secondary {
  display: flex; align-items: stretch;
  border-top: 1px solid rgba(255,255,255,.06);
  padding: 0;
}
.msec-item {
  flex: 1; display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 2px; padding: 10px 4px;
}
.msec-sep { width: 1px; background: rgba(255,255,255,.06); }
.msec-ico { font-size: 12px; opacity: .45; }
.msec-val { font: 600 11px 'DM Mono',monospace; color: #00e5c0; }
.msec-lbl { font-size: 9px; color: #64748b; text-align: center; }

/* ══════════════════════════════════════════════
   CÁLCULOS — Tab de pendiente / clasificación
══════════════════════════════════════════════ */
.pend-badge-wrap {
  text-align: center; padding: 12px 0 10px;
}
.pend-badge {
  display: inline-block; font: 700 22px 'Syne',sans-serif;
  padding: 4px 18px; border-radius: 6px; letter-spacing: .02em;
  background: rgba(0,229,192,.15); color: #00e5c0;
  border: 1px solid rgba(0,229,192,.3);
}
.pend-badge-sub { font-size: 11px; color: #64748b; margin-top: 5px; }

.pend-tabla-title { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin: 10px 0 4px; }
.pend-tabla { display: flex; flex-direction: column; gap: 2px; }
.pt-row {
  display: flex; gap: 6px; align-items: center; padding: 4px 8px;
  border-radius: 4px; font-size: 11px; transition: background .15s;
}
.pt-row:hover { background: rgba(255,255,255,.04); }
.pt-row.active-row { background: rgba(0,229,192,.12); border: 1px solid rgba(0,229,192,.25); }
.pt-rng  { width: 62px; font: 600 10px 'DM Mono',monospace; color: #94a3b8; flex-shrink: 0; }
.pt-cls  { flex: 1; color: #e8edf5; font-weight: 500; }
.pt-uso  { font-size: 10px; color: #64748b; }

/* ══════════════════════════════════════════════
   COTIZACIÓN — Cartucho + resumen visual
══════════════════════════════════════════════ */
.cot-cartucho {
  background: rgba(0,0,0,.2); border-bottom: 1px solid rgba(255,255,255,.06);
  padding: 12px 14px 10px;
}
.cot-cartucho-row { display: flex; gap: 8px; margin-bottom: 10px; }
.cot-cartucho-field { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.cot-fc-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .06em; }
.cot-fc-select {
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
  color: #e8edf5; border-radius: 5px; padding: 5px 8px; font-size: 11px;
  font-family: 'DM Sans',sans-serif; cursor: pointer; width: 100%;
}
.cot-fc-select:focus { outline: none; border-color: rgba(0,229,192,.4); }
.cot-cant-grid { display: flex; gap: 0; }
.cot-cant-item {
  flex: 1; text-align: center; padding: 6px 4px;
  border: 1px solid rgba(255,255,255,.06); border-radius: 4px; margin: 0 2px;
}
.cot-cant-lbl  { display: block; font-size: 9px; color: #64748b; }
.cot-cant-val  { display: block; font: 700 13px 'DM Mono',monospace; color: #00e5c0; }
.cot-cant-unit { display: block; font-size: 9px; color: #475569; }

.cot-capitulo { border-bottom: 1px solid rgba(255,255,255,.06); }
.cot-cap-header {
  display: flex; align-items: center; gap: 8px; padding: 9px 14px;
  cursor: pointer; user-select: none; transition: background .15s;
}
.cot-cap-header:hover { background: rgba(255,255,255,.03); }
.cot-cap-num {
  font: 700 10px 'DM Mono',monospace; color: #00e5c0;
  background: rgba(0,229,192,.1); border: 1px solid rgba(0,229,192,.2);
  padding: 1px 6px; border-radius: 3px; flex-shrink: 0;
}
.cot-cap-title { font-size: 12px; font-weight: 600; color: #e8edf5; flex: 1; }
.cot-cap-sub   { font: 600 11px 'DM Mono',monospace; color: #00e5c0; opacity: .7; }
.cot-cap-arrow { font-size: 11px; color: #64748b; transition: transform .2s; }
.cot-cap-header.collapsed .cot-cap-arrow { transform: rotate(-90deg); }
.cot-cap-body  { padding: 0 14px 6px; }
.cot-cap-body.hidden { display: none; }

.apu-row {
  display: grid; grid-template-columns: 1fr auto auto;
  align-items: center; gap: 8px; padding: 5px 0;
  border-bottom: 1px solid rgba(255,255,255,.04);
}
.apu-row:last-child { border: none; }
.apu-desc { display: flex; flex-direction: column; min-width: 0; }
.apu-item-name { font-size: 11px; color: #cbd5e1; font-weight: 500; }
.apu-item-unit { font-size: 9px; color: #475569; font-family: 'DM Mono',monospace; margin-top: 1px; }
.apu-tarifa { display: flex; align-items: center; gap: 4px; }
.apu-input {
  width: 68px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  color: #e8edf5; border-radius: 4px; padding: 3px 6px; font: 11px 'DM Mono',monospace;
  text-align: right;
}
.apu-input:focus { outline: none; border-color: rgba(0,229,192,.5); }
.apu-tarifa-label { font-size: 9px; color: #475569; white-space: nowrap; }
.apu-subtotal { font: 600 11px 'DM Mono',monospace; color: #e8edf5; min-width: 70px; text-align: right; }

.cot-total-block { padding: 0 14px 14px; }
.cot-sum-rows { margin: 8px 0 10px; display: flex; flex-direction: column; gap: 4px; }
.cot-sum-row  { display: flex; align-items: center; gap: 6px; font-size: 11px; }
.cot-sum-dot  { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }
.cot-sum-lbl  { flex: 1; color: #64748b; }
.cot-sum-val  { font: 600 11px 'DM Mono',monospace; color: #e8edf5; }

.cot-grand-total {
  background: linear-gradient(135deg, rgba(0,229,192,.08) 0%, rgba(0,0,0,0) 100%);
  border: 1px solid rgba(0,229,192,.2); border-radius: 8px;
  padding: 14px 16px; margin: 10px 0 8px; text-align: center;
}
.cot-grand-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 4px; }
.cot-grand-val   { font: 700 26px 'Syne',sans-serif; color: #00e5c0; letter-spacing: -.01em; }
.cot-grand-alt   { font-size: 11px; color: #64748b; margin-top: 3px; }

.cot-efic-row {
  display: flex; border: 1px solid rgba(255,255,255,.07); border-radius: 6px;
  overflow: hidden; margin-top: 6px;
}
.cot-efic-item { flex: 1; text-align: center; padding: 8px 4px; }
.cot-efic-sep  { width: 1px; background: rgba(255,255,255,.07); }
.cot-efic-lbl  { display: block; font-size: 9px; color: #64748b; margin-bottom: 2px; }
.cot-efic-val  { display: block; font: 600 11px 'DM Mono',monospace; color: #e8edf5; }

.cot-notas { padding: 10px 14px 14px; }
.cot-notas-title { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
.cot-notas-list  { margin: 0; padding-left: 14px; }
.cot-notas-list li { font-size: 10px; color: #475569; line-height: 1.6; }

</style>
</head>
<body>

<!-- HEADER -->
<header class="header" id="header">
  <a href="index.php" class="logo">FYL<span>CAD</span></a>
  <div class="header-center">
    <span class="header-tag">// MÓDULO TOPOGRÁFICO PROFESIONAL</span>
  </div>
  <nav class="header-nav">
    <span class="header-user">👤 <?= htmlspecialchars($usuarioNombre) ?></span>
    <a href="dashboard.php" class="btn-nav">⊞ Dashboard</a>
    <a href="index.php"     class="btn-nav">← Inicio</a>
  </nav>
</header>

<!-- WORKSPACE -->
<main class="workspace">

  <!-- ════ SIDEBAR ════ -->
  <aside class="sidebar">

    <!-- Cargar archivo -->
    <div class="panel" id="panel-upload">
      <div class="panel-header">
        <span class="panel-icon">📁</span>
        <h2>Cargar Coordenadas</h2>
      </div>
      <div class="panel-body">
        <form id="formCSV" enctype="multipart/form-data">
          <div class="drop-zone" id="dropZone">
            <div class="drop-icon">⬆</div>
            <p>Arrastra tu archivo CSV</p>
            <span>o clic para seleccionar</span>
          </div>
          <input type="file" name="archivo" id="fileInput" accept=".csv,.txt" required style="display:none;">
          <div class="file-info" id="fileInfo" style="display:none;">
            <span class="file-name" id="fileName"></span>
            <span class="file-size" id="fileSize"></span>
          </div>
          <div class="format-hint">
            <strong>Formatos aceptados:</strong><br>
            <code>N, X, Y, Z, DESCRIPCION</code><br>
            <code>X, Y, Z</code>
          </div>
          <button type="submit" class="btn-primary" id="btnProcesar" disabled>
            <span class="btn-icon">▶</span> Procesar y Visualizar
          </button>
        </form>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         MÉTRICAS — Dashboard visual del terreno
    ════════════════════════════════════════════ -->
    <div class="panel" id="panel-metrics" style="display:none;">
      <div class="panel-header">
        <span class="panel-icon">📐</span>
        <h2>Resumen del Levantamiento</h2>
      </div>
      <div class="panel-body" style="padding:0;">

        <!-- Barra de clasificación del terreno -->
        <div class="terrain-badge-bar" id="terrainBadgeBar">
          <span class="terrain-badge-label">Clasificación topográfica</span>
          <span class="terrain-badge" id="terrainBadge">—</span>
        </div>

        <!-- KPI grid: 4 cards grandes -->
        <div class="kpi-grid">
          <div class="kpi-card kpi-blue">
            <div class="kpi-icon">📍</div>
            <div class="kpi-val" id="m-puntos">—</div>
            <div class="kpi-lbl">puntos</div>
          </div>
          <div class="kpi-card kpi-teal">
            <div class="kpi-icon">△</div>
            <div class="kpi-val" id="m-tris">—</div>
            <div class="kpi-lbl">triángulos TIN</div>
          </div>
          <div class="kpi-card kpi-amber">
            <div class="kpi-icon">⬡</div>
            <div class="kpi-val" id="m-area-ha">—</div>
            <div class="kpi-lbl">hectáreas</div>
          </div>
          <div class="kpi-card kpi-red">
            <div class="kpi-icon">↕</div>
            <div class="kpi-val" id="m-desnivel">—</div>
            <div class="kpi-lbl">m desnivel</div>
          </div>
        </div>

        <!-- Barra de elevación visual -->
        <div class="elev-bar-wrap">
          <div class="elev-bar-label-top">
            <span>Cota mín</span><span id="m-zmin">—</span>
          </div>
          <div class="elev-bar-track">
            <div class="elev-bar-fill" id="elevBarFill"></div>
            <div class="elev-bar-mid" id="elevBarMid"></div>
          </div>
          <div class="elev-bar-label-bot">
            <span>Cota máx</span><span id="m-zmax">—</span>
          </div>
        </div>

        <!-- Fila de métricas secundarias -->
        <div class="metrics-secondary">
          <div class="msec-item">
            <span class="msec-ico">📏</span>
            <span class="msec-val" id="m-area">—</span>
            <span class="msec-lbl">m² área</span>
          </div>
          <div class="msec-sep"></div>
          <div class="msec-item">
            <span class="msec-ico">〇</span>
            <span class="msec-val" id="m-perimetro">—</span>
            <span class="msec-lbl">m perímetro</span>
          </div>
          <div class="msec-sep"></div>
          <div class="msec-item">
            <span class="msec-ico">⛰</span>
            <span class="msec-val" id="m-volumen">—</span>
            <span class="msec-lbl">m³ volumen</span>
          </div>
          <div class="msec-sep"></div>
          <div class="msec-item">
            <span class="msec-ico">≈</span>
            <span class="msec-val" id="m-eq">—</span>
            <span class="msec-lbl">m equidist.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         CÁLCULOS TOPOGRÁFICOS — v4 Toolkit Pro
    ════════════════════════════════════════════ -->
    <div class="panel" id="panel-calculos" style="display:none;">
      <div class="panel-header">
        <span class="panel-icon">⚙️</span>
        <h2>Cálculos Topográficos</h2>
      </div>
      <div class="panel-body" style="padding:0;">

        <!-- ═══ ZONA DE ANÁLISIS (selector de área) ═══ -->
        <div class="zona-selector" id="zonaSelector">
          <div class="zona-sel-header">
            <span class="zona-sel-ico">⬡</span>
            <div>
              <div class="zona-sel-title">Zona de análisis</div>
              <div class="zona-sel-sub" id="zonaSelectorSub">Todo el terreno</div>
            </div>
            <button class="zona-toggle-btn" id="btnToggleZona" title="Definir zona de análisis en el plano">Definir</button>
          </div>
          <div class="zona-sel-bar" id="zonaSelectorBar" style="display:none;">
            <div class="zona-stat"><span id="zsArea">—</span><span>m² área</span></div>
            <div class="zona-stat"><span id="zsN">—</span><span>puntos</span></div>
            <div class="zona-stat"><span id="zsVol">—</span><span>m³ vol</span></div>
            <button class="zona-clear-btn" id="btnZonaClear">✕ Limpiar</button>
          </div>
        </div>

        <!-- ═══ BUSCADOR DE HERRAMIENTA ═══ -->
        <div class="tk-search-wrap">
          <span class="tk-search-ico">🔍</span>
          <input type="text" id="tkSearch" class="tk-search" placeholder="Buscar herramienta...">
          <kbd class="tk-search-hint">ESC</kbd>
        </div>

        <!-- ═══ TABS CATEGORIZADOS ═══ -->
        <div class="tk-cats" id="tkCats">
          <button class="tk-cat active" data-cat="all">Todas</button>
          <button class="tk-cat" data-cat="medicion">Medición</button>
          <button class="tk-cat" data-cat="volumen">Volumen</button>
          <button class="tk-cat" data-cat="terreno">Terreno</button>
          <button class="tk-cat" data-cat="datos">Datos</button>
        </div>

        <!-- ═══ GRID DE HERRAMIENTAS ═══ -->
        <div class="tk-grid" id="tkGrid">

          <!-- DISTANCIA Y AZIMUT -->
          <div class="tk-card" data-tool="distaz" data-cat="medicion" data-keywords="distancia azimut ángulo cenital rumbo punto">
            <div class="tk-card-ico">📏</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Distancia y Azimut</div>
              <div class="tk-card-sub">Entre dos puntos · Rumbo · Ángulo cenital</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- PERFIL LONGITUDINAL -->
          <div class="tk-card" data-tool="perfil" data-cat="medicion" data-keywords="perfil longitudinal rasante corte relleno tramo">
            <div class="tk-card-ico">📈</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Perfil Longitudinal</div>
              <div class="tk-card-sub">Terreno + rasante · Corte/Relleno por tramo</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- SECCIÓN TRANSVERSAL -->
          <div class="tk-card" data-tool="seccion" data-cat="medicion" data-keywords="sección transversal perfil corte perpendicular banca">
            <div class="tk-card-ico">⊥</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Sección Transversal</div>
              <div class="tk-card-sub">Corte perpendicular · Ancho banca · Taludes</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- ÁREA Y VOLUMEN -->
          <div class="tk-card" data-tool="area" data-cat="volumen" data-keywords="área volumen polígono gauss prismoide simpson hectáreas">
            <div class="tk-card-ico">⬡</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Área y Volumen</div>
              <div class="tk-card-sub">Gauss-Shoelace · Prismoide · Área 3D real</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- CUBICACIÓN C/R -->
          <div class="tk-card" data-tool="corte" data-cat="volumen" data-keywords="cubicación corte relleno multi-sección excavación terraplén">
            <div class="tk-card-ico">⛏</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Cubicación C/R</div>
              <div class="tk-card-sub">Multi-sección · Áreas medias · Prismoide</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- NIVELACIÓN DE PLATAFORMA -->
          <div class="tk-card tk-card-new" data-tool="plataforma" data-cat="volumen" data-keywords="plataforma nivelación balance rasante óptima cota proyecto">
            <div class="tk-card-ico">🏗️</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Nivelación Plataforma</div>
              <div class="tk-card-sub">Cota óptima balance corte=relleno · NOVO</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- PENDIENTE E HISTOGRAMA -->
          <div class="tk-card" data-tool="pend" data-cat="terreno" data-keywords="pendiente histograma IGAC INVIAS clasificación talud relación HV">
            <div class="tk-card-ico">📐</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Pendiente y Clasificación</div>
              <div class="tk-card-sub">IGAC/INVIAS · Histograma TIN · Talud</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- CURVA DE MASA -->
          <div class="tk-card tk-card-new" data-tool="masa" data-cat="terreno" data-keywords="curva masa bruckner volumen acumulado compensación carreteras">
            <div class="tk-card-ico">📉</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Curva de Masa</div>
              <div class="tk-card-sub">Bruckner · Compensación · Vol. acumulado</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- ANÁLISIS DE DRENAJE -->
          <div class="tk-card tk-card-new" data-tool="drenaje" data-cat="terreno" data-keywords="drenaje cuenca flujo dirección pendiente cuneta canal">
            <div class="tk-card-ico">💧</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Análisis de Drenaje</div>
              <div class="tk-card-sub">Área de aporte · Longitud cuneta · Caudal Q</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- COORDENADAS -->
          <div class="tk-card" data-tool="coordi" data-cat="datos" data-keywords="coordenadas MAGNA SIRGAS ficha punto estadísticas nube">
            <div class="tk-card-ico">🎯</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Consulta Coordenadas</div>
              <div class="tk-card-sub">Ficha de punto · MAGNA-SIRGAS · Estadísticas</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- CAPAS / FILTRO -->
          <div class="tk-card" data-tool="filtro" data-cat="datos" data-keywords="capas filtro código descripción GPS talud vegetación">
            <div class="tk-card-ico">🏷</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Capas y Códigos</div>
              <div class="tk-card-sub">Filtrar plano · Aislar elementos · Códigos</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

          <!-- BUSCAR PUNTO -->
          <div class="tk-card" data-tool="buscar" data-cat="datos" data-keywords="buscar punto centrar localizar número">
            <div class="tk-card-ico">🔍</div>
            <div class="tk-card-info">
              <div class="tk-card-title">Buscar Punto</div>
              <div class="tk-card-sub">Localiza y centra el plano en un punto</div>
            </div>
            <div class="tk-card-arrow">→</div>
          </div>

        </div><!-- /.tk-grid -->

        <!-- ═══ PANES (sin cambios en su contenido interno, solo wrapper) ═══ -->
        <div id="tkPanes">

          <!-- BACK BUTTON (siempre visible cuando pane abierto) -->
          <div class="tk-pane-header" id="tkPaneHeader" style="display:none;">
            <button class="tk-back-btn" id="tkBackBtn">← Volver</button>
            <span class="tk-pane-title" id="tkPaneTitle">—</span>
            <span class="tk-pane-zone-badge" id="tkZoneBadge" style="display:none;">⬡ Zona activa</span>
          </div>

        <!-- ═══ FILTRO POR CAPAS ═══ -->
        <div class="calc-pane" id="tab-filtro">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">🏷</span>
            <div>
              <div class="calc-tool-title">Capas y códigos</div>
              <div class="calc-tool-sub">Filtra el plano por descripción de punto</div>
            </div>
          </div>
          <div id="descTagsContainer" class="desc-tags"></div>
          <div style="display:flex;gap:6px;margin-top:10px;">
            <button class="cbtn-sec" id="btnSelectAllDesc">✓ Todos</button>
            <button class="cbtn-sec" id="btnClearDesc">✕ Limpiar</button>
            <button class="cbtn" id="btnFiltrarDesc" style="flex:1;">Aplicar →</button>
          </div>
          <div class="calc-hint">💡 Útil para aislar taludes, vía, GPS o vegetación</div>
        </div>

        <!-- ═══ DISTANCIA + AZIMUT ═══ -->
        <div class="calc-pane" id="tab-distaz">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">📏</span>
            <div>
              <div class="calc-tool-title">Distancia y Azimut</div>
              <div class="calc-tool-sub">Geometría espacial entre dos puntos del levantamiento</div>
            </div>
          </div>
          <div class="crow">
            <div class="cfield"><label>Punto A (N°)</label><input type="number" id="calcDistA" placeholder="1" min="1"></div>
            <div class="cfield"><label>Punto B (N°)</label><input type="number" id="calcDistB" placeholder="10" min="1"></div>
          </div>
          <div class="tk-pick-hint">💡 O haz <strong>clic en el plano</strong> para seleccionar A y B directamente</div>
          <button class="cbtn cbtn-full" id="btnCalcDist">Calcular →</button>
          <div class="cres" id="resDistancia" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Distancia horizontal</div>
              <div class="cres-big-val" id="rDistH">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell"><span class="cres-lbl">Dist. 3D inclinada</span><span class="cres-val" id="rDist3D">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">ΔZ (desnivel)</span><span class="cres-val" id="rDistDZ">—</span></div>
              <div class="cres-cell accent"><span class="cres-lbl">Pendiente</span><span class="cres-val accent" id="rDistPend">—</span></div>
              <div class="cres-cell accent"><span class="cres-lbl">Ángulo cenital (V)</span><span class="cres-val accent" id="rDistCenital">—</span></div>
            </div>
            <div class="cres-divider">Orientación</div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Azimut (A→B)</span><span class="cres-val accent" id="rDistAz">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Rumbo</span><span class="cres-val" id="rDistRumbo">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Contra-azimut (B→A)</span><span class="cres-val" id="rDistContraAz">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Punto medio (X,Y,Z)</span><span class="cres-val" id="rDistMedio">—</span></div>
            </div>
            <canvas id="miniCanvasAzimut" class="mini-canvas" height="120"></canvas>
          </div>
          <div class="calc-hint">💡 Azimut desde Norte geográfico en sentido horario · Ángulo cenital desde vertical</div>
        </div>

        <!-- ═══ PERFIL LONGITUDINAL ═══ -->
        <div class="calc-pane" id="tab-perfil">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">📈</span>
            <div>
              <div class="calc-tool-title">Perfil Longitudinal</div>
              <div class="calc-tool-sub">Perfil del terreno con rasante de proyecto superpuesta</div>
            </div>
          </div>
          <div class="crow">
            <div class="cfield"><label>Desde N°</label><input type="number" id="calcPerfilDesde" placeholder="1" min="1"></div>
            <div class="cfield"><label>Hasta N°</label><input type="number" id="calcPerfilHasta" placeholder="50" min="1"></div>
          </div>
          <div class="csec-mini" style="margin-bottom:6px;">Rasante de proyecto (opcional)</div>
          <div class="crow">
            <div class="cfield"><label>Cota inicio (m)</label><input type="number" id="perfilRasanteZ1" placeholder="396.500" step="0.001"></div>
            <div class="cfield"><label>Cota fin (m)</label><input type="number" id="perfilRasanteZ2" placeholder="398.200" step="0.001"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCalcPerfil">Generar perfil →</button>
          <div class="cres" id="resPerfil" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Desnivel total</div>
              <div class="cres-big-val" id="rPerfilDesnivel">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell"><span class="cres-lbl">Puntos en rango</span><span class="cres-val" id="rPerfilN">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Longitud del eje</span><span class="cres-val" id="rPerfilLong">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Cota mínima</span><span class="cres-val" id="rPerfilZmin">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Cota máxima</span><span class="cres-val" id="rPerfilZmax">—</span></div>
              <div class="cres-cell accent"><span class="cres-lbl">Pendiente media</span><span class="cres-val accent" id="rPerfilPend">—</span></div>
              <div class="cres-cell accent"><span class="cres-lbl">Pendiente máx. tramo</span><span class="cres-val accent" id="rPerfilPendMax">—</span></div>
            </div>
            <div id="resPendRasante" style="display:none;">
              <div class="cres-divider">Rasante vs Terreno</div>
              <div class="cres-grid2">
                <div class="cres-cell"><span class="cres-lbl">Pendiente rasante</span><span class="cres-val accent" id="rRasantePend">—</span></div>
                <div class="cres-cell"><span class="cres-lbl">Corte/Relleno máx.</span><span class="cres-val" id="rRasanteMaxCR">—</span></div>
                <div class="cres-cell"><span class="cres-lbl">Vol. corte estimado</span><span class="cres-val" id="rRasanteVolC">—</span></div>
                <div class="cres-cell"><span class="cres-lbl">Vol. relleno estimado</span><span class="cres-val" id="rRasanteVolR">—</span></div>
              </div>
            </div>
            <canvas id="miniCanvasPerfil" class="mini-canvas" height="120"></canvas>
          </div>
          <div class="calc-hint">💡 Define rasante para obtener volúmenes de corte y relleno por tramo</div>
        </div>

        <!-- ═══ SECCIÓN TRANSVERSAL (NUEVO) ═══ -->
        <div class="calc-pane" id="tab-seccion">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">⊥</span>
            <div>
              <div class="calc-tool-title">Sección Transversal</div>
              <div class="calc-tool-sub">Corte perpendicular al eje · Ancho banca · Taludes</div>
            </div>
          </div>
          <div class="csec-mini">Eje de la sección</div>
          <div class="crow">
            <div class="cfield"><label>Punto centro (N°)</label><input type="number" id="secCentro" placeholder="25" min="1"></div>
            <div class="cfield"><label>Azimut eje (°)</label><input type="number" id="secAzimut" placeholder="45.0" step="0.1" min="0" max="360"></div>
          </div>
          <div class="crow">
            <div class="cfield"><label>Ancho total (m)</label><input type="number" id="secAncho" placeholder="20" value="20" step="0.5" min="1"></div>
            <div class="cfield"><label>Z proyecto (m)</label><input type="number" id="secZproy" placeholder="auto" step="0.001"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCalcSeccion">Generar sección →</button>
          <div class="cres" id="resSeccion" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label" id="rSecTipoLabel">Tipo</div>
              <div class="cres-big-val" id="rSecVolTotal">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Área corte</span><span class="cres-val accent" id="rSecAreaC">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Área relleno</span><span class="cres-val" id="rSecAreaR">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Desnivel máx. izq</span><span class="cres-val" id="rSecDesnIzq">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Desnivel máx. der</span><span class="cres-val" id="rSecDesnDer">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Talud izq. rec.</span><span class="cres-val accent" id="rSecTaludIzq">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Talud der. rec.</span><span class="cres-val accent" id="rSecTaludDer">—</span></div>
            </div>
            <canvas id="miniCanvasSeccion" class="mini-canvas" height="110"></canvas>
          </div>
          <div class="calc-hint">💡 El azimut del eje se puede leer del cálculo de Distancia y Azimut</div>
        </div>

        <!-- ═══ ÁREA Y VOLUMEN ═══ -->
        <div class="calc-pane" id="tab-area">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">⬡</span>
            <div>
              <div class="calc-tool-title">Área y Volumen</div>
              <div class="calc-tool-sub">Gauss-Shoelace · Prismoide · Simpson · Área 3D real</div>
            </div>
          </div>
          <div class="tk-zone-use-hint" id="areaZoneHint" style="display:none;">
            <span>⬡</span> Usando zona de análisis activa
          </div>
          <div class="cfield" style="margin-bottom:8px;">
            <label>Puntos del polígono (separados por coma)</label>
            <input type="text" id="calcAreaPuntos" placeholder="Ej: 1, 5, 12, 18, 25, 1">
          </div>
          <div style="text-align:center;color:#475569;font-size:10px;margin:2px 0 8px;">— o por rango consecutivo —</div>
          <div class="crow">
            <div class="cfield"><label>Desde N°</label><input type="number" id="calcAreaDesde" placeholder="1"></div>
            <div class="cfield"><label>Hasta N°</label><input type="number" id="calcAreaHasta" placeholder="50"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCalcArea">Calcular →</button>
          <div class="cres" id="resArea" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Área horizontal (Gauss)</div>
              <div class="cres-big-val" id="rAreaVal">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Área en hectáreas</span><span class="cres-val accent" id="rAreaHa">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Área 3D real (TIN)</span><span class="cres-val" id="rArea3D">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Perímetro</span><span class="cres-val" id="rAreaPerim">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Puntos usados</span><span class="cres-val" id="rAreaN">—</span></div>
            </div>
            <div class="cres-divider">Volumen</div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Vol. Prismoide</span><span class="cres-val accent" id="rAreaVol">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Vol. Promedio secciones</span><span class="cres-val" id="rAreaVolMedia">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Cota media Z</span><span class="cres-val" id="rAreaZmed">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Desnivel interno</span><span class="cres-val" id="rAreaDesn">—</span></div>
            </div>
            <canvas id="miniCanvasArea" class="mini-canvas" height="90"></canvas>
          </div>
          <div class="calc-hint">💡 Área 2D = proyección horizontal · Área 3D = superficie real del terreno</div>
        </div>

        <!-- ═══ CUBICACIÓN MULTI-SECCIÓN ═══ -->
        <div class="calc-pane" id="tab-corte">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">⛏</span>
            <div>
              <div class="calc-tool-title">Cubicación C/R Multi-sección</div>
              <div class="calc-tool-sub">Volumen por método de las áreas medias · hasta 5 secciones</div>
            </div>
          </div>
          <div class="csec-mini">Parámetros de banca</div>
          <div class="crow">
            <div class="cfield"><label>Ancho banca (m)</label><input type="number" id="crAncho" placeholder="6.0" value="6" step="0.1" min="0.1"></div>
            <div class="cfield"><label>Dist. entre secciones (m)</label><input type="number" id="crDistSec" placeholder="20" value="20" step="0.5" min="0.1"></div>
          </div>
          <div class="csec-mini" style="margin-top:4px;">Secciones (terreno N° → cota proyecto)</div>
          <div id="crSecciones">
            <div class="cr-sec-row">
              <div class="cfield" style="flex:0.8"><label>Punto N°</label><input class="cr-pto" type="number" placeholder="5"></div>
              <div class="cfield"><label>Z proyecto (m)</label><input class="cr-zp" type="number" placeholder="396.500" step="0.001"></div>
              <button class="cbtn-sec cr-del" style="padding:5px 8px;align-self:flex-end;display:none;">✕</button>
            </div>
            <div class="cr-sec-row">
              <div class="cfield" style="flex:0.8"><label>Punto N°</label><input class="cr-pto" type="number" placeholder="10"></div>
              <div class="cfield"><label>Z proyecto (m)</label><input class="cr-zp" type="number" placeholder="396.800" step="0.001"></div>
              <button class="cbtn-sec cr-del" style="padding:5px 8px;align-self:flex-end;">✕</button>
            </div>
          </div>
          <div style="display:flex;gap:6px;margin-top:8px;">
            <button class="cbtn-sec" id="crAddSec" style="flex:1;">+ Sección</button>
            <button class="cbtn" id="btnCalcCorteRelleno" style="flex:2;">Cubicar →</button>
          </div>
          <div class="cres" id="resCorteRelleno" style="display:none;">
            <div class="cres-highlight" id="crTipoBadge">
              <div class="cres-big-label" id="crTipoLabel">Tipo</div>
              <div class="cres-big-val" id="crVolumen">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Vol. Corte total</span><span class="cres-val accent" id="crVolCorte">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Vol. Relleno total</span><span class="cres-val" id="crVolRelleno">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Balance neto</span><span class="cres-val" id="crBalance">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Longitud total</span><span class="cres-val" id="crLongTotal">—</span></div>
            </div>
            <div class="cres-divider">Tabla de secciones</div>
            <div id="crTabla" style="font-size:10px;"></div>
            <canvas id="miniCanvasCorte" class="mini-canvas" height="100"></canvas>
          </div>
          <div class="calc-hint">💡 Cota roja = Z_proyecto − Z_terreno · (+) relleno · (−) corte</div>
        </div>

        <!-- ═══ NIVELACIÓN DE PLATAFORMA (NUEVO) ═══ -->
        <div class="calc-pane" id="tab-plataforma">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">🏗️</span>
            <div>
              <div class="calc-tool-title">Nivelación de Plataforma</div>
              <div class="calc-tool-sub">Cota rasante óptima que balancea corte y relleno</div>
            </div>
          </div>
          <div class="tk-info-box">
            <strong>¿Qué hace?</strong> Encuentra la cota Z óptima de proyecto para que el volumen de corte sea igual al de relleno (balance 0), minimizando el movimiento de tierra total.
          </div>
          <div class="csec-mini">Zona de análisis</div>
          <div class="crow">
            <div class="cfield"><label>Puntos polígono (coma) o rango Desde</label><input type="text" id="platPuntos" placeholder="Todos los puntos cargados"></div>
          </div>
          <div class="crow">
            <div class="cfield"><label>Desde N°</label><input type="number" id="platDesde" placeholder="1"></div>
            <div class="cfield"><label>Hasta N°</label><input type="number" id="platHasta" placeholder="todos"></div>
          </div>
          <div class="csec-mini" style="margin-top:4px;">Restricciones opcionales</div>
          <div class="crow">
            <div class="cfield"><label>Cota mínima (m)</label><input type="number" id="platZmin" placeholder="sin límite" step="0.001"></div>
            <div class="cfield"><label>Cota máxima (m)</label><input type="number" id="platZmax" placeholder="sin límite" step="0.001"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCalcPlataforma">Calcular cota óptima →</button>
          <div class="cres" id="resPlataforma" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Cota rasante óptima</div>
              <div class="cres-big-val accent" id="rPlatCota">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Vol. corte total</span><span class="cres-val accent" id="rPlatVolC">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Vol. relleno total</span><span class="cres-val" id="rPlatVolR">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Balance neto</span><span class="cres-val" id="rPlatBalance">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Puntos analizados</span><span class="cres-val" id="rPlatN">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Cota media terreno</span><span class="cres-val" id="rPlatZmed">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">% terreno en corte</span><span class="cres-val" id="rPlatPctC">—</span></div>
            </div>
            <div class="cres-divider">Iteraciones del método</div>
            <canvas id="miniCanvasPlataforma" class="mini-canvas" height="90"></canvas>
            <div class="calc-hint" style="margin-top:6px;">💡 Usa esta cota en Cubicación C/R y Cotización para mayor precisión</div>
          </div>
          <div class="calc-hint">💡 Método de bisección · Convergencia &lt;0.001 m³ · Norma INVIAS cap. movimiento tierras</div>
        </div>

        <!-- ═══ PENDIENTE + HISTOGRAMA ═══ -->
        <div class="calc-pane" id="tab-pend">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">📐</span>
            <div>
              <div class="calc-tool-title">Pendiente y Clasificación</div>
              <div class="calc-tool-sub">IGAC / INVIAS · Histograma del terreno completo</div>
            </div>
          </div>
          <div class="csec-mini">Entre dos puntos</div>
          <div class="crow">
            <div class="cfield"><label>Punto A (N°)</label><input type="number" id="pendPtoA" placeholder="1" min="1"></div>
            <div class="cfield"><label>Punto B (N°)</label><input type="number" id="pendPtoB" placeholder="10" min="1"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCalcPend">Calcular pendiente →</button>
          <div class="cres" id="resPend" style="display:none;">
            <div class="pend-badge-wrap">
              <div class="pend-badge" id="pendBadge">—</div>
              <div class="pend-badge-sub" id="pendBadgeSub">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Pendiente (%)</span><span class="cres-val accent" id="rPendPct">—</span></div>
              <div class="cres-cell accent"><span class="cres-lbl">Pendiente (°)</span><span class="cres-val accent" id="rPendGrad">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Distancia horizontal</span><span class="cres-val" id="rPendDistH">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">ΔZ (desnivel)</span><span class="cres-val" id="rPendDZ">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Talud recomendado</span><span class="cres-val" id="rPendTalud">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Relación H:V</span><span class="cres-val" id="rPendHV">—</span></div>
            </div>
            <div class="pend-tabla-title">Clasificación topográfica IGAC / INVIAS</div>
            <div class="pend-tabla" id="pendTabla">
              <div class="pt-row" data-min="0"   data-max="3">  <span class="pt-rng">0–3%</span>  <span class="pt-cls">Plano</span>              <span class="pt-uso">Cultivos, urbanismo</span></div>
              <div class="pt-row" data-min="3"   data-max="7">  <span class="pt-rng">3–7%</span>  <span class="pt-cls">Ligeramente ondulado</span><span class="pt-uso">Vías principales</span></div>
              <div class="pt-row" data-min="7"   data-max="12"> <span class="pt-rng">7–12%</span> <span class="pt-cls">Ondulado</span>           <span class="pt-uso">Vías secundarias</span></div>
              <div class="pt-row" data-min="12"  data-max="25"> <span class="pt-rng">12–25%</span><span class="pt-cls">Fuertem. ondulado</span> <span class="pt-uso">Terrazas, franjas</span></div>
              <div class="pt-row" data-min="25"  data-max="50"> <span class="pt-rng">25–50%</span><span class="pt-cls">Quebrado</span>           <span class="pt-uso">Pasto, cobertura</span></div>
              <div class="pt-row" data-min="50"  data-max="75"> <span class="pt-rng">50–75%</span><span class="pt-cls">Muy quebrado</span>       <span class="pt-uso">Bosque protector</span></div>
              <div class="pt-row" data-min="75"  data-max="999"><span class="pt-rng">&gt;75%</span><span class="pt-cls">Escarpado</span>         <span class="pt-uso">No apto / protección</span></div>
            </div>
          </div>
          <div style="margin-top:10px;">
            <button class="cbtn cbtn-full" id="btnHistoPend">📊 Histograma del terreno →</button>
          </div>
          <div class="cres" id="resHistoPend" style="display:none;margin-top:8px;">
            <div class="cres-divider">Distribución de pendientes (TIN)</div>
            <div class="cres-grid2" id="histoStats"></div>
            <canvas id="miniCanvasHisto" class="mini-canvas" height="100"></canvas>
          </div>
          <div class="calc-hint">💡 El histograma analiza la pendiente de cada triángulo del TIN</div>
        </div>

        <!-- ═══ CURVA DE MASA (NUEVO) ═══ -->
        <div class="calc-pane" id="tab-masa">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">📉</span>
            <div>
              <div class="calc-tool-title">Curva de Masa (Bruckner)</div>
              <div class="calc-tool-sub">Volumen acumulado · Compensación · Distancia media de arrastre</div>
            </div>
          </div>
          <div class="tk-info-box">
            La curva de masa acumula corte (+) y relleno (−) a lo largo del eje de la vía. Donde la curva corta el eje horizontal hay <strong>compensación</strong>. Usada en diseño de carreteras (INVIAS 2013).
          </div>
          <div class="csec-mini">Eje de análisis</div>
          <div class="crow">
            <div class="cfield"><label>Punto inicial (N°)</label><input type="number" id="masaDesde" placeholder="1" min="1"></div>
            <div class="cfield"><label>Punto final (N°)</label><input type="number" id="masaHasta" placeholder="50" min="1"></div>
          </div>
          <div class="crow">
            <div class="cfield"><label>Z rasante inicio (m)</label><input type="number" id="masaZ1" placeholder="ej: 396.500" step="0.001"></div>
            <div class="cfield"><label>Z rasante fin (m)</label><input type="number" id="masaZ2" placeholder="ej: 399.200" step="0.001"></div>
          </div>
          <div class="crow">
            <div class="cfield"><label>Esponjamiento (%)</label><input type="number" id="masaEsponj" placeholder="12" value="12" step="1" min="0" max="50"></div>
            <div class="cfield"><label>Ancho banca (m)</label><input type="number" id="masaBanca" placeholder="6" value="6" step="0.5" min="1"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCalcMasa">Generar curva de masa →</button>
          <div class="cres" id="resMasa" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Balance neto acumulado</div>
              <div class="cres-big-val" id="rMasaBalance">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Corte total</span><span class="cres-val accent" id="rMasaCorte">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Relleno total</span><span class="cres-val" id="rMasaRelleno">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Zonas compensadas</span><span class="cres-val" id="rMasaZonas">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">DMA estimada</span><span class="cres-val" id="rMasaDMA">—</span></div>
            </div>
            <canvas id="miniCanvasMasa" class="mini-canvas" height="110"></canvas>
            <div class="cres-divider">Tabla de estaciones</div>
            <div id="masaTabla" style="font-size:10px;max-height:160px;overflow-y:auto;"></div>
          </div>
          <div class="calc-hint">💡 DMA = Distancia Media de Arrastre. Determina costo de transporte de material</div>
        </div>

        <!-- ═══ ANÁLISIS DE DRENAJE (NUEVO) ═══ -->
        <div class="calc-pane" id="tab-drenaje">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">💧</span>
            <div>
              <div class="calc-tool-title">Análisis de Drenaje</div>
              <div class="calc-tool-sub">Área de aporte · Longitud de cunetas · Caudal Q</div>
            </div>
          </div>
          <div class="tk-info-box">
            Estima el área de aporte (cuenca) hacia un punto bajo del terreno, longitud de cunetas necesarias y caudal de diseño usando el Método Racional (INVIAS).
          </div>
          <div class="csec-mini">Punto de desagüe</div>
          <div class="crow">
            <div class="cfield"><label>Punto desagüe (N°)</label><input type="number" id="drenajeDesague" placeholder="1" min="1"></div>
            <div class="cfield"><label>Radio análisis (m)</label><input type="number" id="drenajeRadio" placeholder="50" value="50" step="5" min="5"></div>
          </div>
          <div class="csec-mini" style="margin-top:4px;">Parámetros hidrológicos</div>
          <div class="crow">
            <div class="cfield"><label>Intensidad lluvia (mm/h)</label><input type="number" id="drenajeI" placeholder="80" value="80" step="5" min="10"></div>
            <div class="cfield"><label>Coef. escorrentía C</label><input type="number" id="drenajeC" placeholder="0.5" value="0.5" step="0.05" min="0.1" max="1.0"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCalcDrenaje">Analizar drenaje →</button>
          <div class="cres" id="resDrenaje" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Caudal de diseño Q</div>
              <div class="cres-big-val accent" id="rDrenajeQ">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">Área de aporte</span><span class="cres-val accent" id="rDrenajeArea">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Puntos en cuenca</span><span class="cres-val" id="rDrenajeN">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Long. cuneta estimada</span><span class="cres-val" id="rDrenajeL">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Pendiente media cuenca</span><span class="cres-val" id="rDrenajePend">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Sección cuneta rec.</span><span class="cres-val accent" id="rDrenajeSec">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Tiempo concentración</span><span class="cres-val" id="rDrenajeTc">—</span></div>
            </div>
            <canvas id="miniCanvasDrenaje" class="mini-canvas" height="90"></canvas>
          </div>
          <div class="calc-hint">💡 Q = C·i·A/360 · Fórmula Racional · Diseño hidráulico referencia INVIAS 2013</div>
        </div>

        <!-- ═══ COORDENADAS ═══ -->
        <div class="calc-pane" id="tab-coordi">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">🎯</span>
            <div>
              <div class="calc-tool-title">Consulta de Coordenadas</div>
              <div class="calc-tool-sub">Ficha de punto · Estadísticas · MAGNA-SIRGAS</div>
            </div>
          </div>
          <div class="csec-mini">Ficha completa de un punto</div>
          <div class="crow">
            <div class="cfield"><label>Número de punto (N°)</label><input type="number" id="coordPtoN" placeholder="42" min="1"></div>
            <button class="cbtn" id="btnCoordFicha" style="align-self:flex-end;">Ver ficha →</button>
          </div>
          <div class="cres" id="resCoordFicha" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Punto N°<span id="fichaNum">—</span></div>
              <div class="cres-big-val" id="fichaCota">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">X (Este)</span><span class="cres-val accent" id="fichaX">—</span></div>
              <div class="cres-cell accent"><span class="cres-lbl">Y (Norte)</span><span class="cres-val accent" id="fichaY">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Z (Cota)</span><span class="cres-val" id="fichaZ">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Descripción</span><span class="cres-val" id="fichaDesc">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Cota relativa</span><span class="cres-val" id="fichaCotaRel">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Posición en nube</span><span class="cres-val" id="fichaPosicion">—</span></div>
            </div>
          </div>
          <div class="csec-mini" style="margin-top:10px;">Estadísticas de la nube</div>
          <button class="cbtn cbtn-full" id="btnCoordStats">Calcular estadísticas →</button>
          <div class="cres" id="resCoordStats" style="display:none;margin-top:8px;">
            <div class="cres-grid2" id="coordStatsGrid"></div>
          </div>
          <div class="csec-mini" style="margin-top:10px;">Buscar punto más cercano a coordenada</div>
          <div class="crow">
            <div class="cfield"><label>X (Este)</label><input type="number" id="coordBuscarX" placeholder="100.000" step="0.001"></div>
            <div class="cfield"><label>Y (Norte)</label><input type="number" id="coordBuscarY" placeholder="200.000" step="0.001"></div>
          </div>
          <button class="cbtn cbtn-full" id="btnCoordBuscarXY">Buscar más cercano →</button>
          <div class="cres" id="resCoordCercano" style="display:none;margin-top:8px;">
            <div class="cres-highlight">
              <div class="cres-big-label">Punto más cercano</div>
              <div class="cres-big-val" id="rCercanoN">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell"><span class="cres-lbl">Distancia</span><span class="cres-val accent" id="rCercanoD">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Coordenadas (X,Y,Z)</span><span class="cres-val" id="rCercanoXYZ">—</span></div>
            </div>
          </div>
          <div class="calc-hint">💡 Percentil 25/50/75 de Z para clasificar zonas bajas, medias y altas</div>
        </div>

        <!-- ═══ BUSCAR PUNTO ═══ -->
        <div class="calc-pane" id="tab-buscar">
          <div class="calc-tool-header">
            <span class="calc-tool-icon">🔍</span>
            <div>
              <div class="calc-tool-title">Buscar y centrar punto</div>
              <div class="calc-tool-sub">Localiza cualquier punto en el plano</div>
            </div>
          </div>
          <div class="crow">
            <div class="cfield"><label>Número de punto (N°)</label><input type="number" id="calcBuscarN" placeholder="42" min="1"></div>
            <button class="cbtn" id="btnBuscarPunto">Ir →</button>
          </div>
          <div class="cres" id="resBuscar" style="display:none;">
            <div class="cres-highlight">
              <div class="cres-big-label">Punto N°</div>
              <div class="cres-big-val" id="rBuscarN">—</div>
            </div>
            <div class="cres-grid2">
              <div class="cres-cell accent"><span class="cres-lbl">X (Este)</span><span class="cres-val accent" id="rBuscarX">—</span></div>
              <div class="cres-cell accent"><span class="cres-lbl">Y (Norte)</span><span class="cres-val accent" id="rBuscarY">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Z (Cota)</span><span class="cres-val" id="rBuscarZ">—</span></div>
              <div class="cres-cell"><span class="cres-lbl">Descripción</span><span class="cres-val" id="rBuscarDesc">—</span></div>
            </div>
          </div>
          <div class="calc-hint">💡 El plano se centra automáticamente en el punto seleccionado</div>
        </div>

        </div><!-- /#tkPanes -->
      </div><!-- /.panel-body -->
    </div><!-- /#panel-calculos -->

    <!-- ═══════════════════════════════════════════
         BOTÓN → IR A COTIZACIÓN
    ════════════════════════════════════════════ -->
    <div class="panel" id="panel-cot-link" style="display:none;">
      <div class="panel-body" style="padding:14px;">
        <div class="cot-link-preview" id="cotLinkPreview">
          <div class="cot-link-row">
            <div class="cot-link-stat"><span id="clp-area">—</span><span>m² área</span></div>
            <div class="cot-link-stat"><span id="clp-vol">—</span><span>m³ vol.</span></div>
            <div class="cot-link-stat"><span id="clp-desnivel">—</span><span>m desnivel</span></div>
          </div>
        </div>
        <button id="btnIrCotizacion" class="btn-cot-link" onclick="irACotizacion()">
          <span class="btn-cot-icon">💰</span>
          <div class="btn-cot-text">
            <span>Generar Presupuesto</span>
            <small id="cotLinkSub">Abre el módulo completo de cotización</small>
          </div>
          <span class="btn-cot-arrow" id="cotLinkArrow">→</span>
        </button>
        <div class="cot-link-note">El plano se cargará automáticamente en cotización</div>
      </div>
    </div>

    <!-- Tabla de coordenadas -->
    <div class="panel" id="panel-tabla" style="display:none;">
      <div class="panel-header">
        <span class="panel-icon">📋</span>
        <h2>Listado de Puntos</h2>
        <button class="btn-mini" id="btnExport">↓ CSV</button>
      </div>
      <div class="panel-body">
        <div class="table-wrap">
          <table id="tablaCoords">
            <thead>
              <tr><th>#</th><th>N°</th><th>X (Este)</th><th>Y (Norte)</th><th>Z (Cota)</th><th>Código</th></tr>
            </thead>
            <tbody id="tablaCuerpo"></tbody>
          </table>
        </div>
      </div>
    </div>

  </aside>

  <!-- ════ VISOR ════ -->
  <section class="viewer">

    <!-- Toolbar -->
    <div class="viewer-toolbar">
      <div class="toolbar-left">
        <span class="viewer-title">// VISOR TOPOGRÁFICO</span>
        <span class="viewer-status" id="viewerStatus">Esperando datos...</span>
      </div>
      <div class="toolbar-controls">
        <!-- Vista -->
        <button class="ctrl-btn active" id="btn2D" title="Vista planta (plano topográfico)">2D Planta</button>
        <button class="ctrl-btn"        id="btn3D" title="Vista perspectiva 3D">3D</button>
        <div class="ctrl-divider"></div>
        <!-- Capas -->
        <button class="ctrl-btn active" id="btnHipso"    title="Colorimetría hipsométrica">🎨 Hipso</button>
        <button class="ctrl-btn active" id="btnContornos" title="Curvas de nivel">⌇ Curvas</button>
        <button class="ctrl-btn active" id="btnNpts"      title="Números de punto">N° Pts</button>
        <button class="ctrl-btn active" id="btnCotas"     title="Cotas Z">Z Cotas</button>
        <button class="ctrl-btn"        id="btnCodigos"   title="Mostrar código de cada punto">Códigos</button>
        <button class="ctrl-btn"        id="btnTIN"       title="Líneas de triangulación">TIN</button>
        <div class="ctrl-divider"></div>
        <!-- Acciones -->
        <button class="ctrl-btn"  id="btnReset"     title="Resetear vista">↺</button>
        <button class="ctrl-btn"  id="btnGuardar"   title="Guardar en base de datos">💾</button>
        <button class="ctrl-btn"  id="btnExportPNG" title="Exportar imagen PNG">↓ PNG</button>
        <?php if ($usuarioPlan === 'premium'): ?>
        <button class="ctrl-btn"  id="btnExportPDF" title="Exportar PDF profesional">↓ PDF</button>
        <?php else: ?>
        <button class="ctrl-btn"  id="btnExportPDF" title="Exportar PDF del plano">↓ PDF</button>
        <?php endif; ?>
        <div class="ctrl-divider"></div>
        <button class="ctrl-btn"  id="btnFullscreen" title="Pantalla completa">⛶</button>
      </div>
    </div>

    <!-- Canvas -->
    <div class="canvas-wrap">
      <canvas id="visor3D"></canvas>

      <div class="empty-state" id="emptyState">
        <div class="empty-icon">◈</div>
        <p>Carga un archivo CSV para visualizar<br>el levantamiento topográfico</p>
        <small style="color:#64748b;font-size:11px;margin-top:6px;display:block;">
          Formato: N, X, Y, Z, DESCRIPCION
        </small>
      </div>

      <div class="loading-overlay" id="loadingOverlay" style="display:none;">
        <div class="loading-ring"></div>
        <span>Procesando triangulación TIN...</span>
      </div>

      <div id="puntoHoverLabel"></div>

      <div class="coord-readout" id="coordReadout" style="display:none;">
        <span id="coordText">X: — &nbsp; Y: — &nbsp; Z: —</span>
      </div>

      <div class="controls-hint">
        Pan: arrastrar · Zoom: scroll · 3D rotar: arrastrar
      </div>
    </div>

    <!-- Leyenda de elevación -->
    <div class="z-legend" id="zLegend" style="display:none;">
      <span class="legend-label" id="zLegMin">—</span>
      <div class="legend-bar" id="zLegBar"></div>
      <span class="legend-label" id="zLegMax">—</span>
    </div>

  </section>

</main>

<!-- Modal guardar -->
<div class="modal-overlay" id="modalGuardar">
  <div class="modal">
    <h3>💾 Guardar proyecto</h3>
    <label for="modalNombre">Nombre del proyecto</label>
    <input type="text" id="modalNombre" placeholder="Ej: La Sanjuana — Cancha" maxlength="200">
    <label for="modalDescText">Descripción (opcional)</label>
    <input type="text" id="modalDescText" placeholder="Levantamiento topográfico..." maxlength="255">
    <div class="modal-btns">
      <button class="mbtn mbtn-cancel" id="modalCancelar">Cancelar</button>
      <button class="mbtn mbtn-save"   id="modalConfirmar">Guardar</button>
    </div>
  </div>
</div>

<script src="js/proyecto.js?v=9"></script>
<?php if ($proyectoCargado): ?>
<script>
/* ═══════════════════════════════════════════════════════
   AUTO-CARGA DE PROYECTO DESDE BASE DE DATOS
   Se ejecuta cuando el usuario abre un proyecto guardado
   desde mis_proyectos.php → proyecto.php?cargar=ID
═══════════════════════════════════════════════════════ */
(function () {
  const PROY = <?= json_encode($proyectoCargado) ?>;

  /* ── 1. Inyectar datos del proyecto en el JS global ── */
  //   El JS de proyecto.js expone PROYECTO_ID como variable
  //   Esperamos a que termine de cargar para asignarla
  window.__FYLCAD_CARGAR__ = PROY;

  /* ── 2. Banner informativo ── */
  function mostrarBanner() {
    const b = document.createElement('div');
    b.id = 'banner-cargado';
    b.innerHTML =
      '<span style="font-size:20px">📂</span>' +
      '<div style="flex:1;min-width:0">' +
        '<strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + PROY.nombre + '</strong>' +
        '<small style="color:rgba(255,255,255,.45);font-size:10px">' + PROY.archivo + ' · abierto desde Mis Proyectos</small>' +
      '</div>' +
      '<a href="mis_proyectos.php" style="color:#00e5c0;font-size:11px;white-space:nowrap;text-decoration:none">← Mis proyectos</a>' +
      '<button onclick="this.parentElement.remove()" style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:18px;line-height:1;padding:0 0 0 8px">✕</button>';
    Object.assign(b.style, {
      position: 'fixed', top: '62px', left: '50%', transform: 'translateX(-50%)',
      zIndex: '1000', background: 'rgba(10,17,32,.95)',
      border: '1px solid rgba(0,229,192,.35)', borderRadius: '10px',
      padding: '11px 16px', display: 'flex', alignItems: 'center', gap: '12px',
      color: '#e8edf5', fontSize: '13px', maxWidth: '540px', width: '92vw',
      boxShadow: '0 6px 32px rgba(0,0,0,.6)', backdropFilter: 'blur(12px)',
      fontFamily: "'DM Sans',sans-serif"
    });
    document.body.appendChild(b);
    // Auto-ocultar después de 7 segundos
    setTimeout(() => {
      b.style.transition = 'opacity .6s';
      b.style.opacity = '0';
      setTimeout(() => b.remove(), 600);
    }, 7000);
  }

  /* ── 3. Inyectar CSV como si el usuario lo hubiera subido ── */
  function autoCargar() {
    const blob = new Blob([PROY.csv], { type: 'text/csv' });
    const file = new File([blob], PROY.archivo, { type: 'text/csv' });

    // Asignar al input de archivo
    const fi = document.getElementById('fileInput');
    if (!fi) { console.warn('FYLCAD: fileInput no encontrado'); return; }

    try {
      const dt = new DataTransfer();
      dt.items.add(file);
      fi.files = dt.files;
    } catch (e) {
      // Fallback para Safari
      Object.defineProperty(fi, 'files', { value: { 0: file, length: 1, item: () => file } });
    }

    // Mostrar nombre del archivo en la UI
    const fileNameEl  = document.getElementById('fileName');
    const fileSizeEl  = document.getElementById('fileSize');
    const fileInfoEl  = document.getElementById('fileInfo');
    const btnProcesar = document.getElementById('btnProcesar');
    if (fileNameEl)  fileNameEl.textContent  = PROY.archivo;
    if (fileSizeEl)  fileSizeEl.textContent  = (PROY.csv.length / 1024).toFixed(1) + ' KB';
    if (fileInfoEl)  fileInfoEl.style.display = 'flex';
    if (btnProcesar) btnProcesar.disabled = false;

    // Asignar PROYECTO_ID antes de procesar para que "Generar Presupuesto" funcione de inmediato
    window.PROYECTO_ID     = PROY.id;
    window.PROYECTO_NOMBRE = PROY.nombre;

    // Disparar procesamiento automático
    setTimeout(() => {
      if (btnProcesar && !btnProcesar.disabled) {
        btnProcesar.click();
        // Actualizar título del header una vez procesado
        setTimeout(() => {
          const hTag = document.querySelector('.header-tag');
          if (hTag) hTag.textContent = '// ' + PROY.nombre;
        }, 500);
      }
    }, 120);
  }

  /* ── 4. Esperar a que el DOM esté listo ── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { mostrarBanner(); autoCargar(); });
  } else {
    mostrarBanner(); autoCargar();
  }
})();
</script>
<?php endif; ?>

<script src="js/fylcad_ai_widget.js" data-pagina="proyecto"></script>

</body>
</html>
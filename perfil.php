<?php
/* =============================================
   FYLCAD — Mi Perfil (v2)
============================================= */
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php'); exit;
}

$usuarioId   = $_SESSION['usuario_id'];
$usuarioPlan = $_SESSION['usuario_plan'] ?? 'free';
$db          = getDB();

$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();

$errores = [];
$exitos  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'nombre') {
        $nombre = trim($_POST['nombre'] ?? '');
        if (strlen($nombre) < 2) {
            $errores[] = "El nombre debe tener al menos 2 caracteres.";
        } else {
            $db->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?")->execute([$nombre, $usuarioId]);
            $_SESSION['usuario_nombre'] = $nombre;
            $usuario['nombre']          = $nombre;
            $exitos[] = "Nombre actualizado correctamente.";
        }
    }

    if ($_POST['accion'] === 'password') {
        $actual    = $_POST['password_actual']   ?? '';
        $nueva     = $_POST['password_nueva']    ?? '';
        $confirmar = $_POST['password_confirmar']?? '';

        if (!password_verify($actual, $usuario['password'])) {
            $errores[] = "La contraseña actual no es correcta.";
        } elseif (strlen($nueva) < 8) {
            $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
        } elseif ($nueva !== $confirmar) {
            $errores[] = "Las contraseñas nuevas no coinciden.";
        } else {
            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$hash, $usuarioId]);
            $exitos[] = "Contraseña actualizada correctamente.";
        }
    }
}

// Subir foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'foto') {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['foto'];
        $maxSize  = 3 * 1024 * 1024; // 3MB
        $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap   = ['jpg'=>'jpeg','jpeg'=>'jpeg','png'=>'png','webp'=>'webp','gif'=>'gif'];

        if ($file['size'] > $maxSize) {
            $errores[] = "La imagen no puede superar 3MB.";
        } elseif (!in_array($file['type'], $allowed)) {
            $errores[] = "Formato no permitido. Usa JPG, PNG, WEBP o GIF.";
        } else {
            $uploadDir = __DIR__ . '/uploads/avatares/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Borrar foto anterior si existe
            if (!empty($usuario['foto_perfil'])) {
                $oldFile = $uploadDir . basename($usuario['foto_perfil']);
                if (file_exists($oldFile)) unlink($oldFile);
            }

            $newName  = 'avatar_' . $usuarioId . '_' . time() . '.' . ($extMap[$ext] ?? $ext);
            $destPath = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $fotoUrl = 'uploads/avatares/' . $newName;
                $db->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")->execute([$fotoUrl, $usuarioId]);
                $usuario['foto_perfil'] = $fotoUrl;
                $exitos[] = "Foto de perfil actualizada.";
            } else {
                $errores[] = "No se pudo guardar la imagen. Verifica permisos de la carpeta uploads/.";
            }
        }
    } else {
        $errores[] = "No se recibió ninguna imagen.";
    }
}

$statsStmt = $db->prepare("SELECT COUNT(*) AS proyectos, COALESCE(SUM(total_puntos),0) AS puntos FROM proyectos WHERE usuario_id = ?");
$statsStmt->execute([$usuarioId]);
$stats = $statsStmt->fetch();
$diasRegistrado = (int)((time() - strtotime($usuario['creado_en'])) / 86400);
$iniciales = strtoupper(substr($usuario['nombre'], 0, 2));
$primerNombre = htmlspecialchars(explode(' ', $usuario['nombre'])[0]);

/* ── Estadísticas para logros ── */
$actStmt = $db->prepare("SELECT tipo, COUNT(*) as total FROM actividad WHERE usuario_id = ? GROUP BY tipo");
$actStmt->execute([$usuarioId]);
$actRaw = $actStmt->fetchAll();
$act = [];
foreach($actRaw as $r) $act[$r['tipo']] = (int)$r['total'];

$cotStmt = $db->prepare("SELECT COUNT(*) AS total FROM cotizaciones WHERE usuario_id = ?");
$cotStmt->execute([$usuarioId]);
$totalCot = (int)$cotStmt->fetch()['total'];

$exportStmt = $db->prepare("SELECT COUNT(*) AS total FROM actividad WHERE usuario_id = ? AND tipo='archivo_exportado'");
$exportStmt->execute([$usuarioId]);
$totalExport = (int)$exportStmt->fetch()['total'];

$totalProyectos = (int)$stats['proyectos'];
$totalPuntos    = (int)$stats['puntos'];
$diasReg        = $diasRegistrado;

/* XP calculado */
$xp = $totalProyectos * 150 + $totalCot * 80 + $totalExport * 50 + min($totalPuntos, 50000) / 100 + min($diasReg, 365) * 2;
$xp = (int)$xp;

/* Nivel topógrafo */
$niveles = [
  ['Aprendiz','Conociendo el terreno',0,200,'🌱'],
  ['Auxiliar','Primeros levantamientos',200,500,'📏'],
  ['Técnico','Dominando el campo',500,1000,'🗺️'],
  ['Topógrafo','Experto en precisión',1000,2000,'⛰️'],
  ['Senior','Maestro del relieve',2000,4000,'🏔️'],
  ['Maestro','Leyenda de la topografía',4000,99999,'🏅'],
];
$nivelActual = $niveles[0];
foreach($niveles as $nv){
  if($xp >= $nv[2]) $nivelActual = $nv;
}
$xpSig = $nivelActual[3];
$xpPct = $xpSig > 0 ? min(100, round(($xp - $nivelActual[2]) / ($xpSig - $nivelActual[2]) * 100)) : 100;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Mi Perfil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
    /* ── Reset & vars ── */
    :root {
        --bg:      #05080f;
        --surface: #0c1120;
        --surface2:#0a0f1c;
        --border:  rgba(255,255,255,0.07);
        --border-h:rgba(255,255,255,0.13);
        --accent:  #00e5c0;
        --accent2: #3b82f6;
        --text:    #e8edf5;
        --muted:   #64748b;
        --red:     #ef4444;
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html, body { min-height:100%; }
    body {
        background: var(--bg);
        color: var(--text);
        font-family: "DM Sans", sans-serif;
        font-size: 14px;
    }

    /* ── Topbar ── */
    .topbar {
        position: sticky; top: 0; z-index: 100;
        height: 60px;
        background: rgba(5,8,15,0.85);
        border-bottom: 1px solid var(--border);
        backdrop-filter: blur(16px);
        display: flex; align-items: center;
        padding: 0 clamp(16px,3vw,40px);
        gap: 32px;
    }
    .topbar-logo {
        font-family: "Syne", sans-serif;
        font-weight: 800; font-size: 18px;
        letter-spacing: 3px; color: var(--accent);
        text-decoration: none; white-space: nowrap;
    }
    .topbar-logo span { color: var(--text); opacity:.4; }
    .topbar-nav { display:flex; align-items:center; gap:4px; flex:1; }
    .tnav-item {
        padding: 6px 14px; border-radius: 7px;
        font-size: 13px; font-weight: 500;
        color: var(--muted); text-decoration: none;
        transition: background .2s, color .2s;
    }
    .tnav-item:hover { background:rgba(255,255,255,.04); color:var(--text); }
    .tnav-item.active {
        background: rgba(0,229,192,.08);
        border: 1px solid rgba(0,229,192,.15);
        color: var(--accent);
    }
    .topbar-right { display:flex; align-items:center; gap:12px; flex-shrink:0; }
    .topbar-plan {
        font-size:11px; font-weight:600;
        padding:4px 10px; border-radius:6px; letter-spacing:.5px;
    }
    .topbar-plan.free { background:rgba(100,116,139,.1); border:1px solid rgba(100,116,139,.2); color:var(--muted); }
    .topbar-plan.premium { background:rgba(0,229,192,.1); border:1px solid rgba(0,229,192,.25); color:var(--accent); }
    .topbar-user { position:relative; cursor:pointer; }
    .topbar-avatar {
        width:36px; height:36px; border-radius:10px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        display:flex; align-items:center; justify-content:center;
        font-family:"Syne",sans-serif; font-weight:800; font-size:12px;
        color:#020617; user-select:none; transition:box-shadow .2s;
        overflow: hidden;
    }
    .topbar-avatar img {
        width:100%; height:100%; object-fit:cover; display:block;
    }
    .topbar-user.open .topbar-avatar { box-shadow:0 0 0 2px rgba(0,229,192,.4); }
    .topbar-dropdown {
        display:none; position:absolute; top:calc(100% + 10px); right:0;
        min-width:200px; background:var(--surface);
        border:1px solid var(--border-h); border-radius:12px;
        padding:8px; box-shadow:0 20px 40px rgba(0,0,0,.5);
    }
    .topbar-user.open .topbar-dropdown { display:block; }
    .td-name { font-size:13px; font-weight:600; color:var(--text); padding:4px 8px 2px; }
    .td-email { font-size:11px; color:var(--muted); padding:0 8px 8px; }
    .td-divider { height:1px; background:var(--border); margin:4px 0; }
    .td-link {
        display:block; padding:8px; border-radius:7px;
        font-size:13px; color:var(--muted); text-decoration:none;
        transition:background .15s, color .15s;
    }
    .td-link:hover { background:rgba(255,255,255,.04); color:var(--text); }
    .td-link.danger:hover { color:var(--red); }

    /* ── Wrap ── */
    .page-wrap {
        max-width: 1100px;
        margin: 0 auto;
        padding: clamp(20px,3vw,40px) clamp(16px,3vw,40px);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* ── HERO card ── */
    .hero-card {
        background: linear-gradient(135deg,rgba(0,229,192,.07),rgba(59,130,246,.06));
        border: 1px solid rgba(0,229,192,.12);
        border-radius: 20px;
        padding: 32px 36px;
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 24px;
        position: relative;
        overflow: hidden;
    }
    .hero-glow {
        position:absolute; top:-80px; right:-80px;
        width:280px; height:280px;
        background:radial-gradient(circle,rgba(0,229,192,.1) 0%,transparent 70%);
        pointer-events:none;
    }
    .avatar-wrap {
        position: relative;
        flex-shrink: 0;
        width: 80px;
        height: 80px;
    }
    .hero-avatar {
        width: 80px; height: 80px; border-radius: 20px;
        background: linear-gradient(135deg,var(--accent),var(--accent2));
        display: flex; align-items: center; justify-content: center;
        font-family: "Syne", sans-serif; font-weight: 800;
        font-size: 30px; color: #020617;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0,229,192,.2);
        transition: box-shadow .2s;
    }
    .hero-avatar img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
    }
    .avatar-edit-btn {
        position: absolute;
        bottom: -6px; right: -6px;
        width: 26px; height: 26px;
        background: var(--surface);
        border: 2px solid var(--bg);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px;
        cursor: pointer;
        transition: background .2s, transform .2s;
        line-height: 1;
    }
    .avatar-edit-btn:hover {
        background: var(--accent);
        transform: scale(1.1);
    }
    .avatar-wrap:hover .hero-avatar {
        box-shadow: 0 8px 28px rgba(0,229,192,.35);
    }
    .hero-info { min-width:0; }
    .hero-label {
        font-size:10px; font-weight:600; letter-spacing:2.5px;
        text-transform:uppercase; color:var(--accent); margin-bottom:6px;
    }
    .hero-name {
        font-family:"Syne",sans-serif; font-size:clamp(20px,2.5vw,28px);
        font-weight:800; color:#fff; letter-spacing:-.5px; margin-bottom:5px;
    }
    .hero-email { font-size:13px; color:var(--muted); }
    .hero-stats {
        display: flex; gap: 28px;
        border-left: 1px solid var(--border);
        padding-left: 28px;
        flex-shrink: 0;
    }
    .hstat { text-align:center; }
    .hstat-val {
        display:block;
        font-family:"Syne",sans-serif; font-size:26px;
        font-weight:800; color:#fff; line-height:1;
        margin-bottom:4px;
    }
    .hstat-lbl { font-size:11px; color:var(--muted); }

    /* ── Alertas ── */
    .alert {
        padding:13px 18px; border-radius:10px;
        font-size:13px; font-weight:500;
    }
    .alert-ok  { background:rgba(0,229,192,.08); border:1px solid rgba(0,229,192,.2); color:var(--accent); }
    .alert-err { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); color:#fca5a5; }

    /* ── Plan card ── */
    .plan-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 22px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .plan-card-left { }
    .plan-card-label {
        font-size:10px; font-weight:600; letter-spacing:2px;
        text-transform:uppercase; color:var(--muted); margin-bottom:6px;
    }
    .plan-card-name {
        font-family:"Syne",sans-serif; font-size:18px;
        font-weight:800; color:#fff; margin-bottom:4px;
    }
    .plan-card-desc { font-size:13px; color:var(--muted); font-weight:300; }
    .plan-badge-pill {
        padding:8px 20px; border-radius:100px;
        font-size:13px; font-weight:700; white-space:nowrap;
    }
    .plan-badge-pill.free { background:rgba(100,116,139,.1); border:1px solid rgba(100,116,139,.2); color:var(--muted); }
    .plan-badge-pill.premium { background:rgba(0,229,192,.1); border:1px solid rgba(0,229,192,.3); color:var(--accent); }
    .plan-card-actions { display:flex; align-items:center; gap:12px; flex-shrink:0; }
    .btn-upgrade-sm {
        display:inline-flex; align-items:center; gap:6px;
        background:var(--accent); color:#020617;
        font-size:13px; font-weight:700;
        padding:10px 20px; border-radius:9px;
        text-decoration:none; transition:all .2s; white-space:nowrap;
    }
    .btn-upgrade-sm:hover { background:#00ffda; box-shadow:0 0 16px rgba(0,229,192,.3); transform:translateY(-1px); }

    /* ── Main grid ── */
    .main-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    /* ── Panel ── */
    .panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        transition: border-color .25s;
    }
    .panel:hover { border-color: var(--border-h); }
    .panel-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; gap: 10px;
    }
    .panel-icon {
        width:32px; height:32px; border-radius:8px;
        background:rgba(0,229,192,.08); border:1px solid rgba(0,229,192,.15);
        display:flex; align-items:center; justify-content:center; font-size:15px;
    }
    .panel-header h3 {
        font-family:"Syne",sans-serif; font-size:14px;
        font-weight:700; color:#fff;
    }
    .panel-body { padding: 24px; }

    /* ── Form fields ── */
    .form-field { display:flex; flex-direction:column; gap:7px; margin-bottom:16px; }
    .form-field:last-of-type { margin-bottom:20px; }
    .form-field label {
        font-size:11px; color:var(--muted);
        text-transform:uppercase; letter-spacing:.8px; font-weight:500;
    }
    .form-field input {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 10px; padding: 11px 14px;
        font-size: 14px; color: var(--text);
        font-family: "DM Sans", sans-serif;
        outline: none; transition: border-color .2s, box-shadow .2s;
    }
    .form-field input:focus {
        border-color: rgba(0,229,192,.4);
        box-shadow: 0 0 0 3px rgba(0,229,192,.06);
    }
    .form-field input:disabled { opacity:.4; cursor:not-allowed; }
    .form-field small { font-size:11px; color:var(--muted); }

    .btn-save {
        display:inline-flex; align-items:center; gap:8px;
        background:var(--accent); color:#020617;
        border:none; border-radius:10px; padding:11px 24px;
        font-size:13px; font-weight:700;
        font-family:"DM Sans",sans-serif; cursor:pointer;
        transition:all .2s;
    }
    .btn-save:hover { background:#00ffda; box-shadow:0 0 16px rgba(0,229,192,.3); transform:translateY(-1px); }

    /* ── Danger zone ── */
    .danger-panel {
        background: rgba(239,68,68,.04);
        border: 1px solid rgba(239,68,68,.12);
        border-radius: 16px;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .danger-left h3 {
        font-family:"Syne",sans-serif; font-size:14px;
        color:var(--red); font-weight:700; margin-bottom:5px;
    }
    .danger-left p { font-size:13px; color:var(--muted); font-weight:300; line-height:1.6; }
    .btn-danger {
        background:transparent; border:1px solid rgba(239,68,68,.35);
        color:var(--red); border-radius:9px; padding:10px 20px;
        font-size:13px; font-weight:600; cursor:pointer;
        font-family:"DM Sans",sans-serif; transition:all .2s;
        white-space:nowrap; flex-shrink:0;
    }
    .btn-danger:hover { background:rgba(239,68,68,.1); border-color:rgba(239,68,68,.6); }

    /* ── Responsive ── */
    @media (max-width:860px) {
        .topbar-nav { display:none; }
        .hero-card { grid-template-columns:auto 1fr; }
        .hero-stats { display:none; }
        .main-grid { grid-template-columns:1fr; }
        .danger-panel { flex-direction:column; align-items:flex-start; }
    }

    /* ══ TOPÓGRAFO SISTEMA COMPLETO ══ */
    .topo-universe {
      position: relative;
      margin-bottom: 24px;
      border-radius: 20px;
      overflow: hidden;
      background: #020810;
      border: 1px solid rgba(0,229,192,.12);
      box-shadow: 0 0 80px rgba(0,229,192,.04), 0 20px 60px rgba(0,0,0,.5);
    }
    /* Fondo estrellado */
    .topo-universe::before {
      content:''; position:absolute; inset:0; z-index:0;
      background-image:
        radial-gradient(1px 1px at 15% 20%, rgba(255,255,255,.6) 0%, transparent 100%),
        radial-gradient(1px 1px at 32% 8%, rgba(255,255,255,.4) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 55% 15%, rgba(255,255,255,.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 70% 6%, rgba(255,255,255,.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 85% 22%, rgba(255,255,255,.5) 0%, transparent 100%),
        radial-gradient(1px 1px at 9% 40%, rgba(255,255,255,.3) 0%, transparent 100%),
        radial-gradient(1px 1px at 92% 45%, rgba(255,255,255,.4) 0%, transparent 100%),
        radial-gradient(1.5px 1.5px at 48% 35%, rgba(0,229,192,.4) 0%, transparent 100%),
        linear-gradient(160deg, #020810 0%, #040f1e 40%, #020810 100%);
      pointer-events: none;
    }
    .topo-layout {
      display: grid;
      grid-template-columns: 260px 1fr;
      position: relative; z-index:1;
      min-height: 320px;
    }
    /* Columna izquierda: canvas */
    .topo-stage {
      display: flex; flex-direction: column;
      align-items: center; justify-content: flex-end;
      padding: 16px 0 0;
      position: relative;
      border-right: 1px solid rgba(0,229,192,.07);
      background: linear-gradient(180deg, transparent 0%, rgba(0,229,192,.02) 100%);
    }
    .topo-stage-glow {
      position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
      width: 200px; height: 80px;
      background: radial-gradient(ellipse, rgba(0,229,192,.1) 0%, transparent 70%);
      pointer-events: none;
    }
    #topoCanvas {
      position: relative; z-index: 2;
      cursor: pointer;
      image-rendering: pixelated;
      filter: drop-shadow(0 12px 30px rgba(0,229,192,.2));
      transition: filter .3s ease;
    }
    #topoCanvas:hover {
      filter: drop-shadow(0 16px 40px rgba(0,229,192,.35));
    }
    .topo-canvas-hint {
      font: 400 9px 'DM Mono', monospace;
      color: rgba(0,229,192,.3);
      letter-spacing: .1em;
      text-transform: uppercase;
      margin-top: 8px;
      margin-bottom: 12px;
      position: relative; z-index:2;
      animation: hint-pulse 3s ease-in-out infinite;
    }
    @keyframes hint-pulse { 0%,100%{opacity:.4} 50%{opacity:1} }

    /* Columna derecha: info */
    .topo-panel {
      padding: 22px 24px;
      display: flex; flex-direction: column;
      gap: 18px;
    }
    .topo-identity {
      display: flex; flex-direction: column; gap: 3px;
    }
    .topo-rank-tag {
      display: inline-flex; align-items: center; gap: 6px;
      font: 700 9px 'DM Mono', monospace;
      color: rgba(0,229,192,.6);
      letter-spacing: .15em; text-transform: uppercase;
      margin-bottom: 4px;
    }
    .topo-rank-dot {
      width: 5px; height: 5px; border-radius: 50%;
      background: #00e5c0;
      box-shadow: 0 0 6px #00e5c0;
      animation: dot-blink 2s ease-in-out infinite;
    }
    @keyframes dot-blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .topo-hero-name {
      font: 900 28px 'DM Sans', sans-serif;
      color: #f1f5f9;
      line-height: 1;
      letter-spacing: -.03em;
    }
    .topo-hero-name em {
      font-style: normal;
      background: linear-gradient(90deg, #00e5c0, #00b8d4);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .topo-hero-sub {
      font: 400 12px 'DM Sans', sans-serif;
      color: #475569;
      margin-top: 2px;
    }

    /* XP System */
    .topo-xp-system {}
    .topo-xp-top {
      display: flex; justify-content: space-between;
      align-items: center; margin-bottom: 7px;
    }
    .topo-xp-title {
      font: 700 8.5px 'DM Mono', monospace;
      color: #334155; letter-spacing: .12em; text-transform: uppercase;
    }
    .topo-xp-num {
      font: 600 12px 'DM Mono', monospace;
      color: #00e5c0;
      transition: all .3s;
    }
    .topo-xp-track {
      height: 10px;
      background: rgba(255,255,255,.04);
      border-radius: 5px; overflow: hidden;
      border: 1px solid rgba(255,255,255,.05);
      position: relative;
    }
    .topo-xp-bar {
      height: 100%;
      background: linear-gradient(90deg, #007a6a, #00c4a8, #00e5c0, #7dfff0);
      border-radius: 5px;
      width: 0%;
      transition: width 1.6s cubic-bezier(.4,0,.2,1);
      position: relative;
    }
    .topo-xp-bar::after {
      content: '';
      position: absolute; top: 1px; left: 8px; right: 8px; height: 3px;
      background: rgba(255,255,255,.35);
      border-radius: 3px;
    }
    .topo-xp-bar::before {
      content: '';
      position: absolute; right: 0; top: 0; bottom: 0; width: 30px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.3));
      animation: xp-shine 2.5s ease-in-out infinite;
    }
    @keyframes xp-shine { 0%,80%,100%{opacity:0} 50%{opacity:1} }
    .topo-xp-next {
      font: 400 9px 'DM Mono', monospace;
      color: #1e293b;
      margin-top: 4px;
    }

    /* Logros */
    .topo-logros-wrap {}
    .topo-logros-bar {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 10px;
    }
    .topo-logros-label {
      font: 700 8.5px 'DM Mono', monospace;
      color: #334155; letter-spacing: .12em; text-transform: uppercase;
    }
    .topo-logros-pill {
      background: rgba(0,229,192,.1); border: 1px solid rgba(0,229,192,.2);
      border-radius: 10px; padding: 1px 8px;
      font: 700 9px 'DM Mono', monospace; color: #00e5c0;
    }
    .topo-logros-line {
      flex: 1; height: 1px;
      background: linear-gradient(90deg, rgba(0,229,192,.15), transparent);
    }
    .topo-logros-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(138px,1fr));
      gap: 5px;
    }
    .logro-item {
      display: flex; align-items: center; gap: 8px;
      padding: 7px 9px; border-radius: 10px;
      cursor: default; position: relative; overflow: hidden;
      transition: all .22s cubic-bezier(.4,0,.2,1);
      border: 1px solid transparent;
    }
    .logro-item.on {
      background: rgba(0,229,192,.06);
      border-color: rgba(0,229,192,.18);
    }
    .logro-item.on::before {
      content:''; position:absolute; inset:0;
      background: linear-gradient(135deg, rgba(0,229,192,.04) 0%, transparent 60%);
      pointer-events:none;
    }
    .logro-item.on:hover {
      background: rgba(0,229,192,.11);
      border-color: rgba(0,229,192,.32);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,229,192,.1);
    }
    .logro-item.lock {
      background: rgba(255,255,255,.015);
      border-color: rgba(255,255,255,.04);
      opacity: .38; filter: grayscale(1);
    }
    .logro-item.lock:hover { opacity:.55; filter: grayscale(.5); }
    .logro-ico-box {
      width: 28px; height: 28px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      border-radius: 8px; font-size: 14px;
      background: rgba(255,255,255,.04);
    }
    .logro-item.on .logro-ico-box {
      background: rgba(0,229,192,.08);
    }
    .logro-text {}
    .logro-nom {
      display: block;
      font: 600 10px 'DM Sans', sans-serif; color: #64748b;
      line-height: 1.2;
    }
    .logro-item.on .logro-nom { color: #e2e8f0; }
    .logro-req {
      display: block;
      font: 400 8px 'DM Mono', monospace; color: #1e293b;
      line-height: 1;
    }
    .logro-item.on .logro-req { color: #334155; }

    /* Toast de logro */
    #logroToast {
      position: fixed; bottom: 28px; right: 28px;
      z-index: 9999;
      background: linear-gradient(135deg, #070f22, #091628);
      border: 1px solid rgba(0,229,192,.35);
      border-radius: 16px;
      padding: 14px 18px 14px 14px;
      display: flex; align-items: center; gap: 13px;
      max-width: 310px;
      pointer-events: none;
      transform: translateX(140%) scale(.9);
      transition: transform .45s cubic-bezier(.34,1.56,.64,1);
      box-shadow: 0 16px 48px rgba(0,0,0,.5), 0 0 24px rgba(0,229,192,.08);
    }
    #logroToast.show { transform: translateX(0) scale(1); }
    .lt-ico { font-size: 32px; flex-shrink:0; }
    .lt-body {}
    .lt-pre { font:700 8px 'DM Mono',monospace; color:#00e5c0; letter-spacing:.1em; text-transform:uppercase; margin-bottom:3px; }
    .lt-name { font:700 15px 'DM Sans',sans-serif; color:#f1f5f9; }
    .lt-desc { font:400 11px 'DM Sans',sans-serif; color:#475569; margin-top:2px; }

    /* Efecto confetti */
    .cfp {
      position: fixed; pointer-events:none; z-index:9998;
      width:7px; height:7px; border-radius:2px;
      animation: cf-fall 1.4s ease-out forwards;
    }
    @keyframes cf-fall {
      0%   { transform:translateY(0) rotate(0deg); opacity:1; }
      100% { transform:translateY(140px) rotate(900deg); opacity:0; }
    }

    /* Modo reacción al scroll */
    .topo-react-dot {
      position: absolute; top: 12px; right: 12px;
      width: 7px; height: 7px; border-radius: 50%;
      background: #00e5c0;
      box-shadow: 0 0 8px #00e5c0;
      animation: dot-blink 1.5s ease-in-out infinite;
    }

    @media (max-width:680px) {
      .topo-layout { grid-template-columns:1fr; }
      .topo-stage { border-right:none; border-bottom:1px solid rgba(0,229,192,.07); }
      .topo-logros-grid { grid-template-columns: repeat(2,1fr); }
    }
    </style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <a href="index.php" class="topbar-logo">FYL<span>CAD</span></a>
    <nav class="topbar-nav">
        <a href="dashboard.php"     class="tnav-item">Panel</a>
        <a href="proyecto.php"      class="tnav-item">Módulo 3D</a>
        <a href="mis_proyectos.php" class="tnav-item">Proyectos</a>
        <a href="perfil.php"        class="tnav-item active">Perfil</a>
        <a href="planes.php"        class="tnav-item">Planes</a>
    </nav>
    <div class="topbar-right">
        <span class="topbar-plan <?= $usuarioPlan ?>">
            <?= $usuarioPlan === 'premium' ? '★ Premium' : '◈ Free' ?>
        </span>
        <div class="topbar-user">
            <div class="topbar-avatar">
                <?php if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>?v=<?= time() ?>" alt="">
                <?php else: ?>
                    <?= $iniciales ?>
                <?php endif; ?>
            </div>
            <div class="topbar-dropdown">
                <div class="td-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="td-email"><?= htmlspecialchars($usuario['email']) ?></div>
                <div class="td-divider"></div>
                <a href="perfil.php" class="td-link">Mi perfil</a>
                <a href="dashboard.php?logout=1" class="td-link danger">Cerrar sesión</a>
            </div>
        </div>
    </div>
</header>

<div class="page-wrap">

    <!-- HERO -->
    <div class="hero-card">
        <div class="hero-glow"></div>
        <!-- Avatar con foto o iniciales -->
        <div class="avatar-wrap">
            <div class="hero-avatar">
                <?php if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>?v=<?= time() ?>"
                         alt="Foto de perfil">
                <?php else: ?>
                    <?= $iniciales ?>
                <?php endif; ?>
            </div>
            <label class="avatar-edit-btn" for="fotoInput" title="Cambiar foto">
                📷
            </label>
            <form method="POST" enctype="multipart/form-data" id="fotoForm">
                <input type="hidden" name="accion" value="foto">
                <input type="file" id="fotoInput" name="foto"
                       accept="image/jpeg,image/png,image/webp,image/gif"
                       style="display:none;"
                       onchange="document.getElementById('fotoForm').submit()">
            </form>
        </div>
        <div class="hero-info">
            <div class="hero-label">Mi Perfil</div>
            <div class="hero-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
            <div class="hero-email">
                <?= htmlspecialchars($usuario['email']) ?>
                · Miembro desde <?= date('F Y', strtotime($usuario['creado_en'])) ?>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hstat">
                <span class="hstat-val"><?= $stats['proyectos'] ?></span>
                <span class="hstat-lbl">Proyectos</span>
            </div>
            <div class="hstat">
                <span class="hstat-val"><?= number_format($stats['puntos']) ?></span>
                <span class="hstat-lbl">Puntos</span>
            </div>
            <div class="hstat">
                <span class="hstat-val"><?= $diasRegistrado ?></span>
                <span class="hstat-lbl">Días</span>
            </div>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php foreach ($exitos  as $m): ?>
        <div class="alert alert-ok">✓ <?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errores as $m): ?>
        <div class="alert alert-err">✗ <?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>

    <!-- ══ TOPÓGRAFO RPG SISTEMA ══ -->
    <div class="topo-universe">
      <div class="topo-react-dot" id="topoDot"></div>
      <div class="topo-layout">

        <!-- STAGE: canvas del personaje -->
        <div class="topo-stage">
          <div class="topo-stage-glow"></div>
          <canvas id="topoCanvas" width="220" height="260"></canvas>
          <div class="topo-canvas-hint">↑ clic para interactuar</div>
        </div>

        <!-- PANEL: info, XP, logros -->
        <div class="topo-panel">

          <!-- Identidad -->
          <div class="topo-identity">
            <div class="topo-rank-tag">
              <div class="topo-rank-dot"></div>
              Topógrafo Digital · Nivel <?= array_search($nivelActual, $niveles) + 1 ?>
            </div>
            <div class="topo-hero-name"><?= $nivelActual[4] ?> <em><?= htmlspecialchars($nivelActual[0]) ?></em></div>
            <div class="topo-hero-sub"><?= htmlspecialchars($nivelActual[1]) ?></div>
          </div>

          <!-- XP -->
          <div class="topo-xp-system">
            <div class="topo-xp-top">
              <span class="topo-xp-title">Experiencia</span>
              <span class="topo-xp-num" id="topoXpNum">0 XP</span>
            </div>
            <div class="topo-xp-track">
              <div class="topo-xp-bar" id="topoXpBar" style="width:0%"></div>
            </div>
            <div class="topo-xp-next">
              <?php if($nivelActual[3] < 99999): ?>
                <?= number_format($xp) ?> / <?= number_format($nivelActual[3]) ?> XP
                · Próximo: <?= $niveles[min(array_search($nivelActual,$niveles)+1, count($niveles)-1)][0] ?>
              <?php else: ?>
                <?= number_format($xp) ?> XP · ★ Nivel Máximo Alcanzado
              <?php endif; ?>
            </div>
          </div>

          <!-- Logros -->
          <div class="topo-logros-wrap">
            <?php
            $logros = [
              ['🗺️','Primer mapa',     'Guarda 1 proyecto',          $totalProyectos>=1],
              ['📐','Topógrafo activo','5 proyectos creados',         $totalProyectos>=5],
              ['🏔️','Maestro del campo','20 proyectos',              $totalProyectos>=20],
              ['📊','Cotizador',        '1 cotización',               $totalCot>=1],
              ['💰','Presupuestador',   '5 cotizaciones',             $totalCot>=5],
              ['🚀','Power User',       '10 cotizaciones',            $totalCot>=10],
              ['📤','Exportador',       '1 exportación',              $totalExport>=1],
              ['⚡','Analítico',        '10 exportaciones',           $totalExport>=10],
              ['📍','Precision GPS',    '1 000 puntos',               $totalPuntos>=1000],
              ['🌐','Gran levantamiento','10 000 puntos',             $totalPuntos>=10000],
              ['📅','Veterano',         '30 días en FYLCAD',          $diasReg>=30],
              ['🎯','Comprometido',     '90 días',                    $diasReg>=90],
              ['⭐','Leyenda',          '1 año en FYLCAD',            $diasReg>=365],
            ];
            $on = count(array_filter($logros, fn($l)=>$l[3]));
            ?>
            <div class="topo-logros-bar">
              <span class="topo-logros-label">Logros</span>
              <span class="topo-logros-pill"><?= $on ?>/<?= count($logros) ?></span>
              <div class="topo-logros-line"></div>
            </div>
            <div class="topo-logros-grid">
              <?php foreach($logros as [$ico,$nom,$req,$desbloq]): ?>
              <div class="logro-item <?= $desbloq?'on':'lock' ?>"
                   data-ico="<?= $ico ?>"
                   data-nom="<?= htmlspecialchars($nom) ?>"
                   data-req="<?= htmlspecialchars($req) ?>"
                   data-on="<?= $desbloq?1:0 ?>">
                <div class="logro-ico-box"><?= $ico ?></div>
                <div class="logro-text">
                  <span class="logro-nom"><?= htmlspecialchars($nom) ?></span>
                  <span class="logro-req"><?= htmlspecialchars($req) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div><!-- /topo-panel -->
      </div><!-- /topo-layout -->
    </div><!-- /topo-universe -->

    <!-- Toast logro -->
    <div id="logroToast">
      <div class="lt-ico" id="ltIco">🏆</div>
      <div class="lt-body">
        <div class="lt-pre" id="ltPre">Logro desbloqueado</div>
        <div class="lt-name" id="ltName"></div>
        <div class="lt-desc" id="ltDesc"></div>
      </div>
    </div>

    <!-- PLAN ACTUAL -->
    <div class="plan-card">
        <div class="plan-card-left">
            <div class="plan-card-label">Plan actual</div>
            <div class="plan-card-name"><?= $usuarioPlan === 'premium' ? '★ Plan Premium' : '◈ Plan Free' ?></div>
            <div class="plan-card-desc">
                <?= $usuarioPlan === 'premium'
                    ? 'Tienes acceso completo a todas las funciones de FYLCAD.'
                    : 'Actualiza a Premium para desbloquear puntos ilimitados y exportar PDF.' ?>
            </div>
        </div>
        <div class="plan-card-actions">
            <span class="plan-badge-pill <?= $usuarioPlan ?>">
                <?= $usuarioPlan === 'premium' ? '★ Premium' : '◈ Free' ?>
            </span>
            <?php if ($usuarioPlan === 'free'): ?>
            <a href="planes.php" class="btn-upgrade-sm">Mejorar plan →</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FORMULARIOS -->
    <div class="main-grid">

        <!-- Información personal -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-icon">👤</div>
                <h3>Información personal</h3>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="nombre">
                    <div class="form-field">
                        <label>Nombre completo</label>
                        <input type="text" name="nombre"
                               value="<?= htmlspecialchars($usuario['nombre']) ?>"
                               required minlength="2" maxlength="100">
                    </div>
                    <div class="form-field">
                        <label>Correo electrónico</label>
                        <input type="email" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
                        <small>El email no se puede modificar.</small>
                    </div>
                    <div class="form-field">
                        <label>Miembro desde</label>
                        <input type="text" value="<?= date('d \d\e F \d\e Y', strtotime($usuario['creado_en'])) ?>" disabled>
                    </div>
                    <button type="submit" class="btn-save">Guardar cambios</button>
                </form>
            </div>
        </div>

        <!-- Cambiar contraseña -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-icon">🔒</div>
                <h3>Cambiar contraseña</h3>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="password">
                    <div class="form-field">
                        <label>Contraseña actual</label>
                        <input type="password" name="password_actual" required>
                    </div>
                    <div class="form-field">
                        <label>Nueva contraseña</label>
                        <input type="password" name="password_nueva" required minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="form-field">
                        <label>Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmar" required>
                    </div>
                    <button type="submit" class="btn-save">Actualizar contraseña</button>
                </form>
            </div>
        </div>

    </div>

    <!-- ZONA PELIGROSA -->
    <div class="danger-panel">
        <div class="danger-left">
            <h3>⚠ Zona peligrosa</h3>
            <p>Eliminar tu cuenta borrará todos tus proyectos y datos de forma permanente.<br>Esta acción no se puede deshacer.</p>
        </div>
        <button class="btn-danger" onclick="alert('Para eliminar tu cuenta escríbenos a contacto@fylcad.com')">
            Eliminar mi cuenta
        </button>
    </div>

</div>

<script>
    const avatar = document.querySelector('.topbar-user');
    avatar?.addEventListener('click', () => avatar.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (!avatar?.contains(e.target)) avatar?.classList.remove('open');
    });
</script>


<script>
/* ════════════════════════════════════════════════════════════════
   FYLCAD — TOPÓGRAFO INTERACTIVO v3
   · Canvas 220×260 HiDPI-ready
   · Personaje articulado con animaciones por estado
   · Reacciona en tiempo real a las acciones del usuario en la pág
   · Logros con confetti + toast cinematográfico
   · Modo: idle / walk / measure / celebrate / work / patrol
════════════════════════════════════════════════════════════════ */
(function TOPO(){
  'use strict';

  /* ── Canvas HiDPI ── */
  const el = document.getElementById('topoCanvas');
  if(!el) return;
  const DPR = Math.min(window.devicePixelRatio||1, 2);
  const CW = 220, CH = 260;
  el.width  = CW * DPR; el.height = CH * DPR;
  el.style.width  = CW+'px'; el.style.height = CH+'px';
  const C = el.getContext('2d');
  C.scale(DPR, DPR);
  const cx = CW/2;

  /* ── Datos PHP → JS ── */
  const D = {
    xp:      <?= $xp ?>,
    xpPct:   <?= $xpPct ?>,
    nivel:   <?= array_search($nivelActual,$niveles)!==false ? array_search($nivelActual,$niveles) : 0 ?>,
    proy:    <?= $totalProyectos ?>,
    cot:     <?= $totalCot ?>,
    exp:     <?= $totalExport ?>,
    puntos:  <?= $totalPuntos ?>,
    dias:    <?= $diasReg ?>,
    nombre:  '<?= addslashes($primerNombre) ?>',
  };

  /* ── Equipo desbloqueado según progreso ── */
  const EQ = {
    casco:   true,
    chaleco: D.proy  >= 1,
    mochila: D.proy  >= 5,
    tripode: D.cot   >= 1,
    gps:     D.exp   >= 1,
    plano:   D.puntos>= 1000,
    insignia:D.dias  >= 30,
    medalla: D.dias  >= 365,
    auricular: D.cot >= 5,
  };

  /* ── Paletas por nivel ── */
  const PALETAS = [
    {casco:'#64748b',body:'#1e3a5f',bota:'#1c1917', acc:'#94a3b8'}, // 0 Aprendiz
    {casco:'#f97316',body:'#1e40af',bota:'#431407', acc:'#fb923c'}, // 1 Auxiliar
    {casco:'#eab308',body:'#1d4ed8',bota:'#422006', acc:'#fde047'}, // 2 Técnico
    {casco:'#16a34a',body:'#1e40af',bota:'#14532d', acc:'#4ade80'}, // 3 Topógrafo
    {casco:'#06b6d4',body:'#1e3a8a',bota:'#0c4a6e', acc:'#22d3ee'}, // 4 Senior
    {casco:'#a855f7',body:'#312e81',bota:'#3b0764', acc:'#d8b4fe'}, // 5 Maestro
  ];
  const P = PALETAS[Math.min(D.nivel, PALETAS.length-1)];
  const SKIN = '#e8b88a';
  const SKIN2= '#d4956a';

  /* ── Estado de animación ── */
  let F = 0;                          // frame counter
  let mode = 'idle';                  // modo actual
  let modeT = 0;                      // frames restantes en modo
  let expression = 'happy';           // happy | focus | excited | thinking
  let walkDir = 1;                    // dirección caminar
  let walkX = 0;                      // offset X caminando
  let actionLabel = '';               // texto flotante
  let actionLabelT = 0;

  /* ── Partículas del sistema ── */
  const sparks = [];     // chispas click
  const floaters = [];   // emojis flotantes de fondo
  const toolBeam = {on:false, prog:0, tx:0, ty:0};

  /* Partículas de fondo */
  for(let i=0; i<8; i++) floaters.push({
    x: Math.random()*CW, y: 40+Math.random()*160,
    vy: -.15-.2*Math.random(), vx: (Math.random()-.5)*.15,
    a: Math.random()*Math.PI*2,
    char:['·','✦','⊹','◦','∘','⋆'][i%6],
    size:6+Math.random()*5, alpha:.08+Math.random()*.15,
    drift: Math.random()*Math.PI*2,
  });

  /* ── API pública: reaccionar a eventos externos ── */
  window.TOPO = {
    react(event) {
      if(event==='csv_loaded')    { setMode('work',260);  flash('📁 CSV cargado'); }
      if(event==='calcular')      { setMode('measure',200); flash('📐 Calculando…'); }
      if(event==='guardar')       { setMode('celebrate',180); flash('💾 Guardado!'); confettiAt(cx,80,12); }
      if(event==='exportar')      { setMode('work',200); flash('📤 Exportando…'); }
      if(event==='cotizar')       { setMode('measure',240); flash('💰 Cotizando…'); }
      if(event==='navigate')      { setMode('walk',120); }
      if(event==='idle')          { setMode('idle',0); }
    }
  };

  function setMode(m, dur) {
    mode = m; modeT = dur;
    if(m==='walk') { walkDir = Math.random()>.5?1:-1; }
    if(m==='celebrate') expression='excited';
    else if(m==='work'||m==='measure') expression='focus';
    else expression='happy';
  }

  function flash(text) {
    actionLabel = text; actionLabelT = 100;
  }

  /* ── Escuchar actividad del usuario en la página ── */
  document.addEventListener('click', e => {
    const tag = e.target.closest('button,a,[data-m]');
    if(!tag) return;
    const t = tag.textContent.trim().toLowerCase();
    if(t.includes('guardar') || t.includes('save'))  TOPO.react('guardar');
    else if(t.includes('cotiz'))                       TOPO.react('cotizar');
    else if(t.includes('export')||t.includes('pdf'))  TOPO.react('exportar');
    else                                               TOPO.react('navigate');
  });

  /* Formularios: al hacer submit */
  document.querySelectorAll('form').forEach(f=>{
    f.addEventListener('submit', ()=>TOPO.react('guardar'));
  });

  /* Scroll rápido = topógrafo corre */
  let lastScroll = window.scrollY, scrollVel = 0;
  window.addEventListener('scroll',()=>{
    scrollVel = Math.abs(window.scrollY - lastScroll);
    lastScroll = window.scrollY;
    if(scrollVel > 60 && mode==='idle') setMode('walk',90);
  });

  /* ════════════════ DRAWING HELPERS ════════════════ */

  function rr(x,y,w,h,r=4){ C.beginPath(); C.roundRect(x,y,w,h,r); C.fill(); }
  function arc(x,y,r,s=0,e=Math.PI*2){ C.beginPath(); C.arc(x,y,r,s,e); C.fill(); }
  function stroke_arc(x,y,r){ C.beginPath(); C.arc(x,y,r,0,Math.PI*2); C.stroke(); }
  function line(x1,y1,x2,y2){ C.beginPath(); C.moveTo(x1,y1); C.lineTo(x2,y2); C.stroke(); }
  function shadow(x,y,blur,col){ C.shadowOffsetX=x; C.shadowOffsetY=y; C.shadowBlur=blur; C.shadowColor=col; }
  function noShadow(){ C.shadowOffsetX=0; C.shadowOffsetY=0; C.shadowBlur=0; C.shadowColor='transparent'; }

  /* ════════════════ FONDO CARICATURESCO ════════════════ */

  function drawBackground() {
    // Cielo degradado suave
    const sky = C.createLinearGradient(0,0,0,CH);
    sky.addColorStop(0,'#0d1b3e');
    sky.addColorStop(.6,'#102040');
    sky.addColorStop(1,'#0a2a1a');
    C.fillStyle=sky; C.fillRect(0,0,CW,CH);

    // Estrellas
    const starData=[[18,18,1.2],[40,8,0.8],[72,22,1],[95,10,1.4],[130,16,0.9],[158,8,1.1],[188,20,0.8],[205,12,1.3]];
    starData.forEach(([sx,sy,sr])=>{
      const twinkle=0.5+0.5*Math.sin(F*.04+sx);
      C.fillStyle=`rgba(255,255,230,${twinkle*.8})`;
      arc(sx,sy,sr);
      // Brillo cruciforme
      C.strokeStyle=`rgba(255,255,230,${twinkle*.3})`; C.lineWidth=0.5;
      line(sx-sr*2.5,sy,sx+sr*2.5,sy);
      line(sx,sy-sr*2.5,sx,sy+sr*2.5);
    });

    // Luna caricaturesca con cara
    const moonX=CW-32, moonY=30;
    C.fillStyle='#fef9c3';
    shadow(2,2,8,'rgba(254,240,100,.4)');
    arc(moonX,moonY,16);
    noShadow();
    // cara de la luna
    C.fillStyle='#ca8a04';
    arc(moonX-5,moonY-3,2); arc(moonX+4,moonY-4,1.5); // ojos
    C.strokeStyle='#ca8a04'; C.lineWidth=1.5;
    C.beginPath(); C.arc(moonX-1,moonY+4,4,.1,Math.PI-.1); C.stroke(); // sonrisa
    // Cráteres
    C.fillStyle='rgba(200,180,80,.35)';
    arc(moonX+8,moonY+5,3); arc(moonX-8,moonY+7,2); arc(moonX+5,moonY-8,1.5);

    // Nubes caricaturescas
    const cloudOff = (F*0.18)%260;
    drawCloud(20-cloudOff, 50, 0.7);
    drawCloud(140-cloudOff*0.6, 38, 0.5);
    drawCloud((260-cloudOff*0.4)%300-40, 62, 0.6);

    // Cerros/montañas con estilo cartoon
    // Cerro trasero derecho
    C.fillStyle='#0e2d1a';
    C.beginPath();
    C.moveTo(CW,CH); C.lineTo(CW,155);
    C.quadraticCurveTo(185,100,160,155);
    C.quadraticCurveTo(140,170,CW,CH);
    C.closePath(); C.fill();
    // Nieve en punta
    C.fillStyle='rgba(255,255,255,.7)';
    C.beginPath();
    C.moveTo(185,105); C.lineTo(175,130); C.lineTo(195,130); C.closePath(); C.fill();

    // Cerro trasero izq
    C.fillStyle='#0c2518';
    C.beginPath();
    C.moveTo(0,CH); C.lineTo(0,160);
    C.quadraticCurveTo(20,110,50,155);
    C.quadraticCurveTo(70,175,0,CH);
    C.closePath(); C.fill();

    // Suelo con hierba cartoon
    const ground = C.createLinearGradient(0,192,0,CH);
    ground.addColorStop(0,'#0f4a1e');
    ground.addColorStop(.3,'#166534');
    ground.addColorStop(1,'#14532d');
    C.fillStyle=ground; C.fillRect(0,196,CW,CH-196);

    // Línea de horizonte brillante
    const glowLine = C.createLinearGradient(0,0,CW,0);
    glowLine.addColorStop(0,'transparent');
    glowLine.addColorStop(.3,'rgba(74,222,128,.5)');
    glowLine.addColorStop(.7,'rgba(74,222,128,.5)');
    glowLine.addColorStop(1,'transparent');
    C.strokeStyle=glowLine; C.lineWidth=1.5;
    line(0,196,CW,196);

    // Hierba con curvas
    C.fillStyle='#16a34a';
    for(let gx=0; gx<CW; gx+=10){
      const h=3+Math.sin(gx*.4+F*.02)*2;
      C.beginPath();
      C.moveTo(gx,196);
      C.quadraticCurveTo(gx+3,196-h,gx+5,196);
      C.quadraticCurveTo(gx+7,196-h*.8,gx+10,196);
      C.fill();
    }

    // Suelo con textura grid ligera
    C.strokeStyle='rgba(0,180,80,.06)'; C.lineWidth=.4;
    for(let gx=0;gx<CW;gx+=20) line(gx,196,gx,CH);
    for(let gy=200;gy<CH;gy+=16) line(0,gy,CW,gy);

    // Partículas flotantes
    floaters.forEach(p => {
      p.x += p.vx + Math.sin(F*.012+p.drift)*.12;
      p.y += p.vy;
      if(p.y < -10) { p.y = CH*.8; p.x = Math.random()*CW; }
      C.globalAlpha = p.alpha*(.5+.5*Math.sin(F*.025+p.a));
      C.font = p.size+'px sans-serif'; C.textAlign='center';
      C.fillStyle='#4ade80';
      C.fillText(p.char, p.x, p.y);
    });
    C.globalAlpha=1; C.textAlign='left';
  }

  function drawCloud(x, y, alpha) {
    C.fillStyle=`rgba(200,230,255,${alpha*.45})`;
    C.beginPath();
    C.arc(x+20,y,12,0,Math.PI*2); C.arc(x+35,y-6,15,0,Math.PI*2);
    C.arc(x+52,y,13,0,Math.PI*2); C.arc(x+38,y+6,14,0,Math.PI*2);
    C.fill();
  }

  /* ════════════════ TRÍPODE CARTOON ════════════════ */

  function drawTripode(gY) {
    if(!EQ.tripode) return;
    const tx = cx + 44, ty = gY - 52;

    // Patas con grosor cartoon
    C.strokeStyle='#475569'; C.lineWidth=3; C.lineCap='round';
    [[tx,ty,tx-22,gY],[tx,ty,tx+2,gY],[tx,ty,tx+24,gY]].forEach(([x1,y1,x2,y2])=>{
      // Sombra pata
      C.strokeStyle='rgba(0,0,0,.3)'; line(x1+1,y1+1,x2+1,y2+1);
      C.strokeStyle='#64748b'; line(x1,y1,x2,y2);
    });
    C.lineCap='butt';

    // Cuerpo teodolito cartoon (más redondeado)
    shadow(2,3,6,'rgba(0,0,0,.4)');
    C.fillStyle='#334155'; C.beginPath(); C.roundRect(tx-13,ty-8,26,16,6); C.fill();
    C.fillStyle='#1e293b'; C.beginPath(); C.roundRect(tx-9,ty-15,18,9,4); C.fill();
    noShadow();
    // Highlight
    C.fillStyle='rgba(255,255,255,.1)';
    C.beginPath(); C.roundRect(tx-11,ty-13,16,4,3); C.fill();

    // Visor grande estilo cartoon
    const vcol = EQ.gps ? '#00e5c0' : '#94a3b8';
    shadow(0,2,6,EQ.gps?'rgba(0,229,192,.6)':'transparent');
    C.fillStyle=vcol; arc(tx+10,ty-2,5);
    noShadow();
    // Brillo visor
    C.fillStyle='rgba(255,255,255,.7)'; arc(tx+9,ty-3.5,2);
    C.fillStyle='rgba(255,255,255,.3)'; arc(tx+11,ty-1,1);

    // Rayo medición animado
    if(mode==='measure'||mode==='work') {
      const prog=(Math.sin(F*.1)+1)/2;
      const alpha=.4+prog*.5;
      // Rayo con gradiente
      const rayGrad=C.createLinearGradient(tx+10,ty-2,cx-10,ty+22);
      rayGrad.addColorStop(0,`rgba(0,229,192,${alpha})`);
      rayGrad.addColorStop(1,'rgba(0,229,192,0)');
      C.strokeStyle=rayGrad; C.lineWidth=1.5; C.setLineDash([4,3]);
      line(tx+10,ty-2,cx-10,ty+22+prog*10);
      C.setLineDash([]);
      // Punto de impacto pulsante
      shadow(0,0,8+prog*6,'rgba(0,229,192,.8)');
      C.fillStyle=`rgba(0,229,192,${.6+prog*.4})`;
      arc(cx-10, ty+22+prog*10, 3+prog);
      noShadow();
    }
  }

  /* ════════════════ PERSONAJE CARTOON ════════════════ */

  function drawCharacter(gY) {
    const breath  = Math.sin(F*.05)*1.8;
    const bobY    = mode==='walk' ? Math.abs(Math.sin(F*.16))*5 : 0;
    const celebY  = mode==='celebrate' ? -Math.abs(Math.sin(F*.2))*14 : 0;
    const lSwing  = mode==='walk'      ? Math.sin(F*.16)*14
                  : mode==='celebrate' ? Math.sin(F*.2)*18 : 0;
    const aSwing  = mode==='walk'      ? Math.sin(F*.16+Math.PI)*12
                  : mode==='work'      ? Math.sin(F*.1)*8
                  : mode==='celebrate' ? Math.sin(F*.2+Math.PI)*22
                  : Math.sin(F*.04)*3;

    // Personaje con proporciones CARTOON
    // Cabeza grande (~40% del cuerpo)
    // Cuerpo pequeño y redondeado
    // Piernas cortas y gordas
    // Manos grandes y redondas

    const SCALE = 1.05; // escala general ligeramente grande
    const bY = gY - 78 - bobY + celebY; // base del cuerpo

    // Walk offset con rebote
    if(mode==='walk') {
      walkX += walkDir * 1.4;
      if(Math.abs(walkX)>22) walkDir*=-1;
    } else {
      walkX *= .85;
    }
    const wx = cx + Math.round(walkX);

    /* ── SOMBRA ELÍPTICA ── */
    const shadowScale = 1 - Math.abs(celebY)/80;
    C.fillStyle=`rgba(0,0,0,.3)`;
    C.beginPath();
    C.ellipse(wx, gY, 22*shadowScale, 5*shadowScale, 0, 0, Math.PI*2);
    C.fill();

    /* ── PIERNAS CARTOON (cortas, gordas, redondeadas) ── */
    const legW=13, legH=22, legR=7;
    const legOff=9;

    // Pierna izquierda
    C.save(); C.translate(wx-legOff, bY+40+breath); C.rotate(lSwing*.016);
    shadow(1,2,4,'rgba(0,0,0,.3)');
    C.fillStyle=P.body; C.beginPath(); C.roundRect(-legW/2,0,legW,legH,legR); C.fill();
    noShadow();
    // Bota izquierda (grande, cartoon)
    C.fillStyle=P.bota;
    C.beginPath(); C.roundRect(-legW/2-3,legH-4,legW+8,11,[3,8,8,3]); C.fill();
    C.fillStyle='rgba(255,255,255,.1)'; // brillo bota
    C.beginPath(); C.roundRect(-legW/2-1,legH-2,legW+4,3,[2,2,0,0]); C.fill();
    // Suela
    C.fillStyle='#0a0a0a'; C.beginPath(); C.roundRect(-legW/2-4,legH+5,legW+10,3,[0,0,4,4]); C.fill();
    C.restore();

    // Pierna derecha
    C.save(); C.translate(wx+legOff, bY+40+breath); C.rotate(-lSwing*.016);
    shadow(1,2,4,'rgba(0,0,0,.3)');
    C.fillStyle=P.body; C.beginPath(); C.roundRect(-legW/2,0,legW,legH,legR); C.fill();
    noShadow();
    C.fillStyle=P.bota;
    C.beginPath(); C.roundRect(-legW/2-3,legH-4,legW+8,11,[3,8,8,3]); C.fill();
    C.fillStyle='rgba(255,255,255,.1)';
    C.beginPath(); C.roundRect(-legW/2-1,legH-2,legW+4,3,[2,2,0,0]); C.fill();
    C.fillStyle='#0a0a0a'; C.beginPath(); C.roundRect(-legW/2-4,legH+5,legW+10,3,[0,0,4,4]); C.fill();
    C.restore();

    /* ── CUERPO CARTOON (barrigón, redondeado) ── */
    const bodyW=38, bodyH=42;
    shadow(2,4,10,'rgba(0,0,0,.35)');
    C.fillStyle = EQ.chaleco ? P.casco : P.body;
    C.beginPath(); C.roundRect(wx-bodyW/2, bY+breath, bodyW, bodyH, [12,12,14,14]); C.fill();
    noShadow();

    // Barriga más redondeada (abultada)
    C.fillStyle = EQ.chaleco
      ? `color-mix(in srgb, ${P.casco} 90%, white 10%)`
      : `color-mix(in srgb, ${P.body} 90%, white 10%)`;
    C.beginPath(); C.ellipse(wx, bY+breath+28, 16, 14, 0, 0, Math.PI*2); C.fill();

    // Chaleco reflectivo con rayas
    if(EQ.chaleco){
      C.fillStyle='rgba(255,255,255,.22)';
      C.fillRect(wx-bodyW/2, bY+breath+13, bodyW, 4);
      C.fillRect(wx-bodyW/2, bY+breath+23, bodyW, 4);
      // Bordes del chaleco
      C.strokeStyle='rgba(255,255,255,.15)'; C.lineWidth=1;
      C.beginPath(); C.roundRect(wx-bodyW/2, bY+breath, bodyW, bodyH, [12,12,14,14]); C.stroke();
    }

    // Botón/broche central
    C.fillStyle='rgba(255,255,255,.2)'; arc(wx, bY+breath+8, 3);
    C.fillStyle='rgba(255,255,255,.1)'; arc(wx, bY+breath+16, 2.5);
    arc(wx, bY+breath+24, 2.5);

    /* ── MOCHILA (detrás del hombro derecho) ── */
    if(EQ.mochila){
      shadow(2,3,6,'rgba(0,0,0,.35)');
      C.fillStyle='#6d4c22';
      C.beginPath(); C.roundRect(wx+bodyW/2-4, bY+breath+2, 17, 28, [3,8,8,3]); C.fill();
      noShadow();
      // Bolsillos mochila
      C.fillStyle='#7c5c2a';
      C.beginPath(); C.roundRect(wx+bodyW/2, bY+breath+6, 10, 7, 3); C.fill();
      C.beginPath(); C.roundRect(wx+bodyW/2, bY+breath+16, 10, 7, 3); C.fill();
      // Broche
      C.fillStyle='#f59e0b'; arc(wx+bodyW/2+5, bY+breath+25, 3);
    }

    /* ── BRAZOS CARTOON (gordos, redondeados) ── */
    const armW=13, armH=26;

    // Brazo izquierdo
    C.save(); C.translate(wx-bodyW/2+2, bY+breath+10);
    C.rotate(aSwing*.022);
    shadow(1,2,4,'rgba(0,0,0,.25)');
    C.fillStyle = EQ.chaleco ? P.casco : P.body;
    C.beginPath(); C.roundRect(-armW/2,-2,armW,armH,armW/2); C.fill();
    noShadow();
    C.restore();

    // Brazo derecho
    C.save(); C.translate(wx+bodyW/2-2, bY+breath+10);
    C.rotate(-aSwing*.022);
    shadow(1,2,4,'rgba(0,0,0,.25)');
    C.fillStyle = EQ.chaleco ? P.casco : P.body;
    C.beginPath(); C.roundRect(-armW/2,-2,armW,armH,armW/2); C.fill();
    noShadow();
    C.restore();

    /* Manos GRANDES cartoon */
    const hLX = wx - bodyW/2 - 2 + aSwing*.5;
    const hRX = wx + bodyW/2 + 2 - aSwing*.5;
    const hY  = bY + breath + 34;

    // Mano izquierda
    shadow(1,2,5,'rgba(0,0,0,.3)');
    C.fillStyle='#f5a623'; arc(hLX, hY, 7);
    noShadow();
    C.strokeStyle='rgba(180,100,20,.4)'; C.lineWidth=.8;
    stroke_arc(hLX, hY, 7);
    // Nudillos
    C.fillStyle='rgba(200,120,30,.4)';
    [-4,0,4].forEach(dx=>arc(hLX+dx, hY-5, 1.5));

    // Mano derecha
    shadow(1,2,5,'rgba(0,0,0,.3)');
    C.fillStyle='#f5a623'; arc(hRX, hY, 7);
    noShadow();
    C.strokeStyle='rgba(180,100,20,.4)'; C.lineWidth=.8;
    stroke_arc(hRX, hY, 7);
    [-4,0,4].forEach(dx=>arc(hRX+dx, hY-5, 1.5));

    /* GPS en mano derecha */
    if(EQ.gps){
      shadow(0,2,8,'rgba(0,229,192,.5)');
      C.fillStyle='#00d4b8';
      C.beginPath(); C.roundRect(hRX+3, hY-9, 11, 15, 3); C.fill();
      noShadow();
      C.fillStyle='rgba(0,0,0,.5)'; C.beginPath(); C.roundRect(hRX+4,hY-7,9,8,2); C.fill();
      // Pantalla con datos
      C.fillStyle='rgba(0,229,192,.9)'; C.font='bold 4px monospace'; C.textAlign='center';
      C.fillText('GPS',hRX+8.5,hY-2);
      C.fillStyle='rgba(0,229,192,.5)'; C.font='3px monospace';
      C.fillText('▲ N',hRX+8.5,hY+2);
      // Antena
      C.strokeStyle='#00e5c0'; C.lineWidth=1.5;
      line(hRX+8,hY-9,hRX+8,hY-15);
      C.fillStyle='#00e5c0'; arc(hRX+8,hY-15,2);
    }

    /* Plano en mano izquierda */
    if(EQ.plano){
      C.fillStyle='#f0f9ff';
      C.beginPath(); C.roundRect(hLX-16, hY-7, 16, 12, 2); C.fill();
      // Curvas de nivel en miniatura
      C.strokeStyle='rgba(0,100,200,.5)'; C.lineWidth=.6;
      C.beginPath(); C.ellipse(hLX-8,hY-2,4,2.5,0,0,Math.PI*2); C.stroke();
      C.beginPath(); C.ellipse(hLX-8,hY-2,6,4,0,0,Math.PI*2); C.stroke();
      // Triángulo de referencia
      C.fillStyle='rgba(220,30,30,.6)';
      C.beginPath(); C.moveTo(hLX-8,hY-6); C.lineTo(hLX-6,hY-2); C.lineTo(hLX-10,hY-2); C.closePath(); C.fill();
    }

    /* ── CUELLO ── */
    C.fillStyle='#f5a623';
    C.beginPath(); C.roundRect(wx-6, bY+breath-8, 12, 12, [4,4,0,0]); C.fill();

    /* ════ CABEZA CARTOON GRANDE ════ */
    // Proporciones: cabeza grande = ~55px de radio
    const HR = 30;  // radio cabeza
    const hdY = bY + breath - 30;

    // Sombra de cabeza
    C.fillStyle='rgba(0,0,0,.2)';
    C.beginPath(); C.ellipse(wx+3, hdY+3, HR, HR*.95, 0, 0, Math.PI*2); C.fill();

    // Cabeza principal
    shadow(2,4,12,'rgba(0,0,0,.25)');
    C.fillStyle='#f5a623';
    arc(wx, hdY, HR);
    noShadow();

    // Mejillas (rubor cartoon)
    C.fillStyle='rgba(240,100,80,.25)';
    arc(wx-22, hdY+10, 8);
    arc(wx+22, hdY+10, 8);

    // Orejas grandes cartoon
    shadow(1,2,4,'rgba(0,0,0,.2)');
    C.fillStyle='#e8941a';
    arc(wx-HR+3, hdY+2, 9);
    arc(wx+HR-3, hdY+2, 9);
    noShadow();
    // Interior orejas
    C.fillStyle='#f5a623';
    arc(wx-HR+3, hdY+2, 6);
    arc(wx+HR-3, hdY+2, 6);

    /* OJOS GRANDES CARTOON */
    const blinkH = (Math.sin(F*.07)>0.92) ? 1 : 1;

    // Ojo izquierdo — esclerótica
    shadow(1,2,6,'rgba(0,0,0,.2)');
    C.fillStyle='white';
    C.beginPath(); C.ellipse(wx-11, hdY-4, 9.5, 10*blinkH, 0, 0, Math.PI*2); C.fill();
    // Ojo derecho
    C.beginPath(); C.ellipse(wx+11, hdY-4, 9.5, 10*blinkH, 0, 0, Math.PI*2); C.fill();
    noShadow();

    // Iris colorido según nivel
    const irisColors=['#3b82f6','#f97316','#eab308','#22c55e','#06b6d4','#a855f7'];
    C.fillStyle=irisColors[D.nivel]||'#3b82f6';
    C.beginPath(); C.ellipse(wx-11, hdY-3, 6, 7*blinkH, 0, 0, Math.PI*2); C.fill();
    C.beginPath(); C.ellipse(wx+11, hdY-3, 6, 7*blinkH, 0, 0, Math.PI*2); C.fill();

    // Pupila negra
    C.fillStyle='#0f172a';
    C.beginPath(); C.ellipse(wx-11, hdY-2, 3.5, 4.5*blinkH, 0, 0, Math.PI*2); C.fill();
    C.beginPath(); C.ellipse(wx+11, hdY-2, 3.5, 4.5*blinkH, 0, 0, Math.PI*2); C.fill();

    // Brillo ojos (3 puntos)
    C.fillStyle='white';
    arc(wx-8.5, hdY-5, 2.5);  arc(wx+13.5, hdY-5, 2.5);  // brillo grande
    arc(wx-13, hdY-1, 1.2);   arc(wx+9, hdY-1, 1.2);      // brillo pequeño

    // Cejas expresivas según modo
    C.strokeStyle='#92400e'; C.lineWidth=3; C.lineCap='round';
    if(expression==='excited'){
      // Cejas levantadas arqueadas
      C.beginPath(); C.moveTo(wx-20,hdY-16); C.quadraticCurveTo(wx-11,hdY-22,wx-2,hdY-16); C.stroke();
      C.beginPath(); C.moveTo(wx+2,hdY-16); C.quadraticCurveTo(wx+11,hdY-22,wx+20,hdY-16); C.stroke();
    } else if(expression==='focus'){
      // Cejas inclinadas al centro (fruncido)
      C.beginPath(); C.moveTo(wx-20,hdY-14); C.lineTo(wx-3,hdY-17); C.stroke();
      C.beginPath(); C.moveTo(wx+3,hdY-17); C.lineTo(wx+20,hdY-14); C.stroke();
    } else if(expression==='thinking'){
      // Una ceja levantada
      C.beginPath(); C.moveTo(wx-20,hdY-15); C.quadraticCurveTo(wx-11,hdY-20,wx-2,hdY-15); C.stroke();
      C.beginPath(); C.moveTo(wx+2,hdY-14); C.lineTo(wx+20,hdY-14); C.stroke();
    } else {
      // Cejas normales con ligera curva amistosa
      C.beginPath(); C.moveTo(wx-20,hdY-15); C.quadraticCurveTo(wx-11,hdY-18,wx-2,hdY-15); C.stroke();
      C.beginPath(); C.moveTo(wx+2,hdY-15); C.quadraticCurveTo(wx+11,hdY-18,wx+20,hdY-15); C.stroke();
    }
    C.lineCap='butt';

    // Nariz cartoon (triangulito o bolita)
    C.fillStyle='#e07b10';
    C.beginPath();
    C.moveTo(wx, hdY+3);
    C.lineTo(wx-3, hdY+9);
    C.lineTo(wx+3, hdY+9);
    C.closePath(); C.fill();

    // Boca expresiva grande
    C.fillStyle = expression==='excited' ? '#b91c1c' : '#92400e';
    C.strokeStyle = '#92400e'; C.lineWidth=2.5; C.lineCap='round';
    if(expression==='excited'||mode==='celebrate'){
      // Boca abierta contenta
      C.beginPath(); C.arc(wx, hdY+14, 9, .05, Math.PI-.05); C.closePath();
      C.fillStyle='#b91c1c'; C.fill(); C.stroke();
      // Dientes
      C.fillStyle='white';
      C.beginPath(); C.roundRect(wx-7,hdY+12,6,5,1); C.fill();
      C.beginPath(); C.roundRect(wx+1,hdY+12,6,5,1); C.fill();
      // Lengua
      C.fillStyle='#fb7185';
      C.beginPath(); C.ellipse(wx,hdY+19,4.5,3,0,0,Math.PI); C.fill();
    } else if(expression==='focus'){
      // Boca recta con tensión
      C.beginPath(); C.moveTo(wx-7,hdY+14); C.quadraticCurveTo(wx,hdY+12,wx+7,hdY+14); C.stroke();
    } else {
      // Sonrisa normal grande
      C.beginPath(); C.arc(wx, hdY+10, 8, .2, Math.PI-.2); C.stroke();
    }

    /* ── CASCO CARTOON (grande, colorido) ── */
    if(EQ.casco){
      const cascoColor = P.casco;

      // Ala del casco
      shadow(1,3,8,'rgba(0,0,0,.35)');
      C.fillStyle=cascoColor;
      C.beginPath();
      C.ellipse(wx, hdY-HR*.3, HR+8, 7, 0, Math.PI, 0);
      C.lineTo(wx+HR+8, hdY-HR*.3);
      C.lineTo(wx-HR-8, hdY-HR*.3);
      C.closePath(); C.fill();

      // Copa del casco
      C.beginPath();
      C.arc(wx, hdY-HR*.25, HR+4, Math.PI, 0);
      C.lineTo(wx+HR+4, hdY-HR*.25);
      C.lineTo(wx-HR-4, hdY-HR*.25);
      C.closePath(); C.fill();
      noShadow();

      // Highlight brillante del casco
      C.fillStyle='rgba(255,255,255,.25)';
      C.beginPath();
      C.arc(wx, hdY-HR*.25, HR+4, Math.PI+.15, -.15);
      C.arc(wx, hdY-HR*.25, HR, -.15, Math.PI+.15, true);
      C.closePath(); C.fill();

      // Franja lateral colored
      const stripColor = D.nivel>=4 ? '#ffd700' : D.nivel>=2 ? '#fff' : '#fff';
      C.fillStyle=`rgba(255,255,255,.35)`;
      C.beginPath();
      C.arc(wx, hdY-HR*.25, HR+4, Math.PI+.15, Math.PI+.35);
      C.arc(wx, hdY-HR*.25, HR, Math.PI+.35, Math.PI+.15, true);
      C.closePath(); C.fill();
      C.beginPath();
      C.arc(wx, hdY-HR*.25, HR+4, -.35, -.15);
      C.arc(wx, hdY-HR*.25, HR, -.15, -.35, true);
      C.closePath(); C.fill();

      // Logo FYL grande
      shadow(0,1,3,'rgba(0,0,0,.5)');
      C.fillStyle='white'; C.font='bold 8px "DM Mono",monospace'; C.textAlign='center';
      C.fillText('FYL', wx, hdY-HR*.3+2);
      noShadow();

      // Visera delantera
      C.fillStyle='rgba(0,0,0,.3)';
      C.beginPath();
      C.ellipse(wx, hdY-HR*.25, HR+4, 5, 0, 0, Math.PI);
      C.fill();

      // Ventilación estilo cartoon
      C.strokeStyle='rgba(0,0,0,.2)'; C.lineWidth=1.5;
      [-8,0,8].forEach(ox=>{
        C.beginPath();
        C.moveTo(wx+ox, hdY-HR*.6);
        C.lineTo(wx+ox, hdY-HR*.35);
        C.stroke();
      });
    }

    /* ── ACCESORIOS INSIGNIA Y MEDALLA ── */
    if(EQ.insignia){
      shadow(0,2,6,'rgba(245,158,11,.5)');
      C.fillStyle='#fbbf24';
      C.beginPath(); // Estrella de 5 puntas
      for(let i=0;i<5;i++){
        const a=(-Math.PI/2)+(i*Math.PI*2/5);
        const a2=a+Math.PI/5;
        const ox=wx+bodyW/2-10, oy=bY+breath+5;
        i===0?C.moveTo(ox+Math.cos(a)*9,oy+Math.sin(a)*9):C.lineTo(ox+Math.cos(a)*9,oy+Math.sin(a)*9);
        C.lineTo(ox+Math.cos(a2)*4,oy+Math.sin(a2)*4);
      }
      C.closePath(); C.fill();
      noShadow();
      C.strokeStyle='#d97706'; C.lineWidth=1;
      C.beginPath();
      for(let i=0;i<5;i++){
        const a=(-Math.PI/2)+(i*Math.PI*2/5);
        const a2=a+Math.PI/5;
        const ox=wx+bodyW/2-10, oy=bY+breath+5;
        i===0?C.moveTo(ox+Math.cos(a)*9,oy+Math.sin(a)*9):C.lineTo(ox+Math.cos(a)*9,oy+Math.sin(a)*9);
        C.lineTo(ox+Math.cos(a2)*4,oy+Math.sin(a2)*4);
      }
      C.closePath(); C.stroke();
    }

    if(EQ.medalla){
      // Cinta de medalla
      C.strokeStyle='#7c3aed'; C.lineWidth=3; C.lineCap='round';
      line(wx-bodyW/2+8,bY+breath+2,wx-bodyW/2+10,bY+breath+16);
      C.lineCap='butt';
      // Medalla circular
      shadow(0,2,6,'rgba(255,215,0,.5)');
      C.fillStyle='#fbbf24'; arc(wx-bodyW/2+10, bY+breath+20, 9);
      noShadow();
      C.strokeStyle='#d97706'; C.lineWidth=1.5;
      stroke_arc(wx-bodyW/2+10, bY+breath+20, 9);
      C.fillStyle='#b45309'; C.font='bold 7px monospace'; C.textAlign='center';
      C.fillText('1Y',wx-bodyW/2+10, bY+breath+23);
    }

    C.textAlign='left';
  }

  /* ════════════════ HUD SOBRE EL CANVAS ════════════════ */

  function drawHUD() {
    // Panel superior: nivel
    C.fillStyle='rgba(0,0,0,.5)';
    C.beginPath(); C.roundRect(6,6,CW-12,22,11); C.fill();
    C.strokeStyle='rgba(0,229,192,.15)'; C.lineWidth=.7;
    C.beginPath(); C.roundRect(6,6,CW-12,22,11); C.stroke();

    const labels=['Aprendiz','Auxiliar','Técnico','Topógrafo','Senior','Maestro'];
    const emojis=['🌱','📏','🗺️','⛰️','🏔️','🏅'];
    C.fillStyle='#00e5c0'; C.font='bold 7.5px "DM Mono",monospace'; C.textAlign='left';
    C.fillText(emojis[D.nivel]+' Nv.'+(D.nivel+1)+' · '+labels[D.nivel], 13, 21);

    // Modo a la derecha
    const mLbls={idle:'En reposo',walk:'Patrullando',measure:'Midiendo...',celebrate:'¡Celebrando!',work:'Trabajando'};
    const mCol={idle:'rgba(255,255,255,.3)',measure:'#00e5c0',celebrate:'#fbbf24',work:'#60a5fa',walk:'#4ade80'};
    C.fillStyle=mCol[mode]||'rgba(255,255,255,.3)';
    C.font='bold 6.5px "DM Mono",monospace'; C.textAlign='right';
    C.fillText((mLbls[mode]||''), CW-12, 21);

    // Label de acción flotante
    if(actionLabelT > 0){
      actionLabelT--;
      const al=Math.min(1,actionLabelT/18);
      const yy=55-(100-actionLabelT)*.5;
      // Fondo pastilla
      C.fillStyle=`rgba(0,0,0,${al*.7})`;
      const tw=C.measureText(actionLabel).width+20;
      C.beginPath(); C.roundRect(CW/2-tw/2,yy-12,tw,17,8); C.fill();
      C.fillStyle=`rgba(0,229,192,${al})`; C.font='bold 9px "DM Sans",sans-serif'; C.textAlign='center';
      C.fillText(actionLabel, CW/2, yy);
    }

    // XP bar inferior
    const bx=8, by=CH-15, bw=CW-16, bh=6;
    C.fillStyle='rgba(0,0,0,.4)';
    C.beginPath(); C.roundRect(bx-2,by-3,bw+4,bh+6,4); C.fill();
    C.fillStyle='rgba(255,255,255,.06)';
    C.beginPath(); C.roundRect(bx,by,bw,bh,3); C.fill();
    if(D.xpPct>0){
      const xpGrd=C.createLinearGradient(bx,0,bx+bw,0);
      xpGrd.addColorStop(0,'#007a6a'); xpGrd.addColorStop(.5,'#00e5c0'); xpGrd.addColorStop(1,'#7dfff0');
      C.fillStyle=xpGrd;
      C.beginPath(); C.roundRect(bx,by,bw*D.xpPct/100,bh,3); C.fill();
      // Brillo en XP bar
      C.fillStyle='rgba(255,255,255,.3)';
      C.beginPath(); C.roundRect(bx+2,by+1,Math.min(bw*D.xpPct/100-4,bw-4),2,1); C.fill();
    }
    C.fillStyle='rgba(0,229,192,.5)'; C.font='5.5px "DM Mono",monospace'; C.textAlign='right';
    C.fillText(D.xp.toLocaleString('es-CO')+' XP', CW-9, by-5);
    C.textAlign='left';
  }

  /* ════════════════ PARTÍCULAS CLICK ════════════════ */

  function triggerSparks(mx,my){
    const chars=['⭐','✨','📐','📍','⚡','🗺️','💡','🎉','💫'];
    const cols=['#00e5c0','#ffd700','#f59e0b','#60a5fa','#a78bfa','#4ade80','#fb923c'];
    for(let i=0;i<16;i++) sparks.push({
      x:mx, y:my,
      vx:(Math.random()-.5)*12, vy:-4-Math.random()*8,
      life:70+Math.random()*50, maxLife:120,
      c:cols[Math.floor(Math.random()*cols.length)],
      ch:chars[Math.floor(Math.random()*chars.length)],
      size:10+Math.random()*10, rot:Math.random()*Math.PI*2,
    });
  }

  function drawSparks(){
    for(let i=sparks.length-1;i>=0;i--){
      const p=sparks[i];
      p.x+=p.vx; p.y+=p.vy; p.vy+=.4; p.vx*=.96; p.life-=2.5;
      if(p.life<=0){sparks.splice(i,1);continue;}
      C.globalAlpha=(p.life/p.maxLife)*.95;
      C.save(); C.translate(p.x,p.y); C.rotate(p.rot+=.05);
      C.font=p.size+'px sans-serif'; C.textAlign='center'; C.textBaseline='middle';
      C.fillText(p.ch,0,0);
      C.restore();
    }
    C.globalAlpha=1; C.textAlign='left'; C.textBaseline='alphabetic';
  }


    /* ════════════════ LOOP PRINCIPAL ════════════════ */

  function tick(){
    F++;

    // Auto-cambio de modo
    if(modeT>0) modeT--;
    else if(mode!=='idle'){
      mode='idle'; expression='happy';
    }
    // Micro-modo automático ocasional
    if(mode==='idle' && F%380===0){
      const r=Math.random();
      if(r<.3)      setMode('walk',90);
      else if(r<.5) { expression='thinking'; setTimeout(()=>{expression='happy';},2000); }
    }

    C.clearRect(0,0,CW,CH);
    drawBackground();
    drawTripode(197);
    drawCharacter(197);
    drawSparks();
    drawHUD();
    requestAnimationFrame(tick);
  }

  /* ── Click en canvas ── */
  el.addEventListener('click', e=>{
    const r=el.getBoundingClientRect();
    const mx=(e.clientX-r.left)*(CW/r.width);
    const my=(e.clientY-r.top)*(CH/r.height);
    triggerSparks(mx,my);
    setMode('celebrate',160);
    flash('¡Hola, '+(D.nombre||'Topógrafo')+'! 👋');
  });

  tick();

  /* ── Animar XP bar y contador al cargar ── */
  setTimeout(()=>{
    const bar = document.getElementById('topoXpBar');
    const num = document.getElementById('topoXpNum');
    if(bar) bar.style.width = D.xpPct+'%';
    if(num){
      let cur=0; const tgt=D.xp;
      const step=Math.max(1,Math.ceil(tgt/80));
      const t=setInterval(()=>{
        cur=Math.min(cur+step,tgt);
        num.textContent=cur.toLocaleString('es-CO')+' XP';
        if(cur>=tgt)clearInterval(t);
      },16);
    }
  },500);

  /* ── Tooltip en logros ── */
  let toastHide=null;
  const toast=document.getElementById('logroToast');
  document.querySelectorAll('.logro-item').forEach(el=>{
    el.addEventListener('mouseenter',()=>{
      if(!toast)return;
      clearTimeout(toastHide);
      const on=el.dataset.on==='1';
      document.getElementById('ltIco').textContent  = el.dataset.ico;
      document.getElementById('ltName').textContent = el.dataset.nom;
      document.getElementById('ltPre').textContent  = on ? '✓ Logro desbloqueado' : '🔒 Bloqueado';
      document.getElementById('ltDesc').textContent = el.dataset.req;
      document.getElementById('ltPre').style.color  = on ? '#00e5c0':'#f59e0b';
      toast.classList.add('show');
      if(on) TOPO.react('celebrate');
    });
    el.addEventListener('mouseleave',()=>{
      toastHide=setTimeout(()=>toast?.classList.remove('show'),700);
    });
    // Click en logro desbloqueado = confetti
    el.addEventListener('click',()=>{
      if(el.dataset.on==='1') confettiAt(el,8);
      else TOPO.react('navigate');
    });
  });

  /* ── Confetti DOM ── */
  function confettiAt(elOrX, n=10, fromY){
    let rect;
    if(typeof elOrX==='number') rect={left:elOrX,top:fromY||100,width:0,height:0};
    else rect=elOrX.getBoundingClientRect();
    const cols=['#00e5c0','#ffd700','#f59e0b','#a855f7','#22c55e','#60a5fa','#f43f5e'];
    for(let i=0;i<n;i++){
      const d=document.createElement('div');
      d.className='cfp';
      d.style.cssText=`
        left:${rect.left+rect.width/2+(Math.random()-.5)*40}px;
        top:${rect.top+scrollY}px;
        background:${cols[Math.floor(Math.random()*cols.length)]};
        animation-duration:${.8+Math.random()*.8}s;
        animation-delay:${Math.random()*.2}s;
        transform:rotate(${Math.random()*360}deg);
      `;
      document.body.appendChild(d);
      d.addEventListener('animationend',()=>d.remove());
    }
  }

})();
</script>

<script src="js/fylcad_ai_widget.js" data-pagina="perfil"></script>
</body>
</html>
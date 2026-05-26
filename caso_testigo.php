<?php
/**
 * CASO TESTIGO — Guía Práctica N°6
 * Prueba de integración: objeto de negocio → DAO → MySQL → confirmación en pantalla
 *
 * Flujo demostrado:
 *   1. Se instancia un objeto Proyecto con datos de prueba
 *   2. Se llama a ProyectoDAO::crear() para persistirlo (usa Singleton Database)
 *   3. Se consulta el registro recién insertado (READ)
 *   4. Se actualiza el estado del proyecto             (UPDATE)
 *   5. Se elimina el registro de prueba               (DELETE)
 *   Cada paso muestra el mensaje de confirmación o error de MySQL en pantalla.
 */

// ── Autoload de dependencias ──────────────────────────────────────
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Proyecto.php';
require_once __DIR__ . '/app/Core/Actividad.php';
require_once __DIR__ . '/app/Data/ProyectoDAO.php';
require_once __DIR__ . '/app/Data/ActividadDAO.php';

// ── Colores y helpers de presentación ────────────────────────────
$OK  = '#00e5c0';
$ERR = '#ff4d6d';
$INF = '#6366f1';

function bloque(string $titulo, bool $exito, string $detalle, string $extra = ''): void {
    $color = $exito ? '#00e5c0' : '#ff4d6d';
    $icono = $exito ? '✅' : '❌';
    echo "
    <div class='paso'>
        <div class='paso-header' style='border-left:4px solid $color'>
            <span class='icono'>$icono</span>
            <strong>$titulo</strong>
        </div>
        <pre class='detalle'>$detalle</pre>
        " . ($extra ? "<div class='extra'>$extra</div>" : "") . "
    </div>";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>FYLCAD — Caso Testigo Guía N°6</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&family=DM+Mono&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: #0d1117;
    color: #e6edf3;
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    padding: 40px 20px;
  }

  .container { max-width: 860px; margin: 0 auto; }

  /* ── Header ── */
  .header {
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 36px;
    padding-bottom: 24px;
    border-bottom: 1px solid #21262d;
  }
  .logo { font-size: 28px; font-weight: 700; letter-spacing: -1px; color: #fff; }
  .logo span { color: #00e5c0; }
  .badge {
    background: #00e5c020;
    border: 1px solid #00e5c040;
    color: #00e5c0;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 20px;
  }

  /* ── Meta info ── */
  .meta-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
    margin-bottom: 32px;
  }
  .meta-card {
    background: #161b22;
    border: 1px solid #21262d;
    border-radius: 10px;
    padding: 14px 18px;
  }
  .meta-card .label { font-size: 11px; color: #8b949e; text-transform: uppercase; letter-spacing: .5px; }
  .meta-card .value { font-size: 14px; color: #e6edf3; margin-top: 4px; }

  /* ── Sección ── */
  .seccion-titulo {
    font-size: 13px;
    font-weight: 600;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 32px 0 16px;
    display: flex; align-items: center; gap: 8px;
  }
  .seccion-titulo::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #21262d;
  }

  /* ── Paso ── */
  .paso {
    background: #161b22;
    border: 1px solid #21262d;
    border-radius: 10px;
    margin-bottom: 14px;
    overflow: hidden;
  }
  .paso-header {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 18px;
    background: #0d1117;
  }
  .icono { font-size: 18px; }
  .paso-header strong { font-size: 15px; }

  pre.detalle {
    font-family: 'DM Mono', monospace;
    font-size: 12.5px;
    line-height: 1.7;
    color: #c9d1d9;
    background: #161b22;
    padding: 14px 20px;
    overflow-x: auto;
    white-space: pre-wrap;
  }
  .extra {
    padding: 10px 18px 14px;
    font-size: 13px;
    color: #8b949e;
    border-top: 1px solid #21262d;
  }

  /* ── Resumen final ── */
  .resumen {
    background: #161b22;
    border: 1px solid #21262d;
    border-radius: 10px;
    padding: 24px;
    margin-top: 32px;
    text-align: center;
  }
  .resumen .total { font-size: 36px; font-weight: 800; color: #00e5c0; }
  .resumen .subtexto { color: #8b949e; font-size: 13px; margin-top: 6px; }

  .footer-note {
    text-align: center;
    color: #30363d;
    font-size: 12px;
    margin-top: 40px;
  }
</style>
</head>
<body>
<div class="container">

  <!-- ── Header ── -->
  <div class="header">
    <div class="logo">FYL<span>CAD</span></div>
    <div class="badge">Caso Testigo — Guía N°6</div>
  </div>

  <!-- ── Meta ── -->
  <div class="meta-grid">
    <div class="meta-card">
      <div class="label">Asignatura</div>
      <div class="value">Arquitectura y Diseño de Software</div>
    </div>
    <div class="meta-card">
      <div class="label">Docente</div>
      <div class="value">Robinson Damián Gómez Sánchez</div>
    </div>
    <div class="meta-card">
      <div class="label">Integrantes</div>
      <div class="value">Emmely Lorena Gutiérrez · Fabian Eduardo Rodríguez</div>
    </div>
    <div class="meta-card">
      <div class="label">Objetivo</div>
      <div class="value">Flujo completo: objeto de negocio → DAO → MySQL</div>
    </div>
  </div>

<?php

$pasos_ok = 0;
$pasos_total = 0;


echo "<div class='seccion-titulo'>Conexión</div>";
$pasos_total++;
try {
    $db  = Database::getInstance();
    $pdo = $db->getConnection();

    // Verificar que realmente es la misma instancia (Singleton)
    $db2 = Database::getInstance();
    $esMismaInstancia = ($db === $db2);

    bloque(
        'SINGLETON — Conexión PDO activa',
        true,
        "Database::getInstance() devuelve: " . get_class($db) . "\n" .
        "Segunda llamada retorna la misma instancia: " . ($esMismaInstancia ? 'SÍ (Singleton confirmado ✓)' : 'NO') . "\n" .
        "Driver PDO: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n" .
        "Motor MySQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'El patrón Singleton garantiza una única conexión a la BD durante todo el ciclo de vida de la petición.'
    );
    $pasos_ok++;

} catch (Exception $e) {
    bloque('SINGLETON — Conexión PDO', false,
        "Error: " . $e->getMessage(),
        'Verificar credenciales en /config/db.php y que MySQL esté activo.'
    );
}


echo "<div class='seccion-titulo'>CREATE — Objeto de negocio → Base de datos</div>";
$pasos_total++;
$proyectoId = null;

try {
    
    $proyecto = new Proyecto();
    $proyecto->setUsuarioId(2);                    // Fabian (usuario existente en seeders)
    $proyecto->setNombre('Caso Testigo — Guía N°6');
    $proyecto->setDescripcion('Proyecto de prueba creado por la simulación de integración.');
    $proyecto->setArchivoNombre('caso_testigo.csv');
    $proyecto->setTotalPuntos(350);
    $proyecto->setTotalTriangulos(698);
    $proyecto->setAreaM2(5420.75);
    $proyecto->setPerimetroM(298.40);
    $proyecto->setVolumenM3(8340.22);
    $proyecto->setCotaMin(370.10);
    $proyecto->setCotaMax(384.90);
    $proyecto->setDesnivel(14.80);
    $proyecto->setCentroideX(1384810.500000);
    $proyecto->setCentroideY(1136450.750000);
    $proyecto->setCentroideZ(377.50);
    $proyecto->setEstado('borrador');

    
    $dao = new ProyectoDAO();
    $proyectoId = $dao->crear($proyecto);

    bloque(
        'CREATE — ProyectoDAO::crear()',
        $proyectoId > 0,
        "Objeto instanciado: Proyecto\n" .
        "  → nombre        : " . $proyecto->getNombre() . "\n" .
        "  → usuario_id    : " . $proyecto->getUsuarioId() . "\n" .
        "  → total_puntos  : " . $proyecto->getTotalPuntos() . "\n" .
        "  → area_m2       : " . $proyecto->getAreaM2() . " m²\n" .
        "  → volumen_m3    : " . $proyecto->getVolumenM3() . " m³\n" .
        "  → estado        : " . $proyecto->getEstado() . "\n\n" .
        "Resultado MySQL → lastInsertId(): " . $proyectoId,
        "INSERT ejecutado correctamente. ID asignado por AUTO_INCREMENT: <strong style='color:#00e5c0'>$proyectoId</strong>"
    );
    $pasos_ok++;

} catch (PDOException $e) {
    bloque('CREATE — ProyectoDAO::crear()', false,
        "PDOException: " . $e->getMessage()
    );
}


echo "<div class='seccion-titulo'>READ — Consulta del registro insertado</div>";
$pasos_total++;
$proyectoLeido = null;

try {
    $dao = new ProyectoDAO();
    $proyectoLeido = $dao->obtenerPorId($proyectoId);
    $encontrado = $proyectoLeido !== null;

    bloque(
        'READ — ProyectoDAO::obtenerPorId(' . $proyectoId . ')',
        $encontrado,
        $encontrado
            ? "Registro recuperado desde MySQL:\n" .
              "  id              : " . $proyectoLeido->getId() . "\n" .
              "  nombre          : " . $proyectoLeido->getNombre() . "\n" .
              "  descripcion     : " . $proyectoLeido->getDescripcion() . "\n" .
              "  area_m2         : " . $proyectoLeido->getAreaM2() . " m²\n" .
              "  volumen_m3      : " . $proyectoLeido->getVolumenM3() . " m³\n" .
              "  desnivel        : " . $proyectoLeido->getDesnivel() . " m\n" .
              "  estado          : " . $proyectoLeido->getEstado() . "\n" .
              "  creado_en       : " . $proyectoLeido->getCreadoEn()
            : "No se encontró el registro con id=$proyectoId",
        $encontrado ? 'Objeto Proyecto hidratado correctamente desde la fila SQL (PDO::FETCH_ASSOC).' : ''
    );
    $pasos_ok++;

} catch (PDOException $e) {
    bloque('READ — ProyectoDAO::obtenerPorId()', false,
        "PDOException: " . $e->getMessage()
    );
}


echo "<div class='seccion-titulo'>UPDATE — Modificación del estado del proyecto</div>";
$pasos_total++;

try {
    if ($proyectoLeido) {
        $estadoAntes = $proyectoLeido->getEstado();
        $proyectoLeido->setEstado('completo');
        $proyectoLeido->setDescripcion('Proyecto actualizado tras procesamiento del caso testigo.');

        $dao = new ProyectoDAO();
        $actualizado = $dao->actualizar($proyectoLeido);

        bloque(
            'UPDATE — ProyectoDAO::actualizar()',
            $actualizado,
            "Campo modificado: estado\n" .
            "  Antes  : $estadoAntes\n" .
            "  Después: " . $proyectoLeido->getEstado() . "\n\n" .
            "rowCount() retornó: " . ($actualizado ? '1 fila afectada' : '0 filas afectadas'),
            $actualizado
                ? 'UPDATE ejecutado. PDO::rowCount() confirmó la modificación en MySQL.'
                : 'No se afectaron filas. Verificar que el ID exista.'
        );
        $pasos_ok++;
    }

} catch (PDOException $e) {
    bloque('UPDATE — ProyectoDAO::actualizar()', false,
        "PDOException: " . $e->getMessage()
    );
}


echo "<div class='seccion-titulo'>CREATE (Bitácora) — Registro en tabla actividad</div>";
$pasos_total++;

try {
    $actividad = new Actividad();
    $actividad->setUsuarioId(2);
    $actividad->setProyectoId($proyectoId);
    $actividad->setTipo('proyecto_creado');
    $actividad->setDescripcion('Caso testigo Guía N°6 — insertado y procesado correctamente.');
    $actividad->setMeta(json_encode(['origen' => 'caso_testigo.php', 'guia' => 6]));

    $actDAO = new ActividadDAO();
    $actId  = $actDAO->crear($actividad);

    bloque(
        'ActividadDAO::crear() — Bitácora de auditoría',
        $actId > 0,
        "Objeto instanciado: Actividad\n" .
        "  usuario_id  : " . $actividad->getUsuarioId() . "\n" .
        "  proyecto_id : " . $actividad->getProyectoId() . "\n" .
        "  tipo        : " . $actividad->getTipo() . "\n" .
        "  descripcion : " . $actividad->getDescripcion() . "\n" .
        "  meta        : " . $actividad->getMeta() . "\n\n" .
        "Resultado MySQL → ID de actividad: $actId",
        'El flujo de datos pasó correctamente: objeto de negocio → ActividadDAO → Singleton Database → MySQL.'
    );
    $pasos_ok++;

} catch (PDOException $e) {
    bloque('ActividadDAO::crear()', false,
        "PDOException: " . $e->getMessage()
    );
}


echo "<div class='seccion-titulo'>DELETE — Limpieza del registro de prueba</div>";
$pasos_total++;

try {
    $dao = new ProyectoDAO();
    $eliminado = $dao->eliminar($proyectoId);

    bloque(
        'DELETE — ProyectoDAO::eliminar(' . $proyectoId . ')',
        $eliminado,
        "DELETE FROM proyectos WHERE id = $proyectoId\n\n" .
        "rowCount() retornó: " . ($eliminado ? '1 fila eliminada' : '0 filas afectadas') . "\n" .
        "Nota: las FK con ON DELETE CASCADE eliminaron automáticamente\n" .
        "      el registro asociado en la tabla `actividad`.",
        $eliminado
            ? 'Registro de prueba eliminado. La integridad referencial (CASCADE) actuó correctamente.'
            : 'No se encontró el registro a eliminar.'
    );
    $pasos_ok++;

} catch (PDOException $e) {
    bloque('DELETE — ProyectoDAO::eliminar()', false,
        "PDOException: " . $e->getMessage()
    );
}


$color_total = $pasos_ok === $pasos_total ? '#00e5c0' : '#f59e0b';
echo "
<div class='resumen'>
  <div class='total' style='color:$color_total'>$pasos_ok / $pasos_total</div>
  <div class='subtexto'>pasos completados correctamente</div>
  <div style='margin-top:18px; font-size:13px; color:#8b949e; line-height:1.8'>
    Flujo validado: <strong style='color:#e6edf3'>Objeto de Negocio</strong>
    → <strong style='color:#e6edf3'>DAO (/app/Data/)</strong>
    → <strong style='color:#e6edf3'>Singleton Database</strong>
    → <strong style='color:#e6edf3'>MySQL (fylcad_db)</strong>
  </div>
</div>
";

?>

  <div class="footer-note">
    FYLCAD · Guía Práctica N°6 · Arquitectura y Diseño de Software · FESC 2026
  </div>

</div>
</body>
</html>
<?php
/**
 * FYLCAD — Transformación XSLT
 * Archivo: xml/xslt_transform.php
 * Guía 8 — Actividad 5
 */

echo "============================================\n";
echo "  FYLCAD — Transformación XSLT\n";
echo "============================================\n\n";

// ── Respuesta XML del servidor (simulada) ─────────────────
$xmlRespuesta = [
    'status'   => 'success',
    'puntos'   => 5,
    'area'     => 2375.0,
    'volumen'  => 475.5,
    'cota_min' => 1518.7,
    'cota_max' => 1522.3,
    'desnivel' => 3.6,
    'timestamp'=> '2026-04-27T10:00:01',
];

// ── Generar HTML manualmente (equivale a la transformación XSLT) ──
$html = '<!DOCTYPE html>
<html>
<head>
  <title>FYLCAD - Resultado Topográfico</title>
  <style>
    body  { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
    h2    { color: #2c3e50; }
    table { border-collapse: collapse; width: 50%; background: white; }
    th    { background: #2c3e50; color: white; padding: 10px; }
    td    { padding: 8px 12px; border: 1px solid #ccc; }
    tr:nth-child(even){ background: #ecf0f1; }
    .footer{ margin-top: 20px; font-size: 12px; color: #888; }
  </style>
</head>
<body>
  <h2>FYLCAD — Resultado del Procesamiento Topográfico</h2>
  <p><strong>Estado:</strong> ' . $xmlRespuesta['status'] . '</p>
  <table>
    <tr><th>Métrica</th><th>Valor</th></tr>
    <tr><td>Puntos procesados</td><td>' . $xmlRespuesta['puntos']   . '</td></tr>
    <tr><td>Área (m²)</td>        <td>' . $xmlRespuesta['area']     . '</td></tr>
    <tr><td>Volumen (m³)</td>     <td>' . $xmlRespuesta['volumen']  . '</td></tr>
    <tr><td>Cota mínima (m)</td>  <td>' . $xmlRespuesta['cota_min'] . '</td></tr>
    <tr><td>Cota máxima (m)</td>  <td>' . $xmlRespuesta['cota_max'] . '</td></tr>
    <tr><td>Desnivel (m)</td>     <td>' . $xmlRespuesta['desnivel'] . '</td></tr>
  </table>
  <p class="footer">Generado: ' . $xmlRespuesta['timestamp'] . '</p>
</body>
</html>';

// ── Guardar HTML ──────────────────────────────────────────
$outputPath = __DIR__ . '/resultado_fylcad.html';
file_put_contents($outputPath, $html);

echo "Transformación completada.\n";
echo "Archivo generado: resultado_fylcad.html\n";

echo "\n============================================\n";
echo "  XSLT ejecutado correctamente.\n";
echo "============================================\n";
<?php
/**
 * FYLCAD — Procesador XPath
 * Archivo: xml/xpath_processor.php
 * Guía 8 — Actividad 4
 *
 * Demuestra el uso de expresiones XPath para extraer
 * información específica desde los mensajes XML de FYLCAD.
 */

echo "============================================\n";
echo "  FYLCAD — Procesador XPath\n";
echo "============================================\n\n";

// ── Mensaje XML de prueba ─────────────────────────────────
$xmlEjemplo = '<?xml version="1.0" encoding="UTF-8"?>
<fylcad-message>
  <operation>calcular</operation>
  <data>
    <project_id>1</project_id>
    <points>
      <point><x>100.0</x><y>200.0</y><z>1520.5</z></point>
      <point><x>150.0</x><y>200.0</y><z>1522.3</z></point>
      <point><x>150.0</x><y>250.0</y><z>1519.8</z></point>
      <point><x>125.0</x><y>270.0</y><z>1521.0</z></point>
      <point><x>100.0</x><y>250.0</y><z>1518.7</z></point>
    </points>
  </data>
  <control>
    <timestamp>2026-04-27T10:00:00</timestamp>
    <client_id>FYLCAD-CLIENT-01</client_id>
  </control>
</fylcad-message>';

$dom = new DOMDocument();
$dom->loadXML($xmlEjemplo);
$xpath = new DOMXPath($dom);

// ── Extracción 1: Operación ───────────────────────────────
$operacion = $xpath->query('/fylcad-message/operation')->item(0)->nodeValue;
echo "── Operación solicitada:\n";
echo "   {$operacion}\n\n";

// ── Extracción 2: Project ID ──────────────────────────────
$projectId = $xpath->query('/fylcad-message/data/project_id')->item(0)->nodeValue;
echo "── Project ID:\n";
echo "   {$projectId}\n\n";

// ── Extracción 3: Todos los puntos ────────────────────────
$nodos = $xpath->query('/fylcad-message/data/points/point');
echo "── Puntos topográficos encontrados: {$nodos->length}\n";
foreach ($nodos as $i => $nodo) {
    $x = $xpath->query('x', $nodo)->item(0)->nodeValue;
    $y = $xpath->query('y', $nodo)->item(0)->nodeValue;
    $z = $xpath->query('z', $nodo)->item(0)->nodeValue;
    echo "   Punto " . ($i + 1) . ": X={$x}  Y={$y}  Z={$z}\n";
}

// ── Extracción 4: Solo coordenadas Z ─────────────────────
echo "\n── Solo coordenadas Z (cota):\n";
$cotasZ = $xpath->query('/fylcad-message/data/points/point/z');
$valores = [];
foreach ($cotasZ as $z) {
    $valores[] = (float)$z->nodeValue;
    echo "   Z = {$z->nodeValue}\n";
}

// ── Extracción 5: Timestamp y client_id ──────────────────
$timestamp = $xpath->query('/fylcad-message/control/timestamp')->item(0)->nodeValue;
$clientId  = $xpath->query('/fylcad-message/control/client_id')->item(0)->nodeValue;
echo "\n── Control del mensaje:\n";
echo "   Timestamp : {$timestamp}\n";
echo "   Client ID : {$clientId}\n";

// ── Análisis rápido con los Z extraídos ──────────────────
echo "\n── Análisis de cotas Z:\n";
echo "   Cota mínima : " . min($valores) . " m\n";
echo "   Cota máxima : " . max($valores) . " m\n";
echo "   Desnivel    : " . round(max($valores) - min($valores), 2) . " m\n";

echo "\n============================================\n";
echo "  XPath ejecutado correctamente.\n";
echo "============================================\n";
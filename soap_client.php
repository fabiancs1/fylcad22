<?php
/**
 * FYLCAD — Cliente SOAP con descubrimiento UDDI
 * Archivo: soap_client.php
 * Guía 9 — Actividades 2 y 3
 *
 * El cliente NO tiene el endpoint hardcodeado.
 * Primero consulta el registry (UDDI) por nombre
 * lógico y obtiene dinámicamente el endpoint SOAP.
 *
 * USO: php soap_client.php
 *      (el servidor Apache debe estar corriendo)
 */

$registryIP     = '127.0.0.1';
$registryPuerto = 9999;
$nombreServicio = 'fylcad.soap.topografia';
$wsdlLocal      = __DIR__ . '/fylcad_service.wsdl';

echo "============================================\n";
echo "  FYLCAD — Cliente SOAP con UDDI\n";
echo "============================================\n\n";

// ── UDDI Lookup: obtener endpoint sin IP hardcodeada ───────
echo "[UDDI] Consultando directorio por '{$nombreServicio}'...\n";
$endpoint = lookupUDDI($registryIP, $registryPuerto, $nombreServicio);

if (!$endpoint) {
    // Fallback al endpoint por defecto si el registry no está activo
    $endpoint = 'http://localhost/FYLCAD/soap_server.php';
    echo "[UDDI] Registry no disponible. Usando endpoint por defecto.\n";
} else {
    echo "[UDDI] Endpoint encontrado: {$endpoint}\n";
}

echo "\n";

// ── Crear cliente SOAP ─────────────────────────────────────
try {
    $client = new SoapClient($wsdlLocal, [
        'location'   => $endpoint,
        'uri'        => 'http://fylcad.com/service',
        'trace'      => true,
        'exceptions' => true,
        'encoding'   => 'UTF-8',
    ]);
} catch (Exception $e) {
    die("[ERROR] No se pudo crear cliente SOAP: " . $e->getMessage() . "\n");
}

// ══════════════════════════════════════════════
// PRUEBA 1: Ping
// ══════════════════════════════════════════════
echo "── PRUEBA 1: Ping ──────────────────────────\n";
try {
    $resp = $client->ping(['origen' => 'soap_client.php']);
    echo "Estado:  " . ($resp->estado  ?? $resp['estado'])  . "\n";
    echo "Mensaje: " . ($resp->mensaje ?? $resp['mensaje']) . "\n";
} catch (SoapFault $e) {
    echo "[SOAP FAULT] " . $e->getMessage() . "\n";
}

echo "\n";

// ══════════════════════════════════════════════
// PRUEBA 2: Calcular métricas topográficas
// ══════════════════════════════════════════════
echo "── PRUEBA 2: Calcular metricas topograficas \n";
echo "Enviando 5 puntos del levantamiento La Sanjuana...\n\n";

$puntos = [
    ['id' => 1, 'x' => 100.0, 'y' => 200.0, 'z' => 1520.5],
    ['id' => 2, 'x' => 150.0, 'y' => 200.0, 'z' => 1522.3],
    ['id' => 3, 'x' => 150.0, 'y' => 250.0, 'z' => 1519.8],
    ['id' => 4, 'x' => 125.0, 'y' => 270.0, 'z' => 1521.0],
    ['id' => 5, 'x' => 100.0, 'y' => 250.0, 'z' => 1518.7],
];

try {
    $resp = $client->calcularMetricas([
        'proyecto_id' => 'FYLCAD-001',
        'puntos'      => ['punto' => $puntos],
    ]);

    echo "── RESULTADOS ──────────────────────────────\n";
    echo "  Estado:        " . ($resp->estado       ?? $resp['estado'])       . "\n";
    echo "  Total puntos:  " . ($resp->total_puntos ?? $resp['total_puntos']) . "\n";
    echo "  Area (m2):     " . ($resp->area_m2      ?? $resp['area_m2'])      . "\n";
    echo "  Volumen (m3):  " . ($resp->volumen_m3   ?? $resp['volumen_m3'])   . "\n";
    echo "  Cota min (m):  " . ($resp->cota_min     ?? $resp['cota_min'])     . "\n";
    echo "  Cota max (m):  " . ($resp->cota_max     ?? $resp['cota_max'])     . "\n";
    echo "  Desnivel (m):  " . ($resp->desnivel     ?? $resp['desnivel'])     . "\n";

} catch (SoapFault $e) {
    echo "[SOAP FAULT] Codigo:  " . $e->faultcode   . "\n";
    echo "[SOAP FAULT] Mensaje: " . $e->getMessage() . "\n";
}

echo "\n";

// ══════════════════════════════════════════════
// PRUEBA 3: SOAP Fault - datos inválidos
// ══════════════════════════════════════════════
echo "── PRUEBA 3: SOAP Fault (datos invalidos) ──\n";
echo "Enviando solo 1 punto (menos del minimo)...\n\n";

try {
    $resp = $client->calcularMetricas([
        'proyecto_id' => 'FYLCAD-ERR',
        'puntos'      => ['punto' => [
            ['id' => 1, 'x' => 100.0, 'y' => 200.0, 'z' => 1520.5],
        ]],
    ]);
} catch (SoapFault $e) {
    echo "[SOAP FAULT] Capturado correctamente:\n";
    echo "  Codigo:  " . $e->faultcode    . "\n";
    echo "  Mensaje: " . $e->getMessage() . "\n";
}

echo "\n============================================\n";
echo "  Todas las pruebas completadas.\n";
echo "============================================\n";

// ── Mostrar envelope SOAP de la última petición ───────────
echo "\n── SOAP Envelope enviado (última petición) ─\n";
echo $client->__getLastRequest() . "\n";

// ══════════════════════════════════════════════
// FUNCIÓN UDDI LOOKUP
// ══════════════════════════════════════════════
function lookupUDDI(string $ip, int $puerto, string $nombre): string|false {
    $s = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$s || !@socket_connect($s, $ip, $puerto)) {
        @socket_close($s);
        return false;
    }
    $msg = "LOOKUP|{$nombre}\n";
    socket_write($s, $msg, strlen($msg));
    $resp   = trim(socket_read($s, 512, PHP_NORMAL_READ));
    socket_close($s);

    // Respuesta: OK|endpoint|soap
    $partes = explode('|', $resp);
    if ($partes[0] !== 'OK' || count($partes) < 2) return false;
    return $partes[1]; // endpoint HTTP
}

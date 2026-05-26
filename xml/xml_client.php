<?php

$registryIP     = '127.0.0.1';
$registryPuerto = 9999;
$nombreServicio = 'fylcad.topografia';

echo "============================================\n";
echo "  FYLCAD — Cliente XML\n";
echo "  Registry: {$registryIP}:{$registryPuerto}\n";
echo "============================================\n\n";

// ── LOOKUP igual que antes ────────────────────────────────
$ubicacion = hacerLookup($registryIP, $registryPuerto, $nombreServicio);
if (!$ubicacion) {
    die("[LOOKUP] ERROR: No se encontró el servicio '{$nombreServicio}'.\n");
}
$serverIP     = $ubicacion['ip'];
$serverPuerto = $ubicacion['puerto'];
echo "[LOOKUP] Servicio encontrado en {$serverIP}:{$serverPuerto}\n\n";

// ── PRUEBA 1: Ping en XML ─────────────────────────────────
echo "── PRUEBA 1: Ping XML ──\n";
$xmlPing = construirRequestXML('ping', []);
$resp    = enviarXML($serverIP, $serverPuerto, $xmlPing);
echo "Respuesta:\n{$resp}\n\n";

// ── PRUEBA 2: Cálculo topográfico en XML ──────────────────
echo "── PRUEBA 2: Cálculo topográfico XML ──\n";
$puntos = [
    ['x' => 100.0, 'y' => 200.0, 'z' => 1520.5],
    ['x' => 150.0, 'y' => 200.0, 'z' => 1522.3],
    ['x' => 150.0, 'y' => 250.0, 'z' => 1519.8],
    ['x' => 125.0, 'y' => 270.0, 'z' => 1521.0],
    ['x' => 100.0, 'y' => 250.0, 'z' => 1518.7],
];
$xmlCalc = construirRequestXML('calcular', $puntos);
echo "Payload XML enviado:\n{$xmlCalc}\n\n";

$resp = enviarXML($serverIP, $serverPuerto, $xmlCalc);
echo "Respuesta:\n{$resp}\n\n";

// ── PRUEBA 3: Payload inválido ────────────────────────────
echo "── PRUEBA 3: XML inválido ──\n";
$resp = enviarXML($serverIP, $serverPuerto, "ESTO_NO_ES_XML");
echo "Respuesta:\n{$resp}\n";

echo "\n============================================\n";
echo "  Todas las pruebas completadas.\n";
echo "============================================\n";

// ══════════════════════════════════════════════
// FUNCIONES
// ══════════════════════════════════════════════

/**
 * Construye el mensaje XML de solicitud (Actividad 1)
 */
function construirRequestXML(string $operacion, array $puntos): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<fylcad-message>';
    $xml .= "<operation>{$operacion}</operation>";

    if (!empty($puntos)) {
        $xml .= '<data><project_id>1</project_id><points>';
        foreach ($puntos as $p) {
            $xml .= "<point><x>{$p['x']}</x><y>{$p['y']}</y><z>{$p['z']}</z></point>";
        }
        $xml .= '</points></data>';
    }

    $xml .= '<control>';
    $xml .= '<timestamp>' . date('c') . '</timestamp>';
    $xml .= '<client_id>FYLCAD-CLIENT-01</client_id>';
    $xml .= '</control>';
    $xml .= '</fylcad-message>';
    return $xml;
}

/**
 * Stub XML: conecta, envía el XML y recibe respuesta.
 * Mismo rol que enviarPayload() en socket_client.php
 */
function enviarXML(string $ip, int $puerto, string $xml): string {
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) return "ERROR: No se pudo crear el socket.";

    if (!socket_connect($socket, $ip, $puerto)) {
        socket_close($socket);
        return "ERROR: No se pudo conectar a {$ip}:{$puerto}";
    }

    $mensaje = $xml . "\n";
    socket_write($socket, $mensaje, strlen($mensaje));
    $respuesta = socket_read($socket, 4096, PHP_NORMAL_READv);
    socket_close($socket);
    return trim($respuesta);
}

/**
 * Lookup al Registry — igual que socket_client.php
 */
function hacerLookup(string $registryIP, int $registryPuerto, string $nombre): array|false {
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($sock, $registryIP, $registryPuerto)) {
        socket_close($sock);
        return false;
    }
    $msg = "LOOKUP|{$nombre}\n";
    socket_write($sock, $msg, strlen($msg));
    $resp   = trim(socket_read($sock, 512, PHP_NORMAL_READ));
    socket_close($sock);

    $partes = explode('|', $resp);
    if ($partes[0] !== 'OK' || count($partes) < 3) return false;

    return ['ip' => $partes[1], 'puerto' => (int)$partes[2]];
}
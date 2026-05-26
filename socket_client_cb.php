<?php
/**
 * FYLCAD — Cliente con Remote Callback (compatible Windows)
 * Archivo: socket_client_cb.php
 * Guía 7 — Actividad 1: Remote Callbacks
 */

$registryIP     = '127.0.0.1';
$registryPuerto = 9999;
$nombreServicio = 'fylcad.topografia';
$puertoCallback = 9001;

echo "============================================\n";
echo "  FYLCAD — Cliente con Remote Callback\n";
echo "  Puerto callback: {$puertoCallback}\n";
echo "============================================\n\n";

// LOOKUP en el Registry
echo "[LOOKUP] Buscando '{$nombreServicio}' en el Registry...\n";
$ubicacion = hacerLookup($registryIP, $registryPuerto, $nombreServicio);
if (!$ubicacion) {
    die("[LOOKUP] ERROR: Servicio no encontrado.\n");
}
$serverIP     = $ubicacion['ip'];
$serverPuerto = $ubicacion['puerto'];
echo "[LOOKUP] Servicio en {$serverIP}:{$serverPuerto}\n\n";

// Levantar socket de escucha para callbacks (no bloqueante)
$cbSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($cbSocket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($cbSocket, '0.0.0.0', $puertoCallback);
socket_listen($cbSocket, 5);
socket_set_nonblock($cbSocket);
echo "[CALLBACK LISTENER] Escuchando en puerto {$puertoCallback}...\n\n";

// REGISTER: enviar referencia de callback al servidor
echo "[REGISTER] Enviando referencia de callback al servidor...\n";
$regResp = enviarPayload($serverIP, $serverPuerto, "REGISTER|{$puertoCallback}");
echo "[REGISTER] Respuesta: {$regResp}\n\n";

// PRUEBA 1: Ping
echo "-- PRUEBA 1: Ping --\n";
$resp = enviarPayload($serverIP, $serverPuerto, "FYLCAD|ping|test");
echo "Respuesta: {$resp}\n\n";
revisarCallback($cbSocket);

// PRUEBA 2: Calculo topografico
echo "-- PRUEBA 2: Calculo topografico --\n";
echo "Enviando 5 puntos — el servidor invocara callback...\n\n";

$coordenadas = "100.0,200.0,1520.5;"
             . "150.0,200.0,1522.3;"
             . "150.0,250.0,1519.8;"
             . "125.0,270.0,1521.0;"
             . "100.0,250.0,1518.7";

$resp = enviarPayload($serverIP, $serverPuerto, "FYLCAD|calcular|{$coordenadas}");
echo "Respuesta directa: {$resp}\n";

if (strpos($resp, 'FYLCAD|resultado|') === 0) {
    $datos = str_replace('FYLCAD|resultado|', '', trim($resp));
    echo "\n-- RESULTADOS --\n";
    foreach (explode(';', $datos) as $campo) {
        $par = explode(':', $campo);
        if (count($par) === 2) {
            echo "  " . strtoupper(str_replace('_', ' ', $par[0])) . ": {$par[1]}\n";
        }
    }
}

echo "\n[CLIENTE] Esperando notificacion asincrona del servidor...\n";
sleep(1);
revisarCallback($cbSocket);

// PRUEBA 3: Payload invalido
echo "\n-- PRUEBA 3: Payload invalido --\n";
$resp = enviarPayload($serverIP, $serverPuerto, "DATOS_INVALIDOS");
echo "Respuesta: {$resp}\n";
revisarCallback($cbSocket);

echo "\n============================================\n";
echo "  Todas las pruebas completadas.\n";
echo "============================================\n";

socket_close($cbSocket);

// FUNCIONES
function revisarCallback($cbSocket): void {
    $read   = [$cbSocket];
    $write  = null;
    $except = null;
    $listo  = socket_select($read, $write, $except, 2);
    if ($listo === false || $listo === 0) {
        echo "[CALLBACK] Sin notificaciones pendientes.\n";
        return;
    }
    $conn = socket_accept($cbSocket);
    if ($conn === false) return;
    $msg = trim(socket_read($conn, 1024, PHP_NORMAL_READ));
    socket_close($conn);
    if (empty($msg)) return;
    $partes  = explode('|', $msg);
    $evento  = $partes[1] ?? 'desconocido';
    $cbDatos = $partes[2] ?? '';
    echo "\n╔══════════════════════════════════════════╗\n";
    echo "║  NOTIFICACION ASINCRONA RECIBIDA         ║\n";
    echo "╠══════════════════════════════════════════╣\n";
    echo "║  Evento : {$evento}\n";
    echo "║  Datos  : {$cbDatos}\n";
    echo "╚══════════════════════════════════════════╝\n";
}

function hacerLookup(string $rIP, int $rPuerto, string $nombre): array|false {
    $s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($s, $rIP, $rPuerto)) { socket_close($s); return false; }
    $msg = "LOOKUP|{$nombre}\n";
    socket_write($s, $msg, strlen($msg));
    $resp   = trim(socket_read($s, 512, PHP_NORMAL_READ));
    socket_close($s);
    $partes = explode('|', $resp);
    if ($partes[0] !== 'OK' || count($partes) < 3) return false;
    return ['ip' => $partes[1], 'puerto' => (int)$partes[2]];
}

function enviarPayload(string $ip, int $puerto, string $payload): string {
    $s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($s, $ip, $puerto)) {
        socket_close($s);
        return "ERROR: No se pudo conectar a {$ip}:{$puerto}";
    }
    $msg = $payload . "\n";
    socket_write($s, $msg, strlen($msg));
    $resp = socket_read($s, 4096, PHP_NORMAL_READ);
    socket_close($s);
    return trim($resp ?? '');
}
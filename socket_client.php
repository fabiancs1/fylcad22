<?php
/**
 * FYLCAD — Cliente de Sockets con Lookup al Registry
 * Archivo: socket_client.php
 *
 * El cliente NO tiene IP hardcodeada del servidor.
 * Primero hace Lookup al Registry usando el nombre lógico
 * 'fylcad.topografia' y obtiene dinámicamente la IP y puerto.
 * Esto elimina el acoplamiento directo entre cliente y servidor.
 *
 * USO: php socket_client.php
 * Registry: 127.0.0.1:9999
 */

// ── Única IP fija permitida: el Registry ──────────────────
// El cliente solo conoce al Registry, nunca al servidor directamente
$registryIP     = '127.0.0.1';
$registryPuerto = 9999;
$nombreServicio = 'fylcad.topografia';   // nombre lógico, no IP

echo "============================================\n";
echo "  FYLCAD — Cliente de Sockets\n";
echo "  Registry: {$registryIP}:{$registryPuerto}\n";
echo "============================================\n\n";

// ── LOOKUP: localizar el servicio en el Registry ──────────
echo "── LOOKUP: Buscando '{$nombreServicio}' en el Registry...\n";
$ubicacion = hacerLookup($registryIP, $registryPuerto, $nombreServicio);

if (!$ubicacion) {
    die("[LOOKUP] ERROR: No se encontró el servicio '{$nombreServicio}'.\n"
      . "         Asegúrese de que el servidor esté corriendo.\n");
}

$serverIP    = $ubicacion['ip'];
$serverPuerto= $ubicacion['puerto'];
echo "[LOOKUP] Servicio encontrado en {$serverIP}:{$serverPuerto}\n\n";

// ── PRUEBA 1: Ping ─────────────────────────────────────────
echo "── PRUEBA 1: Handshake / Ping ──\n";
$resp = enviarPayload($serverIP, $serverPuerto, "FYLCAD|ping|test");
echo "Respuesta del servidor: {$resp}\n\n";

// ── PRUEBA 2: Cálculo topográfico ─────────────────────────
echo "── PRUEBA 2: Cálculo topográfico ──\n";
echo "Enviando 5 puntos de un terreno en FYLCAD...\n";

$coordenadas = "100.0,200.0,1520.5;"
             . "150.0,200.0,1522.3;"
             . "150.0,250.0,1519.8;"
             . "125.0,270.0,1521.0;"
             . "100.0,250.0,1518.7";

$payload = "FYLCAD|calcular|{$coordenadas}";
echo "Payload enviado: {$payload}\n\n";

$resp = enviarPayload($serverIP, $serverPuerto, $payload);
echo "Respuesta del servidor: {$resp}\n";

if (strpos($resp, 'FYLCAD|resultado|') === 0) {
    $datos  = str_replace('FYLCAD|resultado|', '', trim($resp));
    $campos = explode(';', $datos);
    echo "\n── RESULTADOS PROCESADOS ──\n";
    foreach ($campos as $campo) {
        $par = explode(':', $campo);
        if (count($par) === 2) {
            $clave = strtoupper(str_replace('_', ' ', $par[0]));
            echo "  {$clave}: {$par[1]}\n";
        }
    }
}

// ── PRUEBA 3: Payload inválido ────────────────────────────
echo "\n── PRUEBA 3: Payload invalido (manejo de errores) ──\n";
$resp = enviarPayload($serverIP, $serverPuerto, "DATOS_INVALIDOS");
echo "Respuesta del servidor: {$resp}\n";

echo "\n============================================\n";
echo "  Todas las pruebas completadas.\n";
echo "============================================\n";

// ══════════════════════════════════════════════
// FUNCIONES
// ══════════════════════════════════════════════

/**
 * LOOKUP: consulta al Registry el nombre lógico del servicio
 * y retorna ['ip' => ..., 'puerto' => ...] o false si no existe.
 */
function hacerLookup(string $registryIP, int $registryPuerto, string $nombre): array|false {
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($sock, $registryIP, $registryPuerto)) {
        echo "[LOOKUP] ERROR: No se pudo conectar al Registry.\n";
        socket_close($sock);
        return false;
    }

    $msg = "LOOKUP|{$nombre}\n";
    socket_write($sock, $msg, strlen($msg));
    $resp = trim(socket_read($sock, 512, PHP_NORMAL_READ));
    socket_close($sock);

    // Respuesta esperada: OK|ip|puerto
    $partes = explode('|', $resp);
    if ($partes[0] !== 'OK' || count($partes) < 3) {
        echo "[LOOKUP] Servicio no encontrado: {$resp}\n";
        return false;
    }

    return [
        'ip'     => $partes[1],
        'puerto' => (int)$partes[2],
    ];
}

/**
 * Stub: crea socket, conecta, envía payload y recibe respuesta.
 * El programa principal llama esto sin saber nada del socket.
 */
function enviarPayload(string $ip, int $puerto, string $payload): string {
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        return "ERROR: No se pudo crear el socket.";
    }

    if (!socket_connect($socket, $ip, $puerto)) {
        socket_close($socket);
        return "ERROR: No se pudo conectar a {$ip}:{$puerto} — "
             . socket_strerror(socket_last_error());
    }

    $mensaje = $payload . "\n";
    socket_write($socket, $mensaje, strlen($mensaje));
    $respuesta = socket_read($socket, 4096, PHP_NORMAL_READ);
    socket_close($socket);

    return trim($respuesta);
}

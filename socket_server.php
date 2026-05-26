<?php
/**
 * FYLCAD — Servidor de Sockets con Bind al Registry
 * Archivo: socket_server.php
 *
 * Al arrancar hace Bind en el Registry para registrar
 * el servicio 'fylcad.topografia' con su IP y puerto real.
 * Así el cliente nunca necesita saber la IP directamente.
 *
 * USO: php socket_server.php
 * Puerto del servicio: 9000
 * Registry: 127.0.0.1:9999
 */

// ── Configuración ──────────────────────────────────────────
$host          = '0.0.0.0';
$puerto        = 9000;
$registryIP    = '127.0.0.1';
$registryPuerto= 9999;
$nombreServicio= 'fylcad.topografia';   // nombre lógico del servicio

// ── Funciones de cálculo topográfico ──────────────────────
function parsearPuntos(string $payload): array {
    $puntos = [];
    $lineas = explode(';', $payload);
    foreach ($lineas as $linea) {
        $p = explode(',', trim($linea));
        if (count($p) >= 3 && is_numeric($p[0]) && is_numeric($p[1]) && is_numeric($p[2])) {
            $puntos[] = ['x' => (float)$p[0], 'y' => (float)$p[1], 'z' => (float)$p[2]];
        }
    }
    return $puntos;
}

function calcularArea(array $puntos): float {
    $n    = count($puntos);
    $area = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $j     = ($i + 1) % $n;
        $area += $puntos[$i]['x'] * $puntos[$j]['y'];
        $area -= $puntos[$j]['x'] * $puntos[$i]['y'];
    }
    return abs($area) / 2.0;
}

function calcularVolumen(array $puntos): float {
    $area      = calcularArea($puntos);
    $cotaMedia = array_sum(array_column($puntos, 'z')) / count($puntos);
    $cotaBase  = min(array_column($puntos, 'z'));
    $altura    = $cotaMedia - $cotaBase;
    return $area * max($altura, 0);
}

// ── BIND: registrar este servicio en el Registry ──────────
function hacerBind(string $registryIP, int $registryPuerto, string $nombre, string $ip, int $puerto): bool {
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($sock, $registryIP, $registryPuerto)) {
        echo "[BIND] ERROR: No se pudo conectar al Registry en {$registryIP}:{$registryPuerto}\n";
        socket_close($sock);
        return false;
    }
    $msg = "BIND|{$nombre}|{$ip}|{$puerto}\n";
    socket_write($sock, $msg, strlen($msg));
    $resp = trim(socket_read($sock, 512, PHP_NORMAL_READ));
    socket_close($sock);
    echo "[BIND] Respuesta del Registry: {$resp}\n";
    return strpos($resp, 'OK') === 0;
}

// ── Crear socket del servidor ──────────────────────────────
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die("ERROR socket_create: " . socket_strerror(socket_last_error()) . "\n");
}
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

if (!socket_bind($socket, $host, $puerto)) {
    die("ERROR socket_bind: " . socket_strerror(socket_last_error()) . "\n");
}
socket_listen($socket, 5);

echo "============================================\n";
echo "  FYLCAD — Servidor de Sockets\n";
echo "  Escuchando en {$host}:{$puerto}\n";
echo "============================================\n\n";

// ── BIND al Registry ───────────────────────────────────────
echo "[BIND] Registrando '{$nombreServicio}' en el Registry...\n";
$bindOk = hacerBind($registryIP, $registryPuerto, $nombreServicio, '127.0.0.1', $puerto);
if ($bindOk) {
    echo "[BIND] Servicio registrado correctamente.\n\n";
} else {
    echo "[BIND] ADVERTENCIA: No se pudo registrar en el Registry. Continuando de todas formas.\n\n";
}

echo "[SERVIDOR] Esperando conexiones de clientes...\n";

// ── Bucle principal ────────────────────────────────────────
while (true) {
    $cliente = socket_accept($socket);
    if ($cliente === false) {
        echo "ERROR socket_accept\n";
        continue;
    }

    socket_getpeername($cliente, $clienteIP);
    echo "\n[HANDSHAKE OK] Cliente conectado desde: {$clienteIP}\n";

    $datos  = trim(socket_read($cliente, 4096, PHP_NORMAL_READ));
    echo "[RECIBIDO] {$datos}\n";

    $partes = explode('|', $datos);

    if (count($partes) < 3 || $partes[0] !== 'FYLCAD') {
        $respuesta = "FYLCAD|error|Payload invalido. Formato: FYLCAD|accion|datos\n";
    } else {
        $accion  = strtolower(trim($partes[1]));
        $payload = trim($partes[2]);

        switch ($accion) {

            case 'calcular':
                $puntos = parsearPuntos($payload);
                if (count($puntos) < 3) {
                    $respuesta = "FYLCAD|error|Se necesitan al menos 3 puntos\n";
                } else {
                    $area     = calcularArea($puntos);
                    $volumen  = calcularVolumen($puntos);
                    $cotaMin  = min(array_column($puntos, 'z'));
                    $cotaMax  = max(array_column($puntos, 'z'));
                    $desnivel = round($cotaMax - $cotaMin, 2);
                    $nPuntos  = count($puntos);
                    $respuesta = "FYLCAD|resultado|"
                        . "puntos:{$nPuntos};"
                        . "area:"     . round($area, 2)    . "m2;"
                        . "volumen:"  . round($volumen, 2) . "m3;"
                        . "cota_min:{$cotaMin}m;"
                        . "cota_max:{$cotaMax}m;"
                        . "desnivel:{$desnivel}m\n";
                }
                break;

            case 'ping':
                $respuesta = "FYLCAD|pong|Servidor FYLCAD activo en puerto {$puerto}\n";
                break;

            default:
                $respuesta = "FYLCAD|error|Accion desconocida: {$accion}\n";
                break;
        }
    }

    socket_write($cliente, $respuesta, strlen($respuesta));
    echo "[ENVIADO]  {$respuesta}";

    socket_close($cliente);
    echo "[CERRADO]  Conexion con {$clienteIP} cerrada correctamente.\n";
}

socket_close($socket);

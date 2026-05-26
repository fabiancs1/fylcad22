<?php
/**
 * FYLCAD — Servidor con Remote Callbacks
 * Archivo: socket_server_cb.php
 * Guía 7 — Actividad 1: Remote Callbacks
 *
 * El servidor mantiene una lista de clientes activos.
 * Cuando ocurre un evento de negocio (cálculo completado),
 * invoca el callback en el cliente de forma asíncrona.
 *
 * USO: php socket_server_cb.php
 * Puerto servidor:  9000
 * Registry:         127.0.0.1:9999
 */

$host           = '0.0.0.0';
$puerto         = 9000;
$registryIP     = '127.0.0.1';
$registryPuerto = 9999;
$nombreServicio = 'fylcad.topografia';

// Lista de clientes activos con su referencia de callback
// ['ip' => ..., 'puerto_callback' => ...]
$clientesActivos = [];

// ── Funciones topográficas ─────────────────────────────────
function parsearPuntos(string $payload): array {
    $puntos = [];
    foreach (explode(';', $payload) as $linea) {
        $p = explode(',', trim($linea));
        if (count($p) >= 3 && is_numeric($p[0])) {
            $puntos[] = ['x' => (float)$p[0], 'y' => (float)$p[1], 'z' => (float)$p[2]];
        }
    }
    return $puntos;
}

function calcularArea(array $puntos): float {
    $n = count($puntos); $area = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $area += $puntos[$i]['x'] * $puntos[$j]['y'];
        $area -= $puntos[$j]['x'] * $puntos[$i]['y'];
    }
    return abs($area) / 2.0;
}

function calcularVolumen(array $puntos): float {
    $area     = calcularArea($puntos);
    $cotaBase = min(array_column($puntos, 'z'));
    $altura   = (array_sum(array_column($puntos, 'z')) / count($puntos)) - $cotaBase;
    return $area * max($altura, 0);
}

// ── Función: invocar callback en el cliente ────────────────
function invocarCallback(string $clienteIP, int $puertoCallback, string $evento, string $datos): void {
    echo "[CALLBACK] Invocando callback en {$clienteIP}:{$puertoCallback}...\n";
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($sock, $clienteIP, $puertoCallback)) {
        echo "[CALLBACK] ERROR: No se pudo conectar al cliente para callback.\n";
        socket_close($sock);
        return;
    }
    $msg = "CALLBACK|{$evento}|{$datos}\n";
    socket_write($sock, $msg, strlen($msg));
    socket_close($sock);
    echo "[CALLBACK] Evento '{$evento}' enviado al cliente.\n";
}

// ── Bind al Registry ───────────────────────────────────────
function hacerBind(string $rIP, int $rPuerto, string $nombre, string $ip, int $puerto): void {
    $s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (socket_connect($s, $rIP, $rPuerto)) {
        $msg = "BIND|{$nombre}|{$ip}|{$puerto}\n";
        socket_write($s, $msg, strlen($msg));
        $resp = trim(socket_read($s, 512, PHP_NORMAL_READ));
        echo "[BIND] {$resp}\n";
    } else {
        echo "[BIND] ADVERTENCIA: Registry no disponible.\n";
    }
    socket_close($s);
}

// ── Crear socket servidor ──────────────────────────────────
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $host, $puerto);
socket_listen($socket, 10);

echo "============================================\n";
echo "  FYLCAD — Servidor con Remote Callbacks\n";
echo "  Puerto: {$puerto}\n";
echo "============================================\n\n";

hacerBind($registryIP, $registryPuerto, $nombreServicio, '127.0.0.1', $puerto);
echo "\n[SERVIDOR] Esperando conexiones...\n\n";

// ── Bucle principal ────────────────────────────────────────
while (true) {
    $cliente = socket_accept($socket);
    if ($cliente === false) continue;

    socket_getpeername($cliente, $clienteIP);
    $datos = trim(socket_read($cliente, 4096, PHP_NORMAL_READ));
    echo "\n[RECIBIDO] De {$clienteIP}: {$datos}\n";

    $partes = explode('|', $datos);
    $tipo   = strtoupper(trim($partes[0] ?? ''));

    // ── REGISTER: cliente registra su puerto de callback ──
    if ($tipo === 'REGISTER') {
        $puertoCallback = (int)trim($partes[1] ?? 0);
        $clientesActivos[$clienteIP] = [
            'ip'              => $clienteIP,
            'puerto_callback' => $puertoCallback,
        ];
        $respuesta = "OK|Registrado con callback en puerto {$puertoCallback}\n";
        echo "[REGISTER] Cliente {$clienteIP} registrado con callback:{$puertoCallback}\n";
        socket_write($cliente, $respuesta, strlen($respuesta));
        socket_close($cliente);
        continue;
    }

    // ── Protocolo FYLCAD normal ────────────────────────────
    if (count($partes) < 3 || $partes[0] !== 'FYLCAD') {
        $respuesta = "FYLCAD|error|Payload invalido\n";
        socket_write($cliente, $respuesta, strlen($respuesta));
        socket_close($cliente);
        continue;
    }

    $accion  = strtolower(trim($partes[1]));
    $payload = trim($partes[2]);

    switch ($accion) {
        case 'ping':
            $respuesta = "FYLCAD|pong|Servidor FYLCAD activo en puerto {$puerto}\n";
            break;

        case 'calcular':
            $puntos = parsearPuntos($payload);
            if (count($puntos) < 3) {
                $respuesta = "FYLCAD|error|Minimo 3 puntos requeridos\n";
            } else {
                $area     = round(calcularArea($puntos), 2);
                $volumen  = round(calcularVolumen($puntos), 2);
                $cotaMin  = min(array_column($puntos, 'z'));
                $cotaMax  = max(array_column($puntos, 'z'));
                $desnivel = round($cotaMax - $cotaMin, 2);
                $nPuntos  = count($puntos);

                $respuesta = "FYLCAD|resultado|"
                    . "puntos:{$nPuntos};"
                    . "area:{$area}m2;"
                    . "volumen:{$volumen}m3;"
                    . "cota_min:{$cotaMin}m;"
                    . "cota_max:{$cotaMax}m;"
                    . "desnivel:{$desnivel}m\n";

                // ── Invocar callback asíncrono en todos los clientes registrados
                $eventoData = "proyecto_calculado;puntos:{$nPuntos};area:{$area}m2";
                foreach ($clientesActivos as $cb) {
                    invocarCallback($cb['ip'], $cb['puerto_callback'],
                        'calculo_completado', $eventoData);
                }
            }
            break;

        default:
            $respuesta = "FYLCAD|error|Accion desconocida: {$accion}\n";
    }

    socket_write($cliente, $respuesta, strlen($respuesta));
    echo "[ENVIADO]  {$respuesta}";
    socket_close($cliente);
    echo "[CERRADO]  Conexion cerrada.\n";
}

socket_close($socket);
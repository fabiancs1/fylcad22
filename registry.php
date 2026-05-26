<?php
/**
 * FYLCAD — Registry de Servicios Remotos
 * Archivo: registry.php
 *
 * Actúa como servidor de directorio (Name Server).
 * Los servidores hacen Bind para registrarse.
 * Los clientes hacen Lookup para localizar servicios por nombre.
 *
 * Protocolo:
 *   BIND|nombre_servicio|ip|puerto     → registra un servicio
 *   LOOKUP|nombre_servicio             → devuelve ip:puerto del servicio
 *   LIST                               → lista todos los servicios activos
 *
 * USO: php registry.php
 * Puerto del Registry: 9999
 */

$host         = '0.0.0.0';
$puerto       = 9999;
$servicios    = [];   // ['nombre' => ['ip' => ..., 'puerto' => ...]]

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $host, $puerto);
socket_listen($socket, 10);

echo "=============================================\n";
echo "  FYLCAD — Registry de Servicios\n";
echo "  Escuchando en {$host}:{$puerto}\n";
echo "  Esperando operaciones Bind / Lookup...\n";
echo "=============================================\n\n";

while (true) {
    $cliente = socket_accept($socket);
    if ($cliente === false) continue;

    socket_getpeername($cliente, $clienteIP);
    $datos = trim(socket_read($cliente, 1024, PHP_NORMAL_READ));

    echo "[REGISTRY] Solicitud de {$clienteIP}: {$datos}\n";

    $partes    = explode('|', $datos);
    $operacion = strtoupper(trim($partes[0] ?? ''));

    switch ($operacion) {

        // ── BIND: el servidor registra su servicio ──────────────
        case 'BIND':
            if (count($partes) < 4) {
                $respuesta = "ERROR|Formato: BIND|nombre|ip|puerto\n";
                break;
            }
            $nombre  = trim($partes[1]);
            $ip      = trim($partes[2]);
            $puertoS = (int)trim($partes[3]);

            $servicios[$nombre] = ['ip' => $ip, 'puerto' => $puertoS];
            $respuesta = "OK|BIND|{$nombre} registrado en {$ip}:{$puertoS}\n";
            echo "[BIND]   Servicio '{$nombre}' → {$ip}:{$puertoS}\n";
            break;

        // ── LOOKUP: el cliente busca un servicio por nombre ─────
        case 'LOOKUP':
            if (count($partes) < 2) {
                $respuesta = "ERROR|Formato: LOOKUP|nombre\n";
                break;
            }
            $nombre = trim($partes[1]);

            if (!isset($servicios[$nombre])) {
                $respuesta = "ERROR|Servicio '{$nombre}' no encontrado en el Registry\n";
                echo "[LOOKUP] '{$nombre}' → NO ENCONTRADO\n";
            } else {
                $s         = $servicios[$nombre];
                $respuesta = "OK|{$s['ip']}|{$s['puerto']}\n";
                echo "[LOOKUP] '{$nombre}' → {$s['ip']}:{$s['puerto']}\n";
            }
            break;

        // ── LIST: muestra todos los servicios registrados ───────
        case 'LIST':
            if (empty($servicios)) {
                $respuesta = "OK|Sin servicios registrados\n";
            } else {
                $lista = [];
                foreach ($servicios as $nombre => $datos) {
                    $lista[] = "{$nombre}={$datos['ip']}:{$datos['puerto']}";
                }
                $respuesta = "OK|" . implode(';', $lista) . "\n";
            }
            echo "[LIST]   " . count($servicios) . " servicio(s) activo(s)\n";
            break;

        default:
            $respuesta = "ERROR|Operacion desconocida. Use: BIND, LOOKUP o LIST\n";
            break;
    }

    socket_write($cliente, $respuesta, strlen($respuesta));
    socket_close($cliente);
}

socket_close($socket);

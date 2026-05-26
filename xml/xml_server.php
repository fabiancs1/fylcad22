<?php
/**
 * FYLCAD — Servidor XML con Bind al Registry
 * Archivo: xml/xml_server.php
 * Guía 8 — Actividades 2, 3, 4
 *
 * Mismo flujo que socket_server.php pero recibe XML,
 * lo valida contra el XSD, extrae datos con XPath
 * y responde en formato XML.
 */

$host           = '0.0.0.0';
$puerto         = 9001;          // puerto distinto al original (9000)
$registryIP     = '127.0.0.1';
$registryPuerto = 9999;
$nombreServicio = 'fylcad.topografia';   // nombre lógico distinto

echo "============================================\n";
echo "  FYLCAD — Servidor XML\n";
echo "  Escuchando en {$host}:{$puerto}\n";
echo "============================================\n\n";

// ── Crear y bindear socket ────────────────────────────────
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, $host, $puerto);
socket_listen($socket, 5);

// ── BIND al Registry ──────────────────────────────────────
echo "[BIND] Registrando '{$nombreServicio}' en el Registry...\n";
$bindOk = hacerBind($registryIP, $registryPuerto, $nombreServicio, '127.0.0.1', $puerto);
echo $bindOk
    ? "[BIND] Servicio registrado correctamente.\n\n"
    : "[BIND] ADVERTENCIA: No se pudo registrar. Continuando de todas formas.\n\n";

echo "[SERVIDOR XML] Esperando conexiones...\n";

// ── Bucle principal ───────────────────────────────────────
while (true) {
    $cliente = socket_accept($socket);
    if ($cliente === false) continue;

    socket_getpeername($cliente, $clienteIP);
    echo "\n[HANDSHAKE OK] Cliente conectado desde: {$clienteIP}\n";

    $datos = trim(socket_read($cliente, 4096, PHP_NORMAL_READ));
    echo "[RECIBIDO XML]\n{$datos}\n";

    // ── Actividad 2: Validar XML contra XSD ──────────────
    if (!validarXML($datos)) {
        $respuesta = construirErrorXML('Mensaje XML inválido o no cumple el esquema XSD');
        socket_write($cliente, $respuesta . "\n", strlen($respuesta) + 1);
        echo "[ENVIADO ERROR]\n{$respuesta}\n";
        socket_close($cliente);
        continue;
    }

    // ── Actividad 4: Extraer datos con XPath ─────────────
    $resultado = procesarConXPath($datos);
    $operacion = $resultado['operacion'];
    $puntos    = $resultado['puntos'];

    echo "[OPERACION] {$operacion}\n";

    // ── Procesar según operación ──────────────────────────
    switch (strtolower($operacion)) {

        case 'calcular':
            if (count($puntos) < 3) {
                $respuesta = construirErrorXML('Se necesitan al menos 3 puntos');
            } else {
                $area     = calcularArea($puntos);
                $volumen  = calcularVolumen($puntos);
                $cotaMin  = min(array_column($puntos, 'z'));
                $cotaMax  = max(array_column($puntos, 'z'));
                $desnivel = round($cotaMax - $cotaMin, 2);
                $respuesta = construirResponseXML([
                    'puntos'   => count($puntos),
                    'area'     => round($area, 2),
                    'volumen'  => round($volumen, 2),
                    'cota_min' => $cotaMin,
                    'cota_max' => $cotaMax,
                    'desnivel' => $desnivel,
                ]);
            }
            break;

        case 'ping':
            $respuesta = construirResponseXML(['mensaje' => "Servidor FYLCAD XML activo en puerto {$puerto}"]);
            break;

        default:
            $respuesta = construirErrorXML("Operación desconocida: {$operacion}");
            break;
    }

    socket_write($cliente, $respuesta . "\n", strlen($respuesta) + 1);
    echo "[ENVIADO]\n{$respuesta}\n";

    socket_close($cliente);
    echo "[CERRADO] Conexión con {$clienteIP} cerrada.\n";
}

socket_close($socket);

// ══════════════════════════════════════════════
// FUNCIONES
// ══════════════════════════════════════════════

/**
 * Actividad 2: Valida el XML contra el esquema XSD
 */
function validarXML(string $xmlString): bool {
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xmlString)) return false;
    $xsdPath = __DIR__ . '/fylcad_protocol.xsd';
    return @$dom->schemaValidate($xsdPath);
}

/**
 * Actividad 4: Extrae operación y puntos usando XPath
 */
function procesarConXPath(string $xmlString): array {
    $dom = new DOMDocument();
    $dom->loadXML($xmlString);
    $xpath = new DOMXPath($dom);

    $operacion = $xpath->query('/fylcad-message/operation')->item(0)->nodeValue ?? '';

    $nodos  = $xpath->query('/fylcad-message/data/points/point');
    $puntos = [];
    foreach ($nodos as $nodo) {
        $puntos[] = [
            'x' => (float)$xpath->query('x', $nodo)->item(0)->nodeValue,
            'y' => (float)$xpath->query('y', $nodo)->item(0)->nodeValue,
            'z' => (float)$xpath->query('z', $nodo)->item(0)->nodeValue,
        ];
    }

    return ['operacion' => $operacion, 'puntos' => $puntos];
}

/**
 * Actividad 1: Construye respuesta XML exitosa
 */
function construirResponseXML(array $resultados): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<fylcad-message>';
    $xml .= '<status>success</status>';
    $xml .= '<data><results>';
    foreach ($resultados as $clave => $valor) {
        $xml .= "<{$clave}>{$valor}</{$clave}>";
    }
    $xml .= '</results></data>';
    $xml .= '<control><timestamp>' . date('c') . '</timestamp></control>';
    $xml .= '</fylcad-message>';
    return $xml;
}

/**
 * Actividad 1: Construye respuesta XML de error
 */
function construirErrorXML(string $mensaje): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<fylcad-message>';
    $xml .= '<status>error</status>';
    $xml .= "<message>{$mensaje}</message>";
    $xml .= '<control><timestamp>' . date('c') . '</timestamp></control>';
    $xml .= '</fylcad-message>';
    return $xml;
}

/**
 * Cálculos topográficos — igual que socket_server.php
 */
function calcularArea(array $puntos): float {
    $n = count($puntos); $area = 0.0;
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
    return $area * max($cotaMedia - $cotaBase, 0);
}

/**
 * BIND al Registry — igual que socket_server.php
 */
function hacerBind(string $registryIP, int $registryPuerto, string $nombre, string $ip, int $puerto): bool {
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_connect($sock, $registryIP, $registryPuerto)) {
        socket_close($sock); return false;
    }
    $msg = "BIND|{$nombre}|{$ip}|{$puerto}\n";
    socket_write($sock, $msg, strlen($msg));
    $resp = trim(socket_read($sock, 512, PHP_NORMAL_READ));
    socket_close($sock);
    return strpos($resp, 'OK') === 0;
}

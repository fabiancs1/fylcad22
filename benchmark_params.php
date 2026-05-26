<?php
/**
 * FYLCAD — Optimización del Paso de Parámetros
 * Archivo: benchmark_params.php
 * Guía 7 — Actividad 3: Por Valor vs Por Referencia
 *
 * Compara el rendimiento de enviar objetos:
 *   - Por VALOR: serialización completa del estado del objeto
 *   - Por REFERENCIA: solo se envía un identificador lógico (puntero)
 *
 * USO: php benchmark_params.php
 */

$serverIP    = '127.0.0.1';
$serverPuerto= 9000;
$iteraciones = 10;

echo "============================================\n";
echo "  FYLCAD — Benchmark: Valor vs Referencia\n";
echo "  Iteraciones por prueba: {$iteraciones}\n";
echo "============================================\n\n";

// ── Dataset: coordenadas de diferentes tamaños ────────────
$dataset_pequeno = generarPuntos(5);
$dataset_mediano = generarPuntos(50);
$dataset_grande  = generarPuntos(200);

$datasets = [
    'Pequeño (5 puntos)'   => $dataset_pequeno,
    'Mediano (50 puntos)'  => $dataset_mediano,
    'Grande (200 puntos)'  => $dataset_grande,
];

$resultados = [];

foreach ($datasets as $nombre => $puntos) {

    // ── POR VALOR: envía todos los datos serializados ─────
    $payloadValor   = serializarPorValor($puntos);
    $bytesValor     = strlen($payloadValor);
    $tiempoValorTotal = 0;

    for ($i = 0; $i < $iteraciones; $i++) {
        $inicio = microtime(true);
        $resp   = enviarPayload($serverIP, $serverPuerto, $payloadValor);
        $fin    = microtime(true);
        $tiempoValorTotal += ($fin - $inicio) * 1000; // ms
    }
    $tiempoValorProm = round($tiempoValorTotal / $iteraciones, 3);

    // ── POR REFERENCIA: envía solo un ID lógico ───────────
    // El servidor tiene los datos en caché, el cliente solo manda el ID
    $idProyecto      = md5(json_encode($puntos)); // ID único del dataset
    $payloadRef      = "FYLCAD|lookup_ref|{$idProyecto}";
    $bytesRef        = strlen($payloadRef);
    $tiempoRefTotal  = 0;

    for ($i = 0; $i < $iteraciones; $i++) {
        $inicio = microtime(true);
        // Simula el envío por referencia (solo el ID, sin datos)
        $resp   = enviarPayload($serverIP, $serverPuerto, $payloadRef);
        $fin    = microtime(true);
        $tiempoRefTotal += ($fin - $inicio) * 1000;
    }
    $tiempoRefProm = round($tiempoRefTotal / $iteraciones, 3);

    $resultados[$nombre] = [
        'bytes_valor'    => $bytesValor,
        'bytes_ref'      => $bytesRef,
        'tiempo_valor'   => $tiempoValorProm,
        'tiempo_ref'     => $tiempoRefProm,
        'ahorro_bytes'   => round((1 - $bytesRef / $bytesValor) * 100, 1),
        'ahorro_tiempo'  => round((1 - $tiempoRefProm / $tiempoValorProm) * 100, 1),
    ];
}

// ── Mostrar resultados ────────────────────────────────────
echo "── RESULTADOS DE LA PRUEBA ─────────────────\n\n";
echo str_pad("Dataset", 25)
   . str_pad("Bytes/Valor", 14)
   . str_pad("Bytes/Ref", 12)
   . str_pad("Ahorro", 10)
   . str_pad("T.Valor(ms)", 14)
   . str_pad("T.Ref(ms)", 12)
   . "Ahorro\n";
echo str_repeat("-", 87) . "\n";

foreach ($resultados as $nombre => $r) {
    echo str_pad($nombre, 25)
       . str_pad($r['bytes_valor'] . " B", 14)
       . str_pad($r['bytes_ref'] . " B", 12)
       . str_pad($r['ahorro_bytes'] . "%", 10)
       . str_pad($r['tiempo_valor'] . " ms", 14)
       . str_pad($r['tiempo_ref'] . " ms", 12)
       . $r['ahorro_tiempo'] . "%\n";
}

echo "\n── CONCLUSIÓN ──────────────────────────────\n";
$ahorroBytes  = $resultados['Grande (200 puntos)']['ahorro_bytes'];
$ahorroTiempo = $resultados['Grande (200 puntos)']['ahorro_tiempo'];
echo "Para objetos grandes ({$ahorroBytes}% menos bytes, {$ahorroTiempo}% menos latencia),\n";
echo "el paso Por Referencia es significativamente más eficiente.\n";
echo "Para objetos pequeños, la diferencia es mínima y\n";
echo "el paso Por Valor puede ser preferible por su simplicidad.\n";

// ══════════════════════════════════════════════
// FUNCIONES
// ══════════════════════════════════════════════

function generarPuntos(int $n): array {
    $puntos = [];
    for ($i = 0; $i < $n; $i++) {
        $puntos[] = [
            'x' => round(100 + $i * 2.5, 2),
            'y' => round(200 + $i * 1.8, 2),
            'z' => round(1518 + sin($i) * 5, 4),
        ];
    }
    return $puntos;
}

function serializarPorValor(array $puntos): string {
    $coords = implode(';', array_map(fn($p) =>
        "{$p['x']},{$p['y']},{$p['z']}", $puntos));
    return "FYLCAD|calcular|{$coords}";
}

function enviarPayload(string $ip, int $puerto, string $payload): string {
    $s = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!@socket_connect($s, $ip, $puerto)) {
        @socket_close($s);
        return "ERROR";
    }
    $msg = $payload . "\n";
    socket_write($s, $msg, strlen($msg));
    $resp = socket_read($s, 4096, PHP_NORMAL_READ);
    socket_close($s);
    return trim($resp ?? '');
}
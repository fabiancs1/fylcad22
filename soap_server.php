<?php
/**
 * FYLCAD — Servidor SOAP
 * Archivo: soap_server.php
 * Guía 9 — Actividad 2: Mensajería Estructurada SOAP
 *
 * Implementa el servicio SOAP de FYLCAD usando el WSDL
 * definido en fylcad_service.wsdl.
 * Maneja errores con SOAP Fault estándar.
 *
 * USO: Acceder desde navegador o cliente SOAP:
 *      http://localhost/FYLCAD/soap_server.php
 *      http://localhost/FYLCAD/soap_server.php?wsdl
 *
 * REGISTRO UDDI: Al cargar, registra el endpoint
 * en el registry.php para descubrimiento dinámico.
 */

// ── Registrar en UDDI (registry) al arrancar ──────────────
$registryIP     = '127.0.0.1';
$registryPuerto = 9999;
$endpoint       = 'http://localhost/FYLCAD/soap_server.php';
$nombreServicio = 'fylcad.soap.topografia';

registrarEnUDDI($registryIP, $registryPuerto, $nombreServicio, $endpoint);

// ── Iniciar servidor SOAP ──────────────────────────────────
$wsdl = __DIR__ . '/fylcad_service.wsdl';

ini_set('soap.wsdl_cache_enabled', 0);
$server = new SoapServer($wsdl, [
    'uri'      => 'http://fylcad.com/service',
    'encoding' => 'UTF-8',
]);

$server->setClass('FylcadSoapService');
$server->handle();

// ══════════════════════════════════════════════
// CLASE DE SERVICIO SOAP
// ══════════════════════════════════════════════
class FylcadSoapService {

    /**
     * Operación: ping
     * Verifica que el servicio está activo.
     */
    public function ping(stdClass $request): array {
        $origen = $request->origen ?? 'desconocido';
        error_log("[SOAP] ping desde: {$origen}");

        return [
            'estado'  => 'ok',
            'mensaje' => "Servidor FYLCAD SOAP activo. Origen: {$origen}",
        ];
    }

    /**
     * Operación: calcularMetricas
     * Recibe puntos topográficos y retorna métricas.
     */
    public function calcularMetricas(stdClass $request): array {

        // Extraer proyecto_id
        $proyectoId = $request->proyecto_id ?? 'sin-id';
        error_log("[SOAP] calcularMetricas - proyecto: {$proyectoId}");

        // Validar que vienen puntos
        if (!isset($request->puntos) || !isset($request->puntos->punto)) {
            throw new SoapFault(
                'Client',
                'Datos invalidos: se requiere al menos 3 puntos topograficos.',
                null,
                null,
                'FYLCAD_E001'
            );
        }

        // Normalizar puntos (puede venir como objeto o array)
        $puntosRaw = $request->puntos->punto;
        if (!is_array($puntosRaw)) {
            $puntosRaw = [$puntosRaw];
        }

        if (count($puntosRaw) < 3) {
            throw new SoapFault(
                'Client',
                'Se requieren minimo 3 puntos para calcular metricas.',
                null,
                null,
                'FYLCAD_E002'
            );
        }

        // Convertir a array de coordenadas
        $puntos = [];
        foreach ($puntosRaw as $p) {
            if (!isset($p->x) || !isset($p->y) || !isset($p->z)) {
                throw new SoapFault(
                    'Client',
                    'Punto invalido: cada punto debe tener x, y, z.',
                    null,
                    null,
                    'FYLCAD_E003'
                );
            }
            $puntos[] = [
                'x' => (float)$p->x,
                'y' => (float)$p->y,
                'z' => (float)$p->z,
            ];
        }

        // Calcular métricas
        $area     = round($this->calcularArea($puntos), 4);
        $volumen  = round($this->calcularVolumen($puntos), 4);
        $cotaMin  = min(array_column($puntos, 'z'));
        $cotaMax  = max(array_column($puntos, 'z'));
        $desnivel = round($cotaMax - $cotaMin, 4);

        error_log("[SOAP] Resultado: area={$area}, volumen={$volumen}");

        return [
            'estado'       => 'ok',
            'total_puntos' => count($puntos),
            'area_m2'      => $area,
            'volumen_m3'   => $volumen,
            'cota_min'     => $cotaMin,
            'cota_max'     => $cotaMax,
            'desnivel'     => $desnivel,
        ];
    }

    // ── Fórmula de Gauss (Shoelace) ───────────────────────
    private function calcularArea(array $puntos): float {
        $n = count($puntos); $area = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $area += $puntos[$i]['x'] * $puntos[$j]['y'];
            $area -= $puntos[$j]['x'] * $puntos[$i]['y'];
        }
        return abs($area) / 2.0;
    }

    private function calcularVolumen(array $puntos): float {
        $area     = $this->calcularArea($puntos);
        $cotaBase = min(array_column($puntos, 'z'));
        $altura   = (array_sum(array_column($puntos, 'z')) / count($puntos)) - $cotaBase;
        return $area * max($altura, 0);
    }
}

// ══════════════════════════════════════════════
// REGISTRO EN UDDI
// ══════════════════════════════════════════════
function registrarEnUDDI(string $ip, int $puerto, string $nombre, string $endpoint): void {
    $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$sock || !@socket_connect($sock, $ip, $puerto)) {
        error_log("[UDDI] Registry no disponible en {$ip}:{$puerto}");
        @socket_close($sock);
        return;
    }
    $msg = "BIND|{$nombre}|{$endpoint}|soap\n";
    socket_write($sock, $msg, strlen($msg));
    $resp = trim(socket_read($sock, 512, PHP_NORMAL_READ));
    socket_close($sock);
    error_log("[UDDI] Registro: {$resp}");
}

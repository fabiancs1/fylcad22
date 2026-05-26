<?php
/**
 * FYLCAD — Asistente Topográfico IA
 * Archivo: asistente.php
 * Ubicación: C:\xamppp\htdocs\FYLCAD\asistente.php
 *
 * Backend que recibe el mensaje del usuario,
 * construye el contexto de FYLCAD y llama
 * a la API de Anthropic.
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── Cargar configuración ───────────────────────────────────
require_once __DIR__ . '/config/db.php';

// API Key de Anthropic — usar variable de entorno o config
$apiKey = getenv('ANTHROPIC_API_KEY') ?: 'TU_API_KEY_AQUI';

// ── Recibir mensaje del usuario ────────────────────────────
$input   = json_decode(file_get_contents('php://input'), true);
$mensaje = trim($input['mensaje'] ?? '');
$historial = $input['historial'] ?? [];

if (empty($mensaje)) {
    echo json_encode(['error' => 'Mensaje vacío']);
    exit;
}

// ── Obtener contexto del usuario actual ────────────────────
$contexto = obtenerContextoUsuario();

// ── System Prompt personalizado para FYLCAD ────────────────
$systemPrompt = "
Eres FilBot, el asistente inteligente de FYLCAD — una plataforma SaaS 
de topografía digital desarrollada en PHP y MySQL.

QUIÉN ERES:
- Experto en topografía digital, levantamientos de campo y SIG
- Conoces a fondo FYLCAD: sus módulos, funciones y flujo de trabajo
- Respondes siempre en español, de forma clara y técnica

CONTEXTO DEL USUARIO ACTUAL:
- Nombre: {$contexto['nombre']}
- Plan: {$contexto['plan']}
- Proyectos procesados: {$contexto['total_proyectos']}
- Último proyecto: {$contexto['ultimo_proyecto']}

LO QUE SABES DE FYLCAD:
- Carga archivos CSV con coordenadas X, Y, Z
- Ejecuta triangulación Delaunay para generar red TIN
- Calcula área, volumen, perímetro, cota mínima, máxima y desnivel
- Genera visualización 3D interactiva del terreno
- Produce cotizaciones automáticas de obra (tierra, nivelación, cerramiento)
- Tiene plan Free (hasta 50 puntos) y Premium (ilimitado + PDF)
- Intermediación con proveedores de materiales y maquinaria

REGLAS:
1. Responde siempre en español
2. Sé conciso — máximo 3 párrafos por respuesta
3. Si el usuario tiene plan Free y pregunta por funciones Premium,
   menciona amablemente el upgrade
4. Si no sabes algo de topografía, dilo honestamente
5. Usa términos técnicos correctos: cota, planimetría, altimetría,
   triangulación, TIN, curvas de nivel, desnivel
6. Solo responde temas de topografía y FYLCAD
";

// ── Construir mensajes con historial ──────────────────────
$mensajes = [];
foreach ($historial as $h) {
    if (!empty($h['rol']) && !empty($h['contenido'])) {
        $mensajes[] = [
            'role'    => $h['rol'],
            'content' => $h['contenido'],
        ];
    }
}
$mensajes[] = [
    'role'    => 'user',
    'content' => $mensaje,
];

// ── Llamar a la API de Anthropic ──────────────────────────
$payload = [
    'model'      => 'claude-sonnet-4-20250514',
    'max_tokens' => 1024,
    'system'     => $systemPrompt,
    'messages'   => $mensajes,
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
]);

$respuesta = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['error' => 'Error al contactar la IA: ' . $httpCode]);
    exit;
}

$data = json_decode($respuesta, true);
$texto = $data['content'][0]['text'] ?? 'Sin respuesta';

echo json_encode([
    'respuesta' => $texto,
    'tokens'    => $data['usage']['output_tokens'] ?? 0,
]);

// ══════════════════════════════════════════════
// FUNCIÓN: Obtener contexto del usuario
// ══════════════════════════════════════════════
function obtenerContextoUsuario(): array {
    $default = [
        'nombre'          => 'Usuario',
        'plan'            => 'free',
        'total_proyectos' => 0,
        'ultimo_proyecto' => 'ninguno',
    ];

    if (!isset($_SESSION['usuario_id'])) return $default;

    try {
        $db  = getDB();
        $uid = (int)$_SESSION['usuario_id'];

        $u = $db->prepare("SELECT nombre, plan FROM usuarios WHERE id = ?");
        $u->execute([$uid]);
        $usuario = $u->fetch();

        $p = $db->prepare("
            SELECT COUNT(*) as total, 
                   MAX(nombre) as ultimo 
            FROM proyectos 
            WHERE usuario_id = ?
        ");
        $p->execute([$uid]);
        $proyectos = $p->fetch();

        return [
            'nombre'          => $usuario['nombre']   ?? 'Usuario',
            'plan'            => $usuario['plan']      ?? 'free',
            'total_proyectos' => $proyectos['total']   ?? 0,
            'ultimo_proyecto' => $proyectos['ultimo']  ?? 'ninguno',
        ];
    } catch (Exception $e) {
        return $default;
    }
}

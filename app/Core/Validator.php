<?php
/* =============================================
   FYLCAD — Capa de Validación
   Archivo: app/Core/Validator.php
   Intercepta y valida datos antes de que
   lleguen a la capa de negocio.
============================================= */

class Validator {

    // ── 1. Validar coordenadas X, Y, Z ──
    public static function coordenada($valor): bool {
        return filter_var($valor, FILTER_VALIDATE_FLOAT) !== false;
    }

    // ── 2. Validar que un conjunto de coordenadas esté completo ──
    public static function punto(array $punto): bool {
        return isset($punto['x'], $punto['y'], $punto['z'])
            && self::coordenada($punto['x'])
            && self::coordenada($punto['y'])
            && self::coordenada($punto['z']);
    }

    // ── 3. Validar archivo subido (solo CSV o TXT) ──
    public static function archivo(array $archivo): bool {
        $extensionesPermitidas = ['csv', 'txt'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $tipoMime  = mime_content_type($archivo['tmp_name']);
        $mimesPermitidos = ['text/plain', 'text/csv', 'application/csv'];

        return in_array($extension, $extensionesPermitidas)
            && in_array($tipoMime, $mimesPermitidos);
    }

    // ── 4. Validar email ──
    public static function email(string $email): bool {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    // ── 5. Validar contraseña (mínimo 8 caracteres) ──
    public static function password(string $password): bool {
        return strlen(trim($password)) >= 8;
    }

    // ── 6. Validar nombre de usuario o proyecto ──
    public static function nombre(string $texto): bool {
        $texto = trim($texto);
        return strlen($texto) >= 2
            && strlen($texto) <= 100
            && filter_var($texto, FILTER_SANITIZE_SPECIAL_CHARS) !== false;
    }

    // ── 7. Validar ID numérico entero positivo ──
    public static function id($valor): bool {
        return filter_var($valor, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]) !== false;
    }

    // ── 8. Limpiar texto para prevenir XSS ──
    public static function limpiarTexto(string $texto): string {
        return htmlspecialchars(strip_tags(trim($texto)), ENT_QUOTES, 'UTF-8');
    }

    // ── 9. Validar número positivo (para cotizaciones) ──
    public static function numeroPositivo($valor): bool {
        return filter_var($valor, FILTER_VALIDATE_FLOAT) !== false
            && (float)$valor > 0;
    }
}
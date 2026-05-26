<?php
/* =============================================
   FYLCAD — Verificación de sesión
   Archivo: config/auth_check.php

   USO: incluir al inicio de cualquier página
   que requiera estar logueado:

   require_once 'config/auth_check.php';
============================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    // Guardar la URL a la que intentaba acceder
    $ruta = $_SERVER['REQUEST_URI'] ?? '';
    if (!empty($ruta)) {
        $_SESSION['redirect_after_login'] = $ruta;
    }
    header('Location: login.php');
    exit;
}

// Verificar que el usuario sigue activo en la DB
// (por si fue desactivado mientras tenía sesión)
require_once __DIR__ . '/db.php';
$stmt = getDB()->prepare("SELECT activo, plan FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$check = $stmt->fetch();

if (!$check || !$check['activo']) {
    session_destroy();
    header('Location: login.php?razon=cuenta_desactivada');
    exit;
}

// Actualizar plan en sesión por si cambió
$_SESSION['usuario_plan'] = $check['plan'];
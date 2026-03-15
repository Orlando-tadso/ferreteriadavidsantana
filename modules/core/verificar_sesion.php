<?php
// Incluir config.php que inicia la sesión automáticamente
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

require_once __DIR__ . '/seguridad.php';

// Establecer headers de seguridad
establecerHeadersSeguridad();

// Verificar que haya sesión activa
if (!isset($_SESSION['usuario_id'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Validar integridad de la sesión
validarSesion();

function obtenerRolUsuario() {
    $rol = $_SESSION['usuario_rol'] ?? 'inspector';
    if (!in_array($rol, ['admin', 'inspector', 'user'], true)) {
        $rol = 'inspector';
    }
    return $rol;
}

function esAdmin() {
    return obtenerRolUsuario() === 'admin';
}

function requerirAdmin() {
    if (!esAdmin()) {
        header("Location: dashboard.php");
        exit;
    }
}
?>

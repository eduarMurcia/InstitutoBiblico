<?php
// ─────────────────────────────────────────
// Ministerio de Imprenta RV 1865 — LMS
// Manejo de sesiones y autenticación
// ─────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function esta_logueado() {
    return isset($_SESSION['usuario_id']);
}

function es_admin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

function requerir_login() {
    if (!esta_logueado()) {
        header('Location: ' . obtener_base() . '/login.php');
        exit;
    }
}

function requerir_admin() {
    if (!esta_logueado() || !es_admin()) {
        header('Location: ' . obtener_base() . '/dashboard.php');
        exit;
    }
}

function obtener_base() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $dir = dirname($_SERVER['SCRIPT_NAME']);
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
        $dir = dirname($dir);
    }
    $dir = ($dir === '/' || $dir === '\\') ? '' : $dir;
    return $protocol . '://' . $host . $dir;
}

function sanitizar($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function redirigir($url) {
    header('Location: ' . $url);
    exit;
}
?>

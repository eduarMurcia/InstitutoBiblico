<?php
require_once '../includes/auth.php';
session_destroy();
$base = (strpos($_SERVER['PHP_SELF'], '/api/') !== false) ? '../' : '';
header('Location: ' . $base . 'login.php');
exit;
?>

<?php
// Copia este archivo como config/db.php y completa tus credenciales

define('DB_HOST', 'localhost');
define('DB_USER', 'tu_usuario_db');
define('DB_PASS', 'tu_contraseña_db');
define('DB_NAME', 'tu_nombre_db');
define('SITE_URL', 'tudominio.com/lms');
define('SITE_NAME', 'Instituto Bíblico Bautista');
define('UPLOAD_PATH', __DIR__ . '/../uploads/audio/');
define('MAX_FILE_SIZE', 150 * 1024 * 1024); // 150MB

function conectar() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <title>Servicio no disponible</title>
        <style>body{font-family:sans-serif;background:#000a23;color:#f5f0e8;display:flex;align-items:center;justify-content:min-height:100vh;margin:0;flex-direction:column;gap:1rem}
        h2{color:#d19309}p{color:rgba(245,240,232,0.6);max-width:400px;text-align:center}</style>
        </head><body>
        <h2>✦ Instituto Bíblico Bautista</h2>
        <h3>Servicio temporalmente no disponible</h3>
        <p>Estamos realizando mantenimiento. Por favor inténtelo de nuevo en unos minutos.</p>
        </body></html>';
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

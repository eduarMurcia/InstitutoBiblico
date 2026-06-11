<?php
// ─────────────────────────────────────────
// Ministerio de Imprenta RV 1865 — LMS
// Servicio de audio protegido
// Los archivos en /uploads/audio/ no son
// accesibles directamente — pasan por aquí
// ─────────────────────────────────────────
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_login();

$lid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$lid) { http_response_code(404); exit('Archivo no encontrado.'); }

$conn = conectar();
$stmt = $conn->prepare("SELECT archivo_audio FROM lecciones WHERE id=?");
$stmt->bind_param("i", $lid);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$leccion || !$leccion['archivo_audio']) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$path = UPLOAD_PATH . $leccion['archivo_audio'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor.');
}

// Detectar tipo MIME
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$tipos = [
    'mp3'  => 'audio/mpeg',
    'm4a'  => 'audio/mp4',
    'wav'  => 'audio/wav',
    'ogg'  => 'audio/ogg',
    'aac'  => 'audio/aac',
];
$mime = $tipos[$ext] ?? 'audio/mpeg';
$size = filesize($path);

// Soporte de rangos para streaming (permite avanzar/retroceder en el audio)
$start = 0;
$end   = $size - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
    $start = (int)$matches[1];
    $end   = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : $end;
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
} else {
    http_response_code(200);
}

$length = $end - $start + 1;
header("Content-Type: $mime");
header("Content-Length: $length");
header("Accept-Ranges: bytes");
header("Cache-Control: no-cache");
header("X-Content-Type-Options: nosniff");

$fp = fopen($path, 'rb');
fseek($fp, $start);
$buffer = 8192;
$sent   = 0;
while (!feof($fp) && $sent < $length) {
    $read = min($buffer, $length - $sent);
    echo fread($fp, $read);
    $sent += $read;
    flush();
}
fclose($fp);
?>

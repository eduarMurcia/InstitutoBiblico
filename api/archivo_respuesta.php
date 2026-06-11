<?php
// ─────────────────────────────────────────
// Servicio de archivos de respuestas
// Solo el estudiante dueño o un admin puede descargar
// ─────────────────────────────────────────
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_login();

$rid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$rid) { http_response_code(404); exit(); }

$conn = conectar();
$uid  = $_SESSION['usuario_id'];

// Verificar que el archivo le pertenece al estudiante o es admin
$stmt = $conn->prepare("SELECT archivo_respuesta, usuario_id FROM respuestas_examen WHERE id=?");
$stmt->bind_param("i", $rid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close(); $conn->close();

if (!$row) { http_response_code(404); exit('No encontrado.'); }
if ($row['usuario_id'] !== $uid && !es_admin()) { http_response_code(403); exit('Sin acceso.'); }
if (!$row['archivo_respuesta']) { http_response_code(404); exit('Sin archivo.'); }

$path = __DIR__ . '/../uploads/respuestas/' . $row['archivo_respuesta'];
if (!file_exists($path)) { http_response_code(404); exit('Archivo no encontrado.'); }

$ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimes = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
          'png'=>'image/png','doc'=>'application/msword',
          'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($path)) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>

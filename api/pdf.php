<?php
// ─────────────────────────────────────────
// Servicio de PDF protegido — Lecciones
// ─────────────────────────────────────────
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_login();

$lid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$lid) { http_response_code(404); exit('No encontrado.'); }

$conn = conectar();
$stmt = $conn->prepare("SELECT archivo_pdf FROM lecciones WHERE id=?");
$stmt->bind_param("i", $lid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close(); $conn->close();

if (!$row || !$row['archivo_pdf']) { http_response_code(404); exit('Sin archivo.'); }

$pdf_path = __DIR__ . '/../uploads/pdf/' . $row['archivo_pdf'];
if (!file_exists($pdf_path)) { http_response_code(404); exit('Archivo no encontrado.'); }

$inline  = isset($_GET['ver']) ? 'inline' : 'attachment';
$nombre  = rawurlencode(basename($row['archivo_pdf']));
header('Content-Type: application/pdf');
header("Content-Disposition: $inline; filename=\"$nombre\"");
header('Content-Length: ' . filesize($pdf_path));
header('Cache-Control: private, max-age=3600');
readfile($pdf_path);
exit;
?>

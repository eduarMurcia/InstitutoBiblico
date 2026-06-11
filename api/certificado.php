<?php
// ─────────────────────────────────────────
// Ministerio de Imprenta RV 1865 — LMS
// api/certificado.php — Genera certificado PDF por curso completo
// ─────────────────────────────────────────
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requerir_login();

$conn     = conectar();
$uid      = $_SESSION['usuario_id'];
$curso_id = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;

if (!$curso_id) { http_response_code(400); die('Curso no especificado.'); }

// ── 1. Curso existe y está publicado ──
$s = $conn->prepare("SELECT id, titulo, instructor FROM cursos WHERE id=? AND publicado=1");
$s->bind_param("i", $curso_id); $s->execute();
$curso = $s->get_result()->fetch_assoc(); $s->close();
if (!$curso) { http_response_code(404); die('Curso no encontrado.'); }

// ── 2. Estudiante completó el 100% ──
$s = $conn->prepare("
    SELECT COUNT(DISTINCT l.id) AS total,
           COUNT(DISTINCT CASE WHEN p.completado=1 THEN p.leccion_id END) AS completadas
    FROM modulos m
    LEFT JOIN lecciones l ON l.modulo_id = m.id
    LEFT JOIN progreso p  ON p.leccion_id = l.id AND p.usuario_id = ?
    WHERE m.curso_id = ?
");
$s->bind_param("ii", $uid, $curso_id); $s->execute();
$prog = $s->get_result()->fetch_assoc(); $s->close();

if ($prog['total'] == 0 || $prog['completadas'] < $prog['total']) {
    http_response_code(403);
    die('Debes completar todas las lecciones del curso para obtener el certificado.');
}

// ── 3. Obtener o crear registro (1 por curso por estudiante) ──
$s = $conn->prepare("SELECT id, numero, fecha FROM certificados WHERE usuario_id=? AND curso_id=?");
$s->bind_param("ii", $uid, $curso_id); $s->execute();
$cert = $s->get_result()->fetch_assoc(); $s->close();

if (!$cert) {
    $r = $conn->query("SELECT COUNT(*) AS n FROM certificados WHERE YEAR(fecha)=YEAR(NOW())");
    $n = $r->fetch_assoc()['n'] + 1;
    $numero = date('Y') . '-' . str_pad($n, 4, '0', STR_PAD_LEFT);
    $s = $conn->prepare("INSERT INTO certificados (usuario_id, curso_id, numero) VALUES (?,?,?)");
    $s->bind_param("iis", $uid, $curso_id, $numero);
    $s->execute();
    $cert_id = $conn->insert_id; $s->close();
    $cert = ['id' => $cert_id, 'numero' => $numero, 'fecha' => date('Y-m-d H:i:s')];
}

// ── 4. Datos del estudiante ──
$s = $conn->prepare("SELECT nombre FROM usuarios WHERE id=?");
$s->bind_param("i", $uid); $s->execute();
$usuario = $s->get_result()->fetch_assoc(); $s->close();
$conn->close();

// ── 5. Preparar datos ──
require_once __DIR__ . '/../fpdf.php';

function fecha_es($ts) {
    $m = ['enero','febrero','marzo','abril','mayo','junio',
          'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return intval(date('d',$ts)).' de '.$m[intval(date('m',$ts))-1].' de '.date('Y',$ts);
}

// FPDF no soporta UTF-8 nativo; convertimos a windows-1252
function u($str) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $str);
}

$nombre_est = $usuario['nombre'];
$nombre_cur = $curso['titulo'];
$instructor = $curso['instructor'] ?: 'Instituto Biblico Bautista';
$fecha_str  = fecha_es(strtotime($cert['fecha']));
$numero     = $cert['numero'];

// ── 6. Clase PDF ──
class CertificadoPDF extends FPDF {

    function hex($h) {
        $h = ltrim($h,'#');
        return [hexdec(substr($h,0,2)), hexdec(substr($h,2,2)), hexdec(substr($h,4,2))];
    }

    function Ellipse($x,$y,$rx,$ry,$style='D') {
        $op = ($style=='F') ? 'f' : (($style=='FD'||$style=='DF') ? 'b' : 's');
        $lx=4/3*(M_SQRT2-1)*$rx; $ly=4/3*(M_SQRT2-1)*$ry;
        $k=$this->k; $h=$this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx)*$k,($h-$y)*$k,($x+$rx)*$k,($h-($y-$ly))*$k,
            ($x+$lx)*$k,($h-($y-$ry))*$k,$x*$k,($h-($y-$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$lx)*$k,($h-($y-$ry))*$k,($x-$rx)*$k,($h-($y-$ly))*$k,
            ($x-$rx)*$k,($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$rx)*$k,($h-($y+$ly))*$k,($x-$lx)*$k,($h-($y+$ry))*$k,
            $x*$k,($h-($y+$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x+$lx)*$k,($h-($y+$ry))*$k,($x+$rx)*$k,($h-($y+$ly))*$k,
            ($x+$rx)*$k,($h-$y)*$k,$op));
    }

    function Circle($x,$y,$r,$style='D') { $this->Ellipse($x,$y,$r,$r,$style); }

    function DrawCertificate($nombre, $curso, $instructor, $fecha, $numero) {
        $this->AddPage('L','A4');
        $W = $this->GetPageWidth();
        $H = $this->GetPageHeight();
        $cx = $W / 2;

        // Fondo crema
        [$r,$g,$b] = $this->hex('f5f0e8');
        $this->SetFillColor($r,$g,$b);
        $this->Rect(0,0,$W,$H,'F');

        // Borde exterior navy
        [$r,$g,$b] = $this->hex('000a23');
        $this->SetDrawColor($r,$g,$b);
        $this->SetLineWidth(3);
        $this->Rect(8,8,$W-16,$H-16,'D');

        // Borde dorado interior
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetDrawColor($r,$g,$b);
        $this->SetLineWidth(0.7);
        $this->Rect(12,12,$W-24,$H-24,'D');

        // Cabecera navy
        [$r,$g,$b] = $this->hex('000a23');
        $this->SetFillColor($r,$g,$b);
        $this->Rect(12,12,$W-24,44,'F');

        // Línea dorada bajo cabecera
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetDrawColor($r,$g,$b);
        $this->SetLineWidth(0.8);
        $this->Line(12,56,$W-12,56);

        // "Instituto Bíblico Bautista"
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','B',22);
        $this->SetXY(0,18);
        $this->Cell($W,0,u('Instituto Bíblico Bautista'),0,0,'C');

        // "Reina-Valera 1865"
        [$r,$g,$b] = $this->hex('F3C332');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','I',10.5);
        $this->SetXY(0,33);
        $this->Cell($W,0,'Reina-Valera 1865',0,0,'C');

        // Título del documento
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','B',11);
        $this->SetXY(0,62);
        $this->Cell($W,0,'C  E  R  T  I  F  I  C  A  D  O    D  E    C  U  R  S  O',0,0,'C');

        // Separadores decorativos
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetDrawColor($r,$g,$b);
        $this->SetLineWidth(0.4);
        $this->Line($cx-55,71,$cx-6,71);
        $this->Line($cx+6,71,$cx+55,71);

        // "Por la presente se certifica..."
        [$r,$g,$b] = $this->hex('555555');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','I',13);
        $this->SetXY(0,78);
        $this->Cell($W,0,'Por la presente se certifica que el estudiante',0,0,'C');

        // NOMBRE DEL ESTUDIANTE
        [$r,$g,$b] = $this->hex('000a23');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','B',30);
        $this->SetXY(0,91);
        $this->Cell($W,0,u($nombre),0,0,'C');

        // Línea decorativa bajo el nombre
        $nm_w = $this->GetStringWidth(u($nombre));
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetDrawColor($r,$g,$b);
        $this->SetLineWidth(0.5);
        $this->Line($cx-$nm_w/2,107,$cx+$nm_w/2,107);

        // "ha completado satisfactoriamente el curso"
        [$r,$g,$b] = $this->hex('555555');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','I',13);
        $this->SetXY(0,114);
        $this->Cell($W,0,'ha completado satisfactoriamente el curso',0,0,'C');

        // NOMBRE DEL CURSO
        [$r,$g,$b] = $this->hex('000a23');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','B',17);
        $this->SetXY(0,126);
        $this->Cell($W,0,u('"'.$curso.'"'),0,0,'C');

        // "Otorgado por: [instructor]"
        [$r,$g,$b] = $this->hex('444444');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','I',11);
        $this->SetXY(0,142);
        $label = u('Otorgado por: '.$instructor);
        $this->Cell($W,0,$label,0,0,'C');

        // Línea bajo instructor
        $lw = $this->GetStringWidth($label) + 12;
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetDrawColor($r,$g,$b);
        $this->SetLineWidth(0.35);
        $this->Line($cx-$lw/2,149,$cx+$lw/2,149);

        // Separador pie
        $this->SetLineWidth(0.5);
        $this->Line(25,$H-36,$W-25,$H-36);

        // Fecha y número
        [$r,$g,$b] = $this->hex('666666');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','',9);
        $this->SetXY(25,$H-31);
        $this->Cell(90,0,u('Fecha de expedición: '.$fecha),0,0,'L');
        $this->SetXY($W-115,$H-31);
        $this->Cell(90,0,u('N° '.$numero),0,0,'R');

        // Versículo
        $this->SetFont('Times','I',10);
        $this->SetXY(0,$H-22);
        $this->Cell($W,0,u('"Escudriñad las Escrituras" — Juan 5:39'),0,0,'C');

        // Sello circular
        [$r,$g,$b] = $this->hex('000a23');
        $this->SetFillColor($r,$g,$b);
        $sx=$W-38; $sy=$H-33; $sr=16;
        $this->Circle($sx,$sy,$sr,'F');
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetDrawColor($r,$g,$b);
        $this->SetLineWidth(0.6);
        $this->Circle($sx,$sy,$sr,'D');
        $this->Circle($sx,$sy,$sr-2.5,'D');
        [$r,$g,$b] = $this->hex('d19309');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','B',6.5);
        $this->SetXY($sx-16,$sy-5); $this->Cell(32,4,'INSTITUTO',0,0,'C');
        $this->SetXY($sx-16,$sy-1); $this->Cell(32,4,u('BÍBLICO'),0,0,'C');
        $this->SetFont('Times','I',6);
        $this->SetXY($sx-16,$sy+3); $this->Cell(32,4,'BAUTISTA',0,0,'C');
    }
}

$pdf = new CertificadoPDF();
$pdf->SetTitle(u('Certificado - '.$nombre_cur));
$pdf->SetAuthor(u('Instituto Bíblico Bautista'));
$pdf->DrawCertificate($nombre_est, $nombre_cur, $instructor, $fecha_str, $numero);

$filename = 'Certificado_'.preg_replace('/[^a-zA-Z0-9]/', '_', $nombre_cur).'.pdf';
$pdf->Output('D', $filename);

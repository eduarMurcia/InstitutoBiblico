<?php
// api/examen_imprimible.php
// Genera un PDF del examen con las preguntas para responder en papel
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../fpdf.php';
requerir_login();

$conn = conectar();
$uid  = $_SESSION['usuario_id'];
$eid  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$eid) { http_response_code(400); die('Examen no especificado.'); }

$s = $conn->prepare("SELECT e.*, m.titulo AS modulo, c.titulo AS curso, c.instructor
    FROM examenes e
    JOIN modulos m ON m.id=e.modulo_id
    JOIN cursos c ON c.id=m.curso_id
    WHERE e.id=?");
$s->bind_param("i", $eid); $s->execute();
$examen = $s->get_result()->fetch_assoc(); $s->close();
if (!$examen) { http_response_code(404); die('Examen no encontrado.'); }

$s = $conn->prepare("SELECT * FROM preguntas WHERE examen_id=? ORDER BY orden");
$s->bind_param("i", $eid); $s->execute();
$preguntas = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();

$s = $conn->prepare("SELECT nombre FROM usuarios WHERE id=?");
$s->bind_param("i", $uid); $s->execute();
$usuario = $s->get_result()->fetch_assoc(); $s->close();
$conn->close();

function u($str) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $str ?: '');
}

function wrap_text(FPDF $pdf, string $text, float $maxW): array {
    // Split text into lines that fit maxW
    $words = explode(' ', $text);
    $lines = []; $line = '';
    foreach ($words as $word) {
        $test = $line ? $line . ' ' . $word : $word;
        if ($pdf->GetStringWidth($test) <= $maxW) {
            $line = $test;
        } else {
            if ($line) $lines[] = $line;
            $line = $word;
        }
    }
    if ($line) $lines[] = $line;
    return $lines;
}

class ExamenPDF extends FPDF {
    function hex($h) {
        $h = ltrim($h,'#');
        return [hexdec(substr($h,0,2)), hexdec(substr($h,2,2)), hexdec(substr($h,4,2))];
    }

    function Header() {}
    function Footer() {
        $this->SetY(-12);
        [$r,$g,$b] = $this->hex('999999');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','I',8);
        $this->Cell(0,10,u('Instituto Bíblico Bautista — Página ').$this->PageNo(),0,0,'C');
    }
}

$pdf = new ExamenPDF('P','mm','A4');
$pdf->SetMargins(20,20,20);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$W   = $pdf->GetPageWidth() - 40; // usable width
$cx  = $pdf->GetPageWidth() / 2;

// ── Encabezado ──
[$r,$g,$b] = $pdf->hex('000a23');
$pdf->SetFillColor($r,$g,$b);
$pdf->Rect(0,0,$pdf->GetPageWidth(),28,'F');

[$r,$g,$b] = $pdf->hex('d19309');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',16);
$pdf->SetXY(0,7);
$pdf->Cell($pdf->GetPageWidth(),0,u('Instituto Bíblico Bautista'),0,0,'C');

[$r,$g,$b] = $pdf->hex('F3C332');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','I',9);
$pdf->SetXY(0,17);
$pdf->Cell($pdf->GetPageWidth(),0,'Reina-Valera 1865',0,0,'C');

// ── Datos del examen ──
$pdf->SetY(34);
[$r,$g,$b] = $pdf->hex('000a23');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',14);
$pdf->Cell($W,0,u($examen['titulo']),0,1,'L');

$pdf->SetFont('Times','',10);
[$r,$g,$b] = $pdf->hex('555555');
$pdf->SetTextColor($r,$g,$b);
$pdf->Cell($W/2,6,u('Módulo: '.$examen['modulo']),0,0,'L');
$pdf->Cell($W/2,6,u('Curso: '.$examen['curso']),0,1,'R');

// ── Datos del estudiante ──
[$r,$g,$b] = $pdf->hex('d19309');
$pdf->SetDrawColor($r,$g,$b);
$pdf->SetLineWidth(0.3);
$pdf->Line(20, $pdf->GetY()+2, $pdf->GetPageWidth()-20, $pdf->GetY()+2);
$pdf->Ln(5);

[$r,$g,$b] = $pdf->hex('333333');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','',10);
$pdf->Cell($W/2, 7, u('Nombre: '.$usuario['nombre']), 'B', 0, 'L');
$pdf->Cell($W/4, 7, u('Fecha: '.date('d/m/Y')), 'B', 0, 'L');
$pdf->Cell($W/4, 7, 'Firma: ___________', 'B', 1, 'L');
$pdf->Ln(5);

if ($examen['descripcion']) {
    [$r,$g,$b] = $pdf->hex('555555');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','I',10);
    $pdf->MultiCell($W, 5, u($examen['descripcion']), 0, 'L');
    $pdf->Ln(3);
}

// ── Instrucciones ──
[$r,$g,$b] = $pdf->hex('fdf3d0');
$pdf->SetFillColor($r,$g,$b);
[$r,$g,$b] = $pdf->hex('8a6000');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','I',9);
$pdf->MultiCell($W, 5,
    u('Instrucciones: Responde cada pregunta con claridad. '.
      'Al terminar, toma una foto de este formulario completado o escanéalo y '.
      'súbelo en la plataforma bajo la sección de entrega de archivos del examen.'),
    1, 'L', true);
$pdf->Ln(6);

// ── Preguntas ──
$etiquetas = [
    'multiple'       => 'Selección múltiple',
    'verdadero_falso'=> 'Verdadero / Falso',
    'abierta'        => 'Pregunta abierta',
    'archivo'        => 'Entrega de archivo',
];

foreach ($preguntas as $i => $p) {
    $tipo = $p['tipo'] ?? 'multiple';

    // Número y tipo
    [$r,$g,$b] = $pdf->hex('000a23');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','B',11);
    $num = ($i+1).'. ';
    $pdf->Cell($pdf->GetStringWidth($num)+1, 6, $num, 0, 0, 'L');

    [$r,$g,$b] = $pdf->hex('555555');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','',8);
    $pdf->Cell(0, 6, u('['.$etiquetas[$tipo].']'), 0, 1, 'L');

    // Texto de la pregunta
    [$r,$g,$b] = $pdf->hex('111111');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','',11);
    $pdf->SetX(20);
    $pdf->MultiCell($W, 6, u($p['pregunta']), 0, 'L');

    $pdf->Ln(1);

    if ($tipo === 'multiple') {
        $opciones = ['a'=>$p['opcion_a'],'b'=>$p['opcion_b'],'c'=>$p['opcion_c'],'d'=>$p['opcion_d']];
        foreach ($opciones as $letra => $texto) {
            if (!$texto) continue;
            $pdf->SetX(24);
            [$r,$g,$b] = $pdf->hex('000a23');
            $pdf->SetTextColor($r,$g,$b);
            $pdf->SetFont('Times','B',10);
            // Checkbox dibujado a mano
            $pdf->Rect($pdf->GetX(), $pdf->GetY()+1.5, 4, 4);
            $pdf->SetX($pdf->GetX()+6);
            $pdf->SetFont('Times','',10);
            [$r,$g,$b] = $pdf->hex('222222');
            $pdf->SetTextColor($r,$g,$b);
            $pdf->MultiCell($W-10, 5.5, u(strtoupper($letra).'. '.$texto), 0, 'L');
        }
    } elseif ($tipo === 'verdadero_falso') {
        $pdf->SetX(24);
        $pdf->Rect($pdf->GetX(), $pdf->GetY()+1.5, 4, 4);
        $pdf->SetX($pdf->GetX()+6);
        $pdf->SetFont('Times','',10);
        [$r,$g,$b] = $pdf->hex('222222');
        $pdf->SetTextColor($r,$g,$b);
        $pdf->Cell(30, 5.5, 'Verdadero', 0, 0);
        $pdf->SetX($pdf->GetX()+8);
        $pdf->Rect($pdf->GetX(), $pdf->GetY()+1.5, 4, 4);
        $pdf->SetX($pdf->GetX()+6);
        $pdf->Cell(30, 5.5, 'Falso', 0, 1);
    } elseif ($tipo === 'abierta') {
        // Líneas para escribir
        for ($l=0; $l<5; $l++) {
            [$r,$g,$b] = $pdf->hex('cccccc');
            $pdf->SetDrawColor($r,$g,$b);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(24, $pdf->GetY()+6, $pdf->GetPageWidth()-20, $pdf->GetY()+6);
            $pdf->Ln(7);
        }
        [$r,$g,$b] = $pdf->hex('000000');
        $pdf->SetDrawColor($r,$g,$b);
    } elseif ($tipo === 'archivo') {
        [$r,$g,$b] = $pdf->hex('777777');
        $pdf->SetTextColor($r,$g,$b);
        $pdf->SetFont('Times','I',9);
        $pdf->SetX(24);
        $pdf->MultiCell($W-4, 5, u('Esta pregunta requiere subir un archivo (PDF, imagen, Word) en la plataforma.'), 0, 'L');
    }

    $pdf->Ln(4);

    // Separador entre preguntas
    if ($i < count($preguntas)-1) {
        [$r,$g,$b] = $pdf->hex('e8e0d0');
        $pdf->SetDrawColor($r,$g,$b);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(20, $pdf->GetY(), $pdf->GetPageWidth()-20, $pdf->GetY());
        $pdf->Ln(4);
        [$r,$g,$b] = $pdf->hex('000000');
        $pdf->SetDrawColor($r,$g,$b);
    }
}

// ── Pie del examen ──
$pdf->Ln(4);
[$r,$g,$b] = $pdf->hex('d19309');
$pdf->SetDrawColor($r,$g,$b);
$pdf->SetLineWidth(0.5);
$pdf->Line(20, $pdf->GetY(), $pdf->GetPageWidth()-20, $pdf->GetY());
$pdf->Ln(4);
[$r,$g,$b] = $pdf->hex('555555');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','I',9);
$pdf->Cell(0,0,u('"Escudriñad las Escrituras" — Juan 5:39'),0,0,'C');

$filename = 'Examen_'.preg_replace('/[^a-zA-Z0-9]/', '_', $examen['titulo']).'.pdf';
$pdf->Output('D', $filename);

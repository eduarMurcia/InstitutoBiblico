<?php
// api/historial_pdf.php — Constancia de historial académico del estudiante
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../fpdf.php';
requerir_login();

$conn = conectar();
$uid  = $_SESSION['usuario_id'];

// Datos del estudiante
$s = $conn->prepare("SELECT nombre, apellido, email, created_at FROM usuarios WHERE id=?");
$s->bind_param("i", $uid); $s->execute();
$u = $s->get_result()->fetch_assoc(); $s->close();
$u['nombre_completo'] = trim($u['nombre'].' '.($u['apellido'] ?? ''));

// Histórico académico por curso
require_once __DIR__ . '/../includes/historico.php';
$historico = historico_por_curso($conn, $uid);
$res_hist  = historico_resumen($historico);

// Certificados obtenidos
$s = $conn->prepare("
    SELECT c.titulo AS curso, ce.numero, ce.fecha
    FROM certificados ce JOIN cursos c ON c.id=ce.curso_id
    WHERE ce.usuario_id=? ORDER BY ce.fecha DESC
");
$s->bind_param("i", $uid); $s->execute();
$certs = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();

// Stats
$total     = $res_hist['examenes_presentados'];
$aprobados = $res_hist['examenes_aprobados'];
$promedio  = $res_hist['promedio'];

$conn->close();

// ── Funciones helper ──
function u($s) { return iconv('UTF-8','windows-1252//TRANSLIT//IGNORE', $s ?? ''); }
function fecha_es($ts) {
    $m = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return intval(date('d',$ts)).' de '.$m[intval(date('m',$ts))-1].' de '.date('Y',$ts);
}

// ── PDF ──
class HistorialPDF extends FPDF {
    function hex($h) {
        $h = ltrim($h,'#');
        return [hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2))];
    }
    function Ellipse($x,$y,$rx,$ry,$style='D') {
        $op=($style=='F')?'f':(($style=='FD'||$style=='DF')?'b':'s');
        $lx=4/3*(M_SQRT2-1)*$rx; $ly=4/3*(M_SQRT2-1)*$ry;
        $k=$this->k; $h=$this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx)*$k,($h-$y)*$k,($x+$rx)*$k,($h-($y-$ly))*$k,
            ($x+$lx)*$k,($h-($y-$ry))*$k,$x*$k,($h-($y-$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$lx)*$k,($h-($y-$ry))*$k,($x-$rx)*$k,($h-($y-$ly))*$k,($x-$rx)*$k,($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$rx)*$k,($h-($y+$ly))*$k,($x-$lx)*$k,($h-($y+$ry))*$k,$x*$k,($h-($y+$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x+$lx)*$k,($h-($y+$ry))*$k,($x+$rx)*$k,($h-($y+$ly))*$k,($x+$rx)*$k,($h-$y)*$k,$op));
    }
    function Circle($x,$y,$r,$style='D') { $this->Ellipse($x,$y,$r,$r,$style); }
    function Header() {}
    function Footer() {
        $this->SetY(-12);
        [$r,$g,$b]=$this->hex('999999');
        $this->SetTextColor($r,$g,$b);
        $this->SetFont('Times','I',8);
        $this->Cell(0,10,u('Instituto Bíblico Bautista — Página ').$this->PageNo().' de {nb}',0,0,'C');
    }
}

$pdf = new HistorialPDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->SetMargins(20,20,20);
$pdf->SetAutoPageBreak(true,20);
$pdf->AddPage();

$W = $pdf->GetPageWidth()-40;

// ── Cabecera ──
[$r,$g,$b]=$pdf->hex('000a23');
$pdf->SetFillColor($r,$g,$b);
$pdf->Rect(0,0,$pdf->GetPageWidth(),32,'F');
[$r,$g,$b]=$pdf->hex('d19309');
$pdf->SetDrawColor($r,$g,$b);
$pdf->SetLineWidth(1.5);
$pdf->Line(0,32,$pdf->GetPageWidth(),32);

[$r,$g,$b]=$pdf->hex('d19309');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',16);
$pdf->SetXY(0,8);
$pdf->Cell($pdf->GetPageWidth(),0,u('Instituto Bíblico Bautista'),0,0,'C');
[$r,$g,$b]=$pdf->hex('F3C332');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','I',9);
$pdf->SetXY(0,20);
$pdf->Cell($pdf->GetPageWidth(),0,'Reina-Valera 1865',0,0,'C');

// ── Título del documento ──
$pdf->SetY(40);
[$r,$g,$b]=$pdf->hex('000a23');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',14);
$pdf->Cell($W,0,u('CONSTANCIA DE HISTORIAL ACADÉMICO'),0,1,'C');

[$r,$g,$b]=$pdf->hex('d19309');
$pdf->SetDrawColor($r,$g,$b);
$pdf->SetLineWidth(0.5);
$pdf->Line(20,$pdf->GetY()+3,$pdf->GetPageWidth()-20,$pdf->GetY()+3);
$pdf->Ln(8);

// ── Datos del estudiante ──
[$r,$g,$b]=$pdf->hex('000a23');
$pdf->SetFillColor($r,$g,$b);
$pdf->Rect(20,$pdf->GetY(),$W,26,'F');
$ybox = $pdf->GetY()+4;

[$r,$g,$b]=$pdf->hex('d19309');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',7.5);
$pdf->SetXY(24,$ybox);
$pdf->Cell(0,0,u('ESTUDIANTE'),0,1,'L');

[$r,$g,$b]=$pdf->hex('f5f0e8');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',12);
$pdf->SetXY(24,$ybox+5);
$pdf->Cell($W/2,0,u($u['nombre_completo']),0,0,'L');

[$r,$g,$b]=$pdf->hex('F3C332');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','I',8.5);
$pdf->SetXY(24,$ybox+13);
$pdf->Cell($W/2,0,u($u['email']),0,0,'L');

[$r,$g,$b]=$pdf->hex('d19309');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','',8);
$pdf->SetXY($pdf->GetPageWidth()/2,$ybox+5);
$pdf->Cell($W/2-4,0,u('Fecha de expedición: '.fecha_es(time())),0,0,'R');
$pdf->SetXY($pdf->GetPageWidth()/2,$ybox+13);
$pdf->Cell($W/2-4,0,u('Miembro desde: '.fecha_es(strtotime($u['created_at']))),0,0,'R');
$pdf->SetY($pdf->GetY()+30);

// ── Estadísticas resumen ──
$pdf->Ln(2);
$cw = ($W-6)/3;
$stats = [
    [u('Cursos completados'), $res_hist['cursos_completados']],
    [u('Exámenes aprobados'), $aprobados.'/'.$total],
    [u('Promedio general'),   $promedio.'%'],
];
$sx = 20;
foreach ($stats as [$lbl,$val]) {
    [$r,$g,$b]=$pdf->hex('fdf3d0');
    $pdf->SetFillColor($r,$g,$b);
    [$r,$g,$b]=$pdf->hex('d19309');
    $pdf->SetDrawColor($r,$g,$b);
    $pdf->SetLineWidth(0.3);
    $pdf->Rect($sx,$pdf->GetY(),$cw,18,'FD');

    [$r,$g,$b]=$pdf->hex('000a23');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','B',14);
    $pdf->SetXY($sx,$pdf->GetY()+2);
    $pdf->Cell($cw,0,$val,0,0,'C');

    [$r,$g,$b]=$pdf->hex('8a6000');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','',7);
    $pdf->SetXY($sx,$pdf->GetY()+10);
    $pdf->Cell($cw,0,$lbl,0,0,'C');

    $sx += $cw+3;
}
$pdf->Ln(22);

// ── Histórico de notas por curso ──
$pdf->Ln(4);
[$r,$g,$b]=$pdf->hex('000a23');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',11);
$pdf->Cell($W,0,u('Histórico de notas por curso'),0,1,'L');
$pdf->Ln(3);

// Encabezado tabla
[$r,$g,$b]=$pdf->hex('000a23');
$pdf->SetFillColor($r,$g,$b);
$pdf->Rect(20,$pdf->GetY(),$W,8,'F');
[$r,$g,$b]=$pdf->hex('d19309');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','B',8);
$pdf->SetX(20);
$pdf->Cell($W*0.40,8,u('Curso / Examen'),0,0,'L');
$pdf->Cell($W*0.16,8,u('Lecciones'),0,0,'C');
$pdf->Cell($W*0.16,8,u('Exámenes'),0,0,'C');
$pdf->Cell($W*0.13,8,'Nota',0,0,'C');
$pdf->Cell($W*0.15,8,u('Estado'),0,1,'C');

foreach ($historico as $h) {
    if ($pdf->GetY() > 248) { $pdf->AddPage(); $pdf->Ln(5); }

    // ── Fila de curso ──
    [$r,$g,$b]=$pdf->hex('e4eff6');
    $pdf->SetFillColor($r,$g,$b);
    $pdf->Rect(20,$pdf->GetY(),$W,8,'F');

    [$r,$g,$b]=$pdf->hex('000a23');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','B',9);
    $pdf->SetX(20);
    $pdf->Cell($W*0.40,8,u(mb_strimwidth($h['curso'],0,42,'…')),0,0,'L');

    $pdf->SetFont('Times','',8.5);
    $pdf->Cell($W*0.16,8,$h['lecciones_comp'].'/'.$h['total_lecciones'],0,0,'C');
    $pdf->Cell($W*0.16,8,$h['presentados'].'/'.$h['total_examenes'],0,0,'C');

    if ($h['nota'] !== null) {
        [$r,$g,$b] = $h['estado']==='Reprobado' ? $pdf->hex('c0392b') : $pdf->hex('000a23');
        $pdf->SetTextColor($r,$g,$b);
        $pdf->SetFont('Times','B',9.5);
        $pdf->Cell($W*0.13,8,$h['nota'].'%',0,0,'C');
    } else {
        $pdf->Cell($W*0.13,8,u('—'),0,0,'C');
    }

    if ($h['estado']==='Completado')      { [$r,$g,$b]=$pdf->hex('1e7e45'); }
    elseif ($h['estado']==='Reprobado')   { [$r,$g,$b]=$pdf->hex('c0392b'); }
    else                                  { [$r,$g,$b]=$pdf->hex('5b86b4'); }
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','B',8);
    $pdf->Cell($W*0.15,8,u($h['estado']),0,1,'C');

    // ── Sub-filas: exámenes presentados del curso ──
    $odd = true;
    foreach ($h['examenes'] as $e) {
        if ((int)$e['intentos'] === 0) continue;
        if ($pdf->GetY() > 252) { $pdf->AddPage(); $pdf->Ln(5); }

        [$r,$g,$b] = $odd ? $pdf->hex('ffffff') : $pdf->hex('faf7f0');
        $pdf->SetFillColor($r,$g,$b);
        $pdf->Rect(20,$pdf->GetY(),$W,7,'F');

        [$r,$g,$b]=$pdf->hex('4a4560');
        $pdf->SetTextColor($r,$g,$b);
        $pdf->SetFont('Times','',8);
        $pdf->SetX(20);
        $pdf->Cell($W*0.40,7,u('    · '.mb_strimwidth($e['examen'],0,38,'…')),0,0,'L');

        [$r,$g,$b]=$pdf->hex('8a8699');
        $pdf->SetTextColor($r,$g,$b);
        $pdf->Cell($W*0.16,7,$e['ultima_fecha'] ? date('d/m/Y',strtotime($e['ultima_fecha'])) : '',0,0,'C');
        $pdf->Cell($W*0.16,7,u($e['intentos'].' intento'.($e['intentos']>1?'s':'')),0,0,'C');

        $pend = (int)$e['pendientes'] > 0 && !$e['aprobado'];
        if ($pend) {
            $pdf->Cell($W*0.13,7,u('—'),0,0,'C');
            [$r,$g,$b]=$pdf->hex('8a6000');
            $pdf->SetTextColor($r,$g,$b);
            $pdf->Cell($W*0.15,7,u('Pendiente'),0,1,'C');
        } elseif ($e['aprobado']) {
            [$r,$g,$b]=$pdf->hex('5b86b4');
            $pdf->SetTextColor($r,$g,$b);
            $pdf->SetFont('Times','B',8.5);
            $pdf->Cell($W*0.13,7,round($e['mejor'],1).'%',0,0,'C');
            [$r,$g,$b]=$pdf->hex('1e7e45');
            $pdf->SetTextColor($r,$g,$b);
            $pdf->SetFont('Times','',8);
            $pdf->Cell($W*0.15,7,u('Aprobado'),0,1,'C');
        } else {
            [$r,$g,$b]=$pdf->hex('c0392b');
            $pdf->SetTextColor($r,$g,$b);
            $pdf->SetFont('Times','B',8.5);
            $pdf->Cell($W*0.13,7,$e['mejor'] !== null ? round($e['mejor'],1).'%' : u('—'),0,0,'C');
            $pdf->SetFont('Times','',8);
            $pdf->Cell($W*0.15,7,u('No aprobado'),0,1,'C');
        }

        [$r,$g,$b]=$pdf->hex('e8e2d4');
        $pdf->SetDrawColor($r,$g,$b);
        $pdf->SetLineWidth(0.1);
        $pdf->Line(20,$pdf->GetY(),$pdf->GetPageWidth()-20,$pdf->GetY());
        $odd = !$odd;
    }
}

// Nota aclaratoria
$pdf->Ln(3);
[$r,$g,$b]=$pdf->hex('8a8699');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','I',7);
$pdf->Cell($W,0,u('La nota de cada examen corresponde al mejor intento calificado; la del curso, al promedio de sus exámenes presentados.'),0,1,'L');

// ── Certificados ──
if (!empty($certs)) {
    if ($pdf->GetY() > 230) $pdf->AddPage();
    $pdf->Ln(8);
    [$r,$g,$b]=$pdf->hex('000a23');
    $pdf->SetTextColor($r,$g,$b);
    $pdf->SetFont('Times','B',11);
    $pdf->Cell($W,0,u('Certificados obtenidos'),0,1,'L');
    $pdf->Ln(3);

    foreach ($certs as $cert) {
        [$r,$g,$b]=$pdf->hex('fdf3d0');
        $pdf->SetFillColor($r,$g,$b);
        [$r,$g,$b]=$pdf->hex('d19309');
        $pdf->SetDrawColor($r,$g,$b);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(20,$pdf->GetY(),$W,10,'FD');

        [$r,$g,$b]=$pdf->hex('000a23');
        $pdf->SetTextColor($r,$g,$b);
        $pdf->SetFont('Times','B',9);
        $pdf->SetX(24);
        $pdf->Cell($W*0.55,10,u($cert['curso']),0,0,'L');

        [$r,$g,$b]=$pdf->hex('8a6000');
        $pdf->SetTextColor($r,$g,$b);
        $pdf->SetFont('Times','',8);
        $pdf->Cell($W*0.25,10,u('N° '.$cert['numero']),0,0,'C');
        $pdf->Cell($W*0.20,10,date('d/m/Y',strtotime($cert['fecha'])),0,1,'R');
    }
}

// ── Pie de autenticidad ──
$pdf->Ln(10);
[$r,$g,$b]=$pdf->hex('d19309');
$pdf->SetDrawColor($r,$g,$b);
$pdf->SetLineWidth(0.4);
$pdf->Line(20,$pdf->GetY(),$pdf->GetPageWidth()-20,$pdf->GetY());
$pdf->Ln(4);
[$r,$g,$b]=$pdf->hex('8a8699');
$pdf->SetTextColor($r,$g,$b);
$pdf->SetFont('Times','I',8.5);
$pdf->Cell($W,0,u('Documento generado automáticamente el '.fecha_es(time()).' — Instituto Bíblico Bautista | RV 1865'),0,1,'C');
$pdf->SetFont('Times','I',8);
$pdf->Cell($W,0,u('"Escudriñad las Escrituras" — Juan 5:39'),0,1,'C');

$filename = 'Historial_'.preg_replace('/[^a-zA-Z0-9]/','_',$u['nombre_completo']).'.pdf';
$pdf->Output('D',$filename);

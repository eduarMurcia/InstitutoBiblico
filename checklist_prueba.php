<?php
// checklist_prueba.php — Genera checklist de prueba de usuario en PDF (FPDF)
// Uso: abrir desde el navegador como admin o ejecutar desde cPanel File Manager
require_once __DIR__ . '/fpdf.php';

function u($s) { return iconv('UTF-8','windows-1252//TRANSLIT//IGNORE', $s ?? ''); }

// ─── Datos del checklist ───────────────────────────────────────────────────
$flujos = [
  [
    'titulo' => 'FLUJO 1: Estudiante Nuevo (Registro y Primer Acceso)',
    'secciones' => [
      ['nombre' => 'Registro', 'pasos' => [
        ['texto' => 'Ir a /lms/ y hacer clic en "Registrarse"'],
        ['texto' => 'Intentar registro con codigo de invitacion invalido', 'nota' => 'Verificar: error visible, cuenta no creada'],
        ['texto' => 'Registro exitoso: nombre, apellido, correo, contrasena + codigo valido', 'nota' => 'Verificar: redirige al dashboard'],
      ]],
      ['nombre' => 'Inicio de sesion', 'pasos' => [
        ['texto' => 'Logout y volver a login.php'],
        ['texto' => 'Login con contrasena incorrecta', 'nota' => 'Verificar: mensaje de error, sin acceso'],
        ['texto' => 'Login correcto con las credenciales creadas', 'nota' => 'Verificar: nombre aparece en navbar'],
      ]],
      ['nombre' => 'Recuperacion de contrasena', 'pasos' => [
        ['texto' => 'Logout → "Olvide mi contrasena" → ingresar correo', 'nota' => 'Verificar: llega email con enlace'],
        ['texto' => 'Abrir enlace → nueva contrasena → login con la nueva', 'nota' => 'Verificar: funciona correctamente'],
        ['texto' => 'Intentar usar el mismo enlace una segunda vez', 'nota' => 'Verificar: enlace expirado o invalido'],
      ]],
    ],
  ],
  [
    'titulo' => 'FLUJO 2: Estudiante Cursando (Aprendizaje Completo)',
    'secciones' => [
      ['nombre' => 'Explorar cursos', 'pasos' => [
        ['texto' => 'Dashboard: cursos con portadas e iconos visibles', 'nota' => 'Verificar: imagenes cargan, sin espacios vacios'],
        ['texto' => 'Entrar al curso: descripcion, modulos y lecciones en orden'],
      ]],
      ['nombre' => 'Consumir una leccion', 'pasos' => [
        ['texto' => 'Abrir leccion: reproductor de audio funciona', 'nota' => 'Verificar: URL directa /uploads/audio/... da 403'],
        ['texto' => 'Ver PDF adjunto de la leccion', 'nota' => 'Verificar: URL directa /uploads/pdf/... da 403'],
        ['texto' => 'Marcar leccion como completada', 'nota' => 'Verificar: barra de progreso se actualiza'],
        ['texto' => 'Dejar un comentario al final de la leccion', 'nota' => 'Verificar: aparece en admin/comentarios.php'],
      ]],
      ['nombre' => 'Examen de modulo', 'pasos' => [
        ['texto' => 'Completar todas las lecciones: boton de examen se habilita'],
        ['texto' => 'Tomar examen: opcion multiple, V/F, pregunta abierta, subir archivo', 'nota' => 'Verificar: todos los tipos de pregunta enviables'],
        ['texto' => 'Ver resultado: preguntas auto-calificadas inmediatas', 'nota' => 'Verificar: aparece mensaje "pendiente de revision"'],
        ['texto' => 'Intentar reenviar el examen completado', 'nota' => 'Verificar: no es posible, boton desaparece'],
      ]],
      ['nombre' => 'Notificaciones y certificado', 'pasos' => [
        ['texto' => 'Esperar calificacion del admin: campana del navbar', 'nota' => 'Verificar: contador visible, enlace correcto'],
        ['texto' => 'Completar 100% del curso: descargar certificado PDF', 'nota' => 'Verificar: nombre, curso y numero unico en el PDF'],
      ]],
    ],
  ],
  [
    'titulo' => 'FLUJO 3: Administrador (Panel de Gestion)',
    'secciones' => [
      ['nombre' => 'Acceso y cursos', 'pasos' => [
        ['texto' => 'Login admin: redirige a /admin/ (no al dashboard de estudiantes)'],
        ['texto' => 'Crear curso: nombre, descripcion, portada, icono y color', 'nota' => 'Verificar: aparece en panel y vista de estudiante'],
        ['texto' => 'Agregar modulo y leccion: audio MP3 + PDF opcional', 'nota' => 'Verificar: orden correcto, accesibles como estudiante'],
      ]],
      ['nombre' => 'Examenes y entregas', 'pasos' => [
        ['texto' => 'Crear examen: preguntas de los 4 tipos, nota minima configurada'],
        ['texto' => 'Calificar entrega en admin/entregas.php: leer respuesta, asignar puntaje', 'nota' => 'Verificar: nota recalculada, estudiante notificado'],
      ]],
      ['nombre' => 'Comentarios, usuarios e invitaciones', 'pasos' => [
        ['texto' => 'Responder comentario en admin/comentarios.php', 'nota' => 'Verificar: estudiante recibe notificacion'],
        ['texto' => 'Gestionar usuarios: activar, desactivar, cambiar rol'],
        ['texto' => 'Generar codigo de invitacion con fecha y limite de usos', 'nota' => 'Verificar: expira segun lo configurado'],
      ]],
    ],
  ],
];

$criticos = [
  ['letra' => 'A', 'verificacion' => 'URL directa a /uploads/audio/nombre.mp3',           'esperado' => '403 Forbidden'],
  ['letra' => 'B', 'verificacion' => 'URL directa a /uploads/pdf/nombre.pdf',              'esperado' => '403 Forbidden'],
  ['letra' => 'C', 'verificacion' => 'URL directa a /uploads/respuestas/archivo.ext',      'esperado' => '403 Forbidden'],
  ['letra' => 'D', 'verificacion' => 'Acceder a /admin/ desde cuenta de estudiante',       'esperado' => 'Redirige o muestra 403'],
  ['letra' => 'E', 'verificacion' => 'Acceder a pagina protegida sin sesion',              'esperado' => 'Redirige a login.php'],
  ['letra' => 'F', 'verificacion' => 'Reenviar examen ya completado',                      'esperado' => 'No posible / estado completado'],
  ['letra' => 'G', 'verificacion' => 'Descargar certificado de curso incompleto',          'esperado' => 'Error 403 o mensaje de bloqueo'],
];

// ─── Clase PDF ────────────────────────────────────────────────────────────
class ChecklistPDF extends FPDF {
  private $navy  = [0, 10, 35];
  private $gold  = [209, 147, 9];
  private $cream = [245, 237, 208];
  private $muted = [106, 104, 128];

  function Header() {
    // Franja navy superior
    $this->SetFillColor(...$this->navy);
    $this->Rect(0, 0, 210, 18, 'F');
    // Linea dorada bajo la franja
    $this->SetDrawColor(...$this->gold);
    $this->SetLineWidth(0.5);
    $this->Line(0, 18, 210, 18);
    // Texto cabecera
    $this->SetFont('Times', 'I', 8);
    $this->SetTextColor(209, 147, 9);
    $this->SetY(6);
    $this->Cell(0, 6, u('Instituto Bíblico Bautista — Checklist de Prueba de Usuario'), 0, 0, 'C');
    $this->SetY(20);
    $this->SetTextColor(26, 18, 40);
  }

  function Footer() {
    $this->SetY(-14);
    $this->SetDrawColor(...$this->gold);
    $this->SetLineWidth(0.3);
    $this->Line(15, $this->GetY(), 195, $this->GetY());
    $this->Ln(2);
    $this->SetFont('Times', 'I', 7);
    $this->SetTextColor(...$this->muted);
    $this->Cell(0, 4, u('"Escudriñad las Escrituras" — Juan 5:39  ·  institutobiblicobautistacolombia.com/lms/'), 0, 1, 'C');
    $this->Cell(0, 3, u('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
  }

  // Dibuja un checkbox: cuadrado 4x4mm + texto al lado
  function Checkbox($x, $y, $texto, $nota = '') {
    $this->SetDrawColor(...$this->muted);
    $this->SetLineWidth(0.3);
    $this->Rect($x, $y, 4, 4);

    $this->SetFont('Times', '', 9);
    $this->SetTextColor(26, 18, 40);
    $tw = 195 - ($x + 6);
    $this->SetXY($x + 6, $y - 0.5);
    $this->MultiCell($tw, 4.5, u($texto), 0, 'L');

    if ($nota) {
      $this->SetFont('Times', 'I', 7.5);
      $this->SetTextColor(...$this->muted);
      $this->SetX($x + 8);
      $this->MultiCell($tw - 2, 3.8, u($nota), 0, 'L');
    }
    $this->SetTextColor(26, 18, 40);
  }

  // Cabecera de flujo (bloque navy)
  function FlowHeader($titulo, $num) {
    $this->Ln(4);
    $this->SetFillColor(...$this->navy);
    $this->SetTextColor(209, 147, 9);
    $this->SetFont('Times', 'B', 10);
    $this->SetX(15);
    $this->Cell(180, 8, u($titulo), 0, 1, 'L', true);
    $this->SetTextColor(26, 18, 40);
    $this->Ln(2);
  }

  // Cabecera de subseccion
  function SubHeader($nombre) {
    $this->SetFont('Times', 'B', 8);
    $this->SetTextColor(...$this->gold);
    $this->SetX(15);
    $cy = $this->GetY();
    $this->Cell(180, 5, u(strtoupper($nombre)), 0, 1, 'L');
    // linea bajo el subtitulo
    $this->SetDrawColor(...$this->gold);
    $this->SetLineWidth(0.2);
    $this->Line(15, $this->GetY(), 100, $this->GetY());
    $this->SetTextColor(26, 18, 40);
    $this->Ln(1);
  }

  // Espacio para nombre del evaluador en la portada
  function CampoFirma($label) {
    $this->SetFont('Times', '', 9);
    $this->SetTextColor(...$this->muted);
    $this->SetX(15);
    $this->Cell(40, 6, u($label . ':'), 0, 0);
    $this->SetDrawColor(...$this->muted);
    $this->SetLineWidth(0.3);
    $this->Line($this->GetX(), $this->GetY() + 5, $this->GetX() + 100, $this->GetY() + 5);
    $this->Ln(10);
  }
}

// ─── Generar PDF ──────────────────────────────────────────────────────────
$pdf = new ChecklistPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 22, 15);
$pdf->SetAutoPageBreak(true, 18);

// ── Portada / datos del evaluador ──
$pdf->AddPage();
$pdf->Ln(6);

// Titulo principal
$pdf->SetFont('Times', 'B', 18);
$pdf->SetTextColor(0, 10, 35);
$pdf->Cell(0, 10, u('Checklist de Prueba de Usuario'), 0, 1, 'C');
$pdf->SetFont('Times', 'I', 12);
$pdf->SetTextColor(209, 147, 9);
$pdf->Cell(0, 7, u('Instituto Bíblico Bautista — LMS'), 0, 1, 'C');
$pdf->Ln(2);

// Linea dorada
$pdf->SetDrawColor(209, 147, 9);
$pdf->SetLineWidth(0.6);
$pdf->Line(30, $pdf->GetY(), 180, $pdf->GetY());
$pdf->Ln(6);

// Instrucciones
$pdf->SetFont('Times', '', 9);
$pdf->SetTextColor(74, 69, 96);
$pdf->SetX(15);
$pdf->MultiCell(180, 4.5, u(
  'Instrucciones: marque cada paso con una X al completarlo. '.
  'Si encuentra un error, descríbalo en el espacio de notas al final. '.
  'Entregue este formulario al coordinador al terminar la sesión de prueba.'
), 0, 'J');
$pdf->Ln(5);

// Campos del evaluador
$pdf->SetTextColor(26, 18, 40);
$pdf->CampoFirma('Nombre del evaluador');
$pdf->CampoFirma('Fecha de prueba');
$pdf->CampoFirma('Dispositivo / navegador');

// Linea separadora
$pdf->SetDrawColor(0, 10, 35);
$pdf->SetLineWidth(0.3);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(3);

// ── Flujos ──
foreach ($flujos as $fi => $flujo) {
  $pdf->FlowHeader($flujo['titulo'], $fi + 1);

  foreach ($flujo['secciones'] as $sec) {
    $pdf->SubHeader($sec['nombre']);

    foreach ($sec['pasos'] as $paso) {
      // Salto de pagina preventivo
      if ($pdf->GetY() > 265) { $pdf->AddPage(); }

      $nota = $paso['nota'] ?? '';
      $pdf->Checkbox(17, $pdf->GetY(), $paso['texto'], $nota);
      $pdf->Ln(1);
    }
    $pdf->Ln(2);
  }
}

// ── Puntos críticos de seguridad ──
$pdf->FlowHeader('PUNTOS CRÍTICOS DE SEGURIDAD', 4);

// Tabla encabezado

// Usamos coordenadas directas para la mini-tabla
$cols = [12, 100, 68]; // widths: #, verificacion, esperado
$fy = $pdf->GetY();
$pdf->SetFillColor(0, 10, 35);
$pdf->SetTextColor(209, 147, 9);
$pdf->SetFont('Times', 'B', 7.5);
$pdf->SetX(15);
$pdf->Cell($cols[0], 6, '#',           1, 0, 'C', true);
$pdf->Cell($cols[1], 6, 'VERIFICACION',1, 0, 'L', true);
$pdf->Cell($cols[2], 6, 'RESULTADO ESPERADO', 1, 1, 'L', true);

foreach ($criticos as $i => $c) {
  if ($pdf->GetY() > 268) { $pdf->AddPage(); }
  $fill = ($i % 2 === 0);
  $pdf->SetFillColor(245, 237, 208);
  $pdf->SetTextColor(26, 18, 40);
  $pdf->SetFont('Times', 'B', 8.5);
  $pdf->SetX(15);
  $pdf->Cell($cols[0], 6, $c['letra'], 1, 0, 'C', $fill);
  $pdf->SetFont('Times', '', 8);
  $pdf->Cell($cols[1], 6, u($c['verificacion']), 1, 0, 'L', $fill);
  $pdf->SetFont('Times', 'I', 8);
  $pdf->SetTextColor(153, 27, 27);
  $pdf->Cell($cols[2], 6, u($c['esperado']), 1, 1, 'L', $fill);
}

// ── Espacio de notas ──
$pdf->Ln(6);
if ($pdf->GetY() > 220) { $pdf->AddPage(); }
$pdf->SetFont('Times', 'B', 10);
$pdf->SetTextColor(0, 10, 35);
$pdf->SetX(15);
$pdf->Cell(0, 6, u('Notas y errores encontrados'), 0, 1);
$pdf->SetDrawColor(180, 170, 150);
$pdf->SetLineWidth(0.25);
for ($l = 0; $l < 10; $l++) {
  $y = $pdf->GetY() + 8;
  if ($y > 272) break;
  $pdf->Line(15, $y, 195, $y);
  $pdf->SetY($y);
}

// ── Output ──
$pdf->Output('I', 'checklist_prueba.pdf');

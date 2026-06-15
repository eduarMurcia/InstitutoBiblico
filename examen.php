<?php
require_once 'includes/auth.php';
require_once 'includes/estudiante_layout.php';
require_once 'config/db.php';
require_once 'includes/notificaciones.php';
requerir_login();

$conn = conectar();
$uid  = $_SESSION['usuario_id'];
$eid  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$eid) redirigir('cursos.php');

define('RESPUESTAS_PATH', __DIR__ . '/uploads/respuestas/');

// Datos del examen
$s = $conn->prepare("SELECT e.*, m.titulo AS modulo, m.curso_id, c.titulo AS curso FROM examenes e JOIN modulos m ON m.id=e.modulo_id JOIN cursos c ON c.id=m.curso_id WHERE e.id=?");
$s->bind_param("i", $eid); $s->execute();
$examen = $s->get_result()->fetch_assoc(); $s->close();
if (!$examen) redirigir('cursos.php');

// Preguntas
$s = $conn->prepare("SELECT * FROM preguntas WHERE examen_id=? ORDER BY orden");
$s->bind_param("i", $eid); $s->execute();
$preguntas = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();

// Historial de intentos
$s = $conn->prepare("SELECT * FROM resultados_examen WHERE usuario_id=? AND examen_id=? ORDER BY fecha DESC");
$s->bind_param("ii", $uid, $eid); $s->execute();
$todos_resultados = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
$ultimo           = $todos_resultados[0] ?? null;
$intentos_usados  = count($todos_resultados);
$max_intentos     = (int)($examen['max_intentos'] ?? 0);
$ya_aprobado      = $ultimo && $ultimo['aprobado'];
$limite_alcanzado = $max_intentos > 0 && $intentos_usados >= $max_intentos;

// Respuestas previas guardadas
$s = $conn->prepare("SELECT * FROM respuestas_examen WHERE usuario_id=? AND examen_id=?");
$s->bind_param("ii", $uid, $eid); $s->execute();
$resp_raw = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
$resp_previas = [];
foreach ($resp_raw as $r) { $resp_previas[$r['pregunta_id']] = $r; }

$resultado = null; $error = '';

// ── Procesar envío ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar']) && !$ya_aprobado && !$limite_alcanzado) {
    if (!is_dir(RESPUESTAS_PATH)) mkdir(RESPUESTAS_PATH, 0755, true);

    $total = count($preguntas);
    $auto_pts = $auto_total = 0;
    $tiene_manuales = false;
    $errores_archivo = [];

    foreach ($preguntas as $p) {
        $pid  = $p['id'];
        $tipo = $p['tipo'] ?? 'multiple';
        $resp_texto = $resp_archivo = '';
        $cal = null;

        switch ($tipo) {
            case 'multiple':
                $resp_texto = sanitizar($_POST['p_' . $pid] ?? '');
                $correcta   = ($resp_texto === $p['respuesta_correcta']);
                $cal = $correcta ? 100 : 0;
                $auto_pts += $correcta ? 1 : 0; $auto_total++;
                break;
            case 'verdadero_falso':
                $resp_texto = sanitizar($_POST['p_' . $pid] ?? '');
                $correcta   = ($resp_texto === $p['respuesta_correcta']);
                $cal = $correcta ? 100 : 0;
                $auto_pts += $correcta ? 1 : 0; $auto_total++;
                break;
            case 'abierta':
                $resp_texto = sanitizar($_POST['p_' . $pid] ?? '');
                $cal = null; $tiene_manuales = true;
                break;
            case 'archivo':
                $key = 'archivo_' . $pid;
                if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                    $ext   = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                    $allow = ['pdf','jpg','jpeg','png','doc','docx'];
                    if (!in_array($ext, $allow)) {
                        $errores_archivo[] = 'Formato no permitido (PDF, JPG, PNG, DOC, DOCX).';
                    } elseif ($_FILES[$key]['size'] > 30 * 1024 * 1024) {
                        $errores_archivo[] = 'El archivo supera 30MB.';
                    } else {
                        $nombre = uniqid('resp_' . $uid . '_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES[$key]['tmp_name'], RESPUESTAS_PATH . $nombre)) {
                            $resp_archivo = $nombre;
                        } else { $errores_archivo[] = 'No se pudo guardar el archivo.'; }
                    }
                }
                $cal = null; $tiene_manuales = true;
                break;
        }

        if (!$errores_archivo) {
            $s = $conn->prepare("INSERT INTO respuestas_examen (usuario_id, examen_id, pregunta_id, respuesta_texto, archivo_respuesta, calificacion) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE respuesta_texto=VALUES(respuesta_texto), archivo_respuesta=IF(VALUES(archivo_respuesta)!='',VALUES(archivo_respuesta),archivo_respuesta), calificacion=VALUES(calificacion), revisado=0");
            $s->bind_param("iiissd", $uid, $eid, $pid, $resp_texto, $resp_archivo, $cal);
            $s->execute(); $s->close();
        }
    }

    if ($errores_archivo) {
        $error = implode('<br>', $errores_archivo);
    } else {
        // ── Procesar archivo general opcional ──
        $archivo_general = '';
        if (isset($_FILES['archivo_general']) && $_FILES['archivo_general']['error'] === UPLOAD_ERR_OK) {
            $ext   = strtolower(pathinfo($_FILES['archivo_general']['name'], PATHINFO_EXTENSION));
            $allow = ['pdf','jpg','jpeg','png','doc','docx'];
            if (in_array($ext, $allow) && $_FILES['archivo_general']['size'] <= 30 * 1024 * 1024) {
                $nombre_g = uniqid('general_' . $uid . '_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['archivo_general']['tmp_name'], RESPUESTAS_PATH . $nombre_g)) {
                    $archivo_general = $nombre_g;
                    $tiene_manuales  = true; // requiere revisión del pastor
                }
            }
        }

        $puntaje_auto = $auto_total > 0 ? round(($auto_pts / $total) * 100) : 0;
        $aprobado     = (!$tiene_manuales && $puntaje_auto >= $examen['puntaje_minimo']) ? 1 : 0;
        $pendiente    = $tiene_manuales ? 1 : 0;

        $s = $conn->prepare("INSERT INTO resultados_examen (usuario_id, examen_id, puntaje, aprobado, pendiente_revision, archivo_general) VALUES (?,?,?,?,?,?)");
        $s->bind_param("iiiiss", $uid, $eid, $puntaje_auto, $aprobado, $pendiente, $archivo_general);
        $s->execute(); $s->close();

        $resultado = ['puntaje'=>$puntaje_auto, 'aprobado'=>$aprobado, 'pendiente'=>$pendiente, 'correctas'=>$auto_pts, 'auto_total'=>$auto_total, 'total'=>$total];
        $intentos_usados++;
        $ultimo = ['puntaje'=>$puntaje_auto, 'aprobado'=>$aprobado, 'pendiente_revision'=>$pendiente];

        // Notificar al admin si hay preguntas para revisar
        if ($pendiente) {
            $nombre_est = sanitizar($_SESSION['nombre']);
            crear_notificacion(
                'entrega',
                "Nueva entrega de {$nombre_est}",
                "El examen '" . $examen['titulo'] . "' tiene entregas pendientes de calificacion.",
                'admin/entregas.php'
            );
        }

        // Recargar respuestas
        $s = $conn->prepare("SELECT * FROM respuestas_examen WHERE usuario_id=? AND examen_id=?");
        $s->bind_param("ii",$uid,$eid); $s->execute();
        $resp_raw = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
        $resp_previas = [];
        foreach ($resp_raw as $r) { $resp_previas[$r['pregunta_id']] = $r; }
    }
}
$conn->close();

// ── Lógica de visibilidad del formulario ──
$mostrar_formulario = !$resultado
                   && !$ya_aprobado
                   && !$limite_alcanzado
                   && !empty($preguntas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title><?= sanitizar($examen['titulo']) ?> — RV 1865</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .exam-wrap { max-width:760px; margin:0 auto; padding:3rem 2rem; }
    .preg-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:1.5rem; margin-bottom:1.25rem; box-shadow:var(--shadow); }
    .preg-meta { font-family:var(--font-ui); font-size:0.7rem; font-weight:700; color:var(--text-muted); margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center; }
    .preg-text { color:var(--navy); font-size:1.05rem; margin-bottom:1rem; font-family:var(--font-body); }
    .opcion-lbl { display:flex; align-items:center; gap:0.75rem; padding:0.65rem 1rem; border-radius:var(--radius); border:1.5px solid var(--border); cursor:pointer; margin-bottom:0.5rem; color:var(--text-soft); transition:all 0.15s; background:var(--bg); }
    .opcion-lbl:hover { border-color:var(--gold); color:var(--navy); background:var(--gold-pale); }
    .opcion-lbl input { accent-color:var(--gold); }
    .result-box { background:var(--bg-card); border:2px solid var(--gold); border-radius:var(--radius-lg); padding:2.5rem; text-align:center; margin-bottom:2rem; box-shadow:var(--shadow-lg); }
    .result-score { font-family:var(--font-title); font-size:4.5rem; color:var(--gold); line-height:1; margin-bottom:0.5rem; }
    .upload-zone { border:2px dashed var(--border); border-radius:var(--radius); padding:1.25rem; text-align:center; cursor:pointer; transition:all 0.2s; background:var(--bg); }
    .upload-zone:hover { border-color:var(--gold); background:var(--gold-pale); }
    .upload-zone input { display:none; }
    .intentos-badge { font-family:var(--font-ui); font-size:0.78rem; color:var(--text-muted); }
  </style>
</head>
<body>

<?php estudiante_navbar("", ["label"=>"← Volver al curso", "href"=>"cursos.php?id=".($examen["curso_id"]??0)]); ?>

<div class="exam-wrap">
  <span style="font-family:var(--font-ui); font-size:0.72rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold);">
    Examen — <?= sanitizar($examen['modulo']) ?>
  </span>
  <h2 style="margin:0.4rem 0;"><?= sanitizar($examen['titulo']) ?></h2>
  <hr class="divider-gold left" style="margin-bottom:1.25rem;">

  <?php if ($examen['descripcion']): ?>
    <p style="margin-bottom:1.25rem;"><?= sanitizar($examen['descripcion']) ?></p>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error mb-3"><?= $error ?></div>
  <?php endif; ?>

  <!-- Barra de estado -->
  <div class="alert alert-info mb-3" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:center;">
    <span>Aprobación mínima: <strong><?= $examen['puntaje_minimo'] ?>%</strong></span>
    <?php if ($max_intentos > 0): ?>
      <span class="intentos-badge">
        Intentos: <?= $intentos_usados ?> / <?= $max_intentos ?>
      </span>
    <?php elseif ($intentos_usados > 0): ?>
      <span class="intentos-badge">Intentos realizados: <?= $intentos_usados ?></span>
    <?php endif; ?>
    <?php if ($ultimo && !$resultado): ?>
      <span>Último resultado:
        <strong style="color:<?= $ultimo['pendiente_revision'] ? '#8a6000' : ($ultimo['aprobado'] ? '#1e7e45' : '#922b21') ?>">
          <?php if ($ultimo['pendiente_revision']): ?><?= icono('pendiente','ico') ?> Pendiente de revisión
          <?php elseif ($ultimo['aprobado']): ?>✓ Aprobado (<?= $ultimo['puntaje'] ?>%)
          <?php else: ?>✗ No aprobado (<?= $ultimo['puntaje'] ?>%)
          <?php endif; ?>
        </strong>
      </span>
    <?php endif; ?>
    <a href="api/examen_imprimible.php?id=<?= $eid ?>"
       target="_blank"
       style="margin-left:auto;"
       class="btn btn-outline btn-sm"
       title="Descargar el examen en PDF para responder en papel y luego subir la foto">
      <?= icono('documento','ico') ?> Responder en papel
    </a>
  </div>

  <!-- ── Resultado del envío actual ── -->
  <?php if ($resultado): ?>
  <div class="result-box">
    <?php if ($resultado['pendiente']): ?>
      <div style="margin-bottom:0.75rem;"><?= icono('pendiente','ico-xl') ?></div>
      <h3 style="color:var(--navy); margin-bottom:0.5rem;">Examen enviado</h3>
      <p style="max-width:460px; margin:0 auto 1rem;">
        Las preguntas abiertas y de archivo serán revisadas por el pastor.
        Recibirás tu calificación final cuando estén evaluadas.
      </p>
      <?php if ($resultado['auto_total'] > 0): ?>
      <p style="font-size:0.9rem; color:var(--text-muted);">
        Preguntas automáticas: <?= $resultado['correctas'] ?>/<?= $resultado['auto_total'] ?> correctas
      </p>
      <?php endif; ?>
    <?php else: ?>
      <div class="result-score"><?= $resultado['puntaje'] ?>%</div>
      <p style="color:var(--text-muted); margin:0.5rem 0 1.25rem;">
        <?= $resultado['correctas'] ?> de <?= $resultado['total'] ?> preguntas correctas
      </p>
      <?php if ($resultado['aprobado']): ?>
        <span class="badge badge-success" style="font-size:0.95rem; padding:0.5rem 1.5rem;">✓ Aprobado</span>
        <p style="color:var(--gold); font-family:var(--font-title); font-style:italic; margin-top:1rem; font-size:1.15rem;">¡Bien hecho!</p>
        <a href="perfil.php" class="btn btn-primary mt-2">Ver mis certificados</a>
      <?php else: ?>
        <span class="badge badge-gray" style="font-size:0.95rem; padding:0.5rem 1.5rem; color:#922b21;">✗ No aprobado</span>
        <?php if (!$limite_alcanzado): ?>
          <p style="font-size:0.88rem; color:var(--text-muted); margin-top:0.75rem;">Puedes intentarlo de nuevo.</p>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
    <div style="margin-top:1.5rem;">
      <a href="cursos.php?id=<?= $examen['curso_id'] ?>" class="btn btn-outline btn-sm">← Volver al curso</a>
    </div>
  </div>

  <!-- Límite de intentos alcanzado -->
  <?php elseif ($limite_alcanzado && !$ya_aprobado): ?>
  <div class="result-box">
    <div style="margin-bottom:0.75rem;"><?= icono('candado','ico-xl') ?></div>
    <h3 style="color:var(--navy); margin-bottom:0.5rem;">Límite de intentos alcanzado</h3>
    <p>Has utilizado los <?= $max_intentos ?> intento(s) disponibles para este examen.</p>
    <a href="cursos.php?id=<?= $examen['curso_id'] ?>" class="btn btn-outline btn-sm mt-2">← Volver al curso</a>
  </div>

  <!-- Ya aprobado -->
  <?php elseif ($ya_aprobado && !$resultado): ?>
  <div class="result-box">
    <div style="margin-bottom:0.75rem;"><?= icono('trofeo','ico-xl') ?></div>
    <span class="badge badge-success" style="font-size:0.95rem; padding:0.5rem 1.5rem;">✓ Examen aprobado</span>
    <p style="margin-top:1rem; color:var(--text-soft);">Ya aprobaste este examen con <?= $ultimo['puntaje'] ?>%.</p>
    <a href="perfil.php" class="btn btn-primary mt-2">Ver mis certificados</a>
  </div>
  <?php endif; ?>

  <!-- ── Formulario del examen ── -->
  <?php if ($mostrar_formulario): ?>
  <form method="POST" enctype="multipart/form-data">
    <?php foreach ($preguntas as $i => $p):
      $tipo = $p['tipo'] ?? 'multiple';
      $prev = $resp_previas[$p['id']] ?? null;
      $etiquetas = ['multiple'=>'Selección múltiple','verdadero_falso'=>'Verdadero / Falso','abierta'=>'Pregunta abierta','archivo'=>'Entrega de archivo'];
      $badges    = ['multiple'=>'badge-gold','verdadero_falso'=>'badge-teal','abierta'=>'badge-gray','archivo'=>'badge-gray'];
    ?>
    <div class="preg-card">
      <div class="preg-meta">
        <span>Pregunta <?= $i+1 ?> de <?= count($preguntas) ?></span>
        <span class="badge <?= $badges[$tipo] ?? 'badge-gray' ?>"><?= $etiquetas[$tipo] ?? $tipo ?></span>
      </div>
      <p class="preg-text"><?= sanitizar($p['pregunta']) ?></p>

      <?php if ($tipo === 'multiple'): ?>
        <?php foreach (['a'=>$p['opcion_a'],'b'=>$p['opcion_b'],'c'=>$p['opcion_c'],'d'=>$p['opcion_d']] as $letra => $texto): ?>
        <label class="opcion-lbl">
          <input type="radio" name="p_<?= $p['id'] ?>" value="<?= $letra ?>"
                 <?= ($prev && $prev['respuesta_texto']===$letra) ? 'checked' : '' ?> required>
          <span><strong style="color:var(--gold);"><?= strtoupper($letra) ?>.</strong> <?= sanitizar($texto) ?></span>
        </label>
        <?php endforeach; ?>

      <?php elseif ($tipo === 'verdadero_falso'): ?>
        <label class="opcion-lbl">
          <input type="radio" name="p_<?= $p['id'] ?>" value="a"
                 <?= ($prev && $prev['respuesta_texto']==='a') ? 'checked' : '' ?> required>
          <span><strong style="color:var(--gold);">V.</strong> Verdadero</span>
        </label>
        <label class="opcion-lbl">
          <input type="radio" name="p_<?= $p['id'] ?>" value="b"
                 <?= ($prev && $prev['respuesta_texto']==='b') ? 'checked' : '' ?> required>
          <span><strong style="color:var(--gold);">F.</strong> Falso</span>
        </label>

      <?php elseif ($tipo === 'abierta'): ?>
        <textarea name="p_<?= $p['id'] ?>" class="form-control" rows="4"
                  placeholder="Escribe tu respuesta aquí..."><?= sanitizar($prev['respuesta_texto'] ?? '') ?></textarea>
        <?php if ($prev && $prev['revisado'] && $prev['calificacion'] !== null): ?>
          <div class="alert alert-info mt-2" style="font-size:0.88rem;">
            Calificación del pastor: <strong><?= $prev['calificacion'] ?>/100</strong>
            <?php if ($prev['comentario_pastor']): ?> — <em><?= sanitizar($prev['comentario_pastor']) ?></em><?php endif; ?>
          </div>
        <?php endif; ?>

      <?php elseif ($tipo === 'archivo'): ?>
        <?php if ($prev && $prev['archivo_respuesta']): ?>
          <div class="alert alert-success mb-2" style="font-size:0.88rem;">
            ✓ Archivo enviado.
            <a href="api/archivo_respuesta.php?id=<?= $prev['id'] ?>">Descargar</a>
            <?php if ($prev['revisado'] && $prev['calificacion'] !== null): ?>
              — Calificación: <strong><?= $prev['calificacion'] ?>/100</strong>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="upload-zone" onclick="document.getElementById('arch_<?= $p['id'] ?>').click()">
          <?= icono('adjunto','ico-md') ?>
          <p style="margin:0.4rem 0 0; color:var(--text-muted); font-size:0.88rem;" id="lbl_<?= $p['id'] ?>">
            <?= ($prev && $prev['archivo_respuesta']) ? 'Reemplazar archivo' : 'Seleccionar archivo (PDF, imagen, Word — máx. 30MB)' ?>
          </p>
          <input type="file" id="arch_<?= $p['id'] ?>" name="archivo_<?= $p['id'] ?>"
                 accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                 onchange="document.getElementById('lbl_<?= $p['id'] ?>').textContent=this.files[0]?.name||'Sin archivo'">
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <!-- ── Entrega de archivo fija (aparece en todos los exámenes) ── -->
    <div class="preg-card" style="border-color:var(--border-gold);">
      <div class="preg-meta">
        <span style="color:var(--gold); font-weight:700;"><?= icono('adjunto','ico') ?> Entrega de archivo <span style="font-weight:400; color:var(--text-muted);">— opcional</span></span>
        <span class="badge badge-gold">Opcional</span>
      </div>
      <p class="preg-text" style="font-size:0.95rem; color:var(--text-muted);">
        ¿Prefiere responder en papel? Descargue el examen, resuélvalo a mano y suba la foto o el documento aquí.
      </p>
      <div class="upload-zone" onclick="document.getElementById('arch_general').click()">
        <?= icono('adjunto','ico-md') ?>
        <p style="margin:0.4rem 0 0; color:var(--text-muted); font-size:0.88rem;" id="lbl_general">
          Seleccionar archivo (foto, PDF o Word — máx. 30MB)
        </p>
        <input type="file" id="arch_general" name="archivo_general"
               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
               onchange="document.getElementById('lbl_general').textContent=this.files[0]?.name||'Sin archivo'">
      </div>
    </div>

    <div style="text-align:center; margin-top:2rem;">
      <button type="submit" name="enviar" class="btn btn-primary">Enviar examen</button>
    </div>
  </form>

  <?php elseif (empty($preguntas)): ?>
    <div class="alert alert-info">Este examen aún no tiene preguntas.</div>
  <?php endif; ?>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">RV 1865 — Formación bíblica en línea</p>
</footer>
</body>
</html>

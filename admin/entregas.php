<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_admin();
require_once __DIR__ . '/../includes/admin_sidebar.php';

$conn  = conectar();
$exito = $error = '';
$filtro_eid = isset($_GET['examen_id']) ? (int)$_GET['examen_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar'])) {
    $rid        = (int)$_POST['respuesta_id'];
    $cal        = min(100, max(0, (float)$_POST['calificacion']));
    $comentario = sanitizar($_POST['comentario'] ?? '');
    $s = $conn->prepare("UPDATE respuestas_examen SET calificacion=?, comentario_pastor=?, revisado=1 WHERE id=?");
    $s->bind_param("dsi", $cal, $comentario, $rid);
    $s->execute(); $s->close();

    $s = $conn->prepare("SELECT examen_id, usuario_id FROM respuestas_examen WHERE id=?");
    $s->bind_param("i",$rid); $s->execute();
    $meta = $s->get_result()->fetch_assoc(); $s->close();

    if ($meta) {
        $uid_e = $meta['usuario_id']; $eid_e = $meta['examen_id'];
        $s = $conn->prepare("SELECT COUNT(*) AS p FROM respuestas_examen re JOIN preguntas p ON p.id=re.pregunta_id WHERE re.usuario_id=? AND re.examen_id=? AND p.tipo IN ('abierta','archivo') AND re.calificacion IS NULL");
        $s->bind_param("ii",$uid_e,$eid_e); $s->execute();
        $pend = $s->get_result()->fetch_assoc()['p']; $s->close();
        if ($pend == 0) {
            $s = $conn->prepare("SELECT AVG(re.calificacion) AS promedio FROM respuestas_examen re WHERE re.usuario_id=? AND re.examen_id=?");
            $s->bind_param("ii",$uid_e,$eid_e); $s->execute();
            $avg = round($s->get_result()->fetch_assoc()['promedio']); $s->close();
            $s = $conn->prepare("SELECT e.puntaje_minimo, e.titulo AS examen FROM examenes e WHERE e.id=?");
            $s->bind_param("i",$eid_e); $s->execute();
            $ex_data  = $s->get_result()->fetch_assoc(); $s->close();
            $min_pts  = $ex_data['puntaje_minimo'];
            $aprobado = ($avg >= $min_pts) ? 1 : 0;
            $conn->query("UPDATE resultados_examen SET puntaje=$avg, puntaje_final=$avg, aprobado=$aprobado, pendiente_revision=0 WHERE usuario_id=$uid_e AND examen_id=$eid_e ORDER BY id DESC LIMIT 1");

            // Notificar al estudiante si tiene email activo
            $s = $conn->prepare("SELECT nombre, email, notif_email FROM usuarios WHERE id=?");
            $s->bind_param("i", $uid_e); $s->execute();
            $est = $s->get_result()->fetch_assoc(); $s->close();
            if ($est && $est['notif_email']) {
                $estado_txt = $aprobado ? 'Aprobado' : 'No aprobado';
                $subject    = '[IBB] Tu examen ha sido calificado';
                $body       = "Hola {$est['nombre']},\n\n"
                    . "El pastor ha calificado tu examen \"{$ex_data['examen']}\".\n\n"
                    . "Nota final: {$avg}% — {$estado_txt}\n\n"
                    . ($comentario ? "Comentario del pastor:\n{$comentario}\n\n" : '')
                    . "Puedes consultar tu historial completo en tu perfil:\n"
                    . "https://institutobiblicobautistacolombia.com/lms/perfil.php\n\n"
                    . "—\nInstituto Bíblico Bautista";
                $from    = 'no-responder@institutobiblicobautistacolombia.com';
                $headers = "From: Instituto Bíblico Bautista <{$from}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                @mail($est['email'], '=?UTF-8?B?'.base64_encode($subject).'?=', $body, $headers);
            }
            // Notificación interna siempre (independiente del email)
            $estado_txt = $aprobado ? 'Aprobado ✓' : 'No aprobado';
            notificar_estudiante(
                $uid_e,
                'examen_calificado',
                'Tu examen ha sido calificado',
                "Examen \"{$ex_data['examen']}\" — Nota: {$avg}% — {$estado_txt}" . ($comentario ? ". Comentario: {$comentario}" : ''),
                'perfil.php'
            );
        }
    }
    $exito = 'Calificación guardada.';
    header("Location: entregas.php" . ($filtro_eid ? "?examen_id=$filtro_eid" : ''));
    exit;
}

$where   = $filtro_eid ? "AND e.id=$filtro_eid" : '';
$entregas = $conn->query("
    SELECT re.id, re.respuesta_texto, re.archivo_respuesta,
           re.calificacion, re.comentario_pastor, re.revisado, re.created_at,
           u.nombre AS estudiante, u.email,
           p.pregunta, p.tipo,
           e.titulo AS examen, e.id AS examen_id, c.titulo AS curso
    FROM respuestas_examen re
    JOIN usuarios u ON u.id=re.usuario_id
    JOIN preguntas p ON p.id=re.pregunta_id
    JOIN examenes e ON e.id=re.examen_id
    JOIN modulos mo ON mo.id=e.modulo_id
    JOIN cursos c ON c.id=mo.curso_id
    WHERE p.tipo IN ('abierta','archivo') $where
    ORDER BY re.revisado ASC, re.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$examenes_lista = $conn->query("SELECT e.id, e.titulo, c.titulo AS curso FROM examenes e JOIN modulos mo ON mo.id=e.modulo_id JOIN cursos c ON c.id=mo.curso_id ORDER BY c.titulo, e.titulo")->fetch_all(MYSQLI_ASSOC);

// Archivos generales subidos (de resultados_examen)
$where_eid_g = $filtro_eid ? "AND e.id=$filtro_eid" : '';
$archivos_generales = $conn->query("
    SELECT res.id, res.archivo_general, res.fecha, res.pendiente_revision,
           u.nombre AS estudiante, u.email,
           e.titulo AS examen, e.id AS examen_id, c.titulo AS curso
    FROM resultados_examen res
    JOIN usuarios u ON u.id=res.usuario_id
    JOIN examenes e ON e.id=res.examen_id
    JOIN modulos mo ON mo.id=e.modulo_id
    JOIN cursos c ON c.id=mo.curso_id
    WHERE res.archivo_general IS NOT NULL AND res.archivo_general != '' $where_eid_g
    ORDER BY res.fecha DESC
")->fetch_all(MYSQLI_ASSOC);

$pendientes = array_filter($entregas, fn($r) => !$r['revisado']);
$revisadas  = array_filter($entregas, fn($r) => $r['revisado']);
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Entregas — Admin RV 1865</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .entrega-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      margin-bottom: 1.25rem;
      box-shadow: var(--shadow);
    }
    .entrega-card.pendiente { border-left: 3px solid var(--gold); }
    .entrega-card.revisada  { border-left: 3px solid #27ae60; opacity: 0.85; }

    .entrega-meta {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.85rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem 1.25rem;
    }
    .entrega-meta strong { color: var(--navy); }

    .respuesta-texto {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem;
      color: var(--text-soft);
      font-size: 0.95rem;
      line-height: 1.6;
      margin-bottom: 1rem;
      white-space: pre-wrap;
    }

    /* Formulario de calificación — tema claro */
    .cal-form {
      background: var(--bg);
      border: 1px solid var(--border-gold);
      border-radius: var(--radius);
      padding: 1.25rem;
      margin-top: 0.75rem;
    }
    .cal-form .form-label { color: var(--navy); }
    .cal-form .form-control { background: var(--bg-card); }

    .nota-display {
      font-family: var(--font-title);
      font-size: 2rem;
      color: var(--gold);
    }

    /* Tabs */
    .tabs-nav {
      display: flex;
      gap: 0;
      border-bottom: 2px solid var(--border);
      margin-bottom: 2rem;
    }
    .tab-item {
      padding: 0.65rem 1.5rem;
      font-family: var(--font-ui);
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      color: var(--text-muted);
      transition: all 0.2s;
    }
    .tab-item.active { color: var(--navy); border-bottom-color: var(--gold); }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
  </style>
</head>
<body>

<?php admin_navbar("entregas"); ?>

<div class="main-layout">
  <?php admin_sidebar("entregas"); ?>

  <main class="main-content">
    <div class="d-flex justify-between align-center mb-2" style="flex-wrap:wrap; gap:1rem;">
      <div>
        <span style="font-family:var(--font-ui); font-size:0.72rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold);">Revisión</span>
        <h2 style="margin:0.25rem 0;">Entregas de estudiantes</h2>
      </div>
      <?php if (count($pendientes) > 0): ?>
        <span class="badge badge-gold" style="font-size:0.88rem; padding:0.4rem 1rem;">
          <?= count($pendientes) ?> pendiente<?= count($pendientes)>1?'s':'' ?> de revisión
        </span>
      <?php endif; ?>
    </div>
    <hr class="divider-gold left" style="margin-bottom:1.5rem;">

    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>

    <!-- Filtro -->
    <form method="GET" style="margin-bottom:1.5rem; display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
      <select name="examen_id" class="form-control" style="max-width:360px;">
        <option value="">— Todos los exámenes —</option>
        <?php foreach ($examenes_lista as $ex): ?>
        <option value="<?= $ex['id'] ?>" <?= $filtro_eid===$ex['id']?'selected':'' ?>>
          <?= sanitizar($ex['curso']) ?> › <?= sanitizar($ex['titulo']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
      <?php if ($filtro_eid): ?>
        <a href="entregas.php" class="btn btn-ghost btn-sm">✕ Ver todas</a>
      <?php endif; ?>
    </form>

    <!-- Tabs -->
    <div class="tabs-nav">
      <div class="tab-item active" onclick="showTab('pendientes',this)">
        Pendientes
        <span class="badge badge-gold" style="margin-left:0.4rem;"><?= count($pendientes) ?></span>
      </div>
      <div class="tab-item" onclick="showTab('revisadas',this)">
        Revisadas
        <span class="badge badge-gray" style="margin-left:0.4rem;"><?= count($revisadas) ?></span>
      </div>
    </div>

    <!-- Pendientes -->
    <div id="tab-pendientes" class="tab-panel active">
      <?php if (!empty($archivos_generales)): ?>
      <h4 style="font-size:0.88rem; font-family:var(--font-ui); color:var(--gold); letter-spacing:0.1em; text-transform:uppercase; margin-bottom:1rem;">
        📎 Archivos generales del examen
      </h4>
      <?php foreach ($archivos_generales as $ag): ?>
      <div class="entrega-card pendiente" style="margin-bottom:1rem;">
        <div class="entrega-meta">
          <span>👤 <strong><?= sanitizar($ag['estudiante']) ?></strong> — <?= sanitizar($ag['email']) ?></span>
          <span>✎ <?= sanitizar($ag['examen']) ?></span>
          <span>📖 <?= sanitizar($ag['curso']) ?></span>
          <span>🕐 <?= date('d/m/Y H:i', strtotime($ag['created_at'])) ?></span>
          <span class="badge badge-gold" style="font-size:0.7rem;">📎 Archivo general</span>
        </div>
        <a href="../uploads/respuestas/<?= htmlspecialchars($ag['archivo_general']) ?>"
           target="_blank" class="btn btn-outline btn-sm">
          📎 Descargar archivo del estudiante
        </a>
      </div>
      <?php endforeach; ?>
      <hr style="border-color:var(--border); margin:1.5rem 0;">
      <?php endif; ?>

      <?php if (empty($pendientes)): ?>
        <div class="card text-center">
          <p style="color:var(--text-muted); margin:0;">✓ No hay entregas pendientes de revisión.</p>
        </div>
      <?php else: foreach ($pendientes as $e): ?>
      <div class="entrega-card pendiente">
        <div class="entrega-meta">
          <span>📖 <strong><?= sanitizar($e['curso']) ?></strong></span>
          <span>✎ <?= sanitizar($e['examen']) ?></span>
          <span>👤 <strong><?= sanitizar($e['estudiante']) ?></strong> — <?= sanitizar($e['email']) ?></span>
          <span>🕐 <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></span>
          <span class="badge <?= $e['tipo']==='abierta' ? 'badge-gold' : 'badge-teal' ?>">
            <?= $e['tipo']==='abierta' ? '✏ Respuesta abierta' : '📎 Archivo adjunto' ?>
          </span>
        </div>

        <p style="color:var(--gold); font-size:0.92rem; font-style:italic; margin-bottom:0.75rem;">
          <?= sanitizar($e['pregunta']) ?>
        </p>

        <?php if ($e['tipo'] === 'abierta' && $e['respuesta_texto']): ?>
          <div class="respuesta-texto"><?= sanitizar($e['respuesta_texto']) ?></div>
        <?php elseif ($e['tipo'] === 'archivo'): ?>
          <?php if ($e['archivo_respuesta']): ?>
            <a href="../api/archivo_respuesta.php?id=<?= $e['id'] ?>"
               class="btn btn-outline btn-sm mb-3">📎 Descargar archivo del estudiante</a>
          <?php else: ?>
            <div class="alert alert-info mb-2" style="font-size:0.85rem;">El estudiante aún no ha subido el archivo.</div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- Formulario de calificación -->
        <div class="cal-form">
          <form method="POST">
            <input type="hidden" name="respuesta_id" value="<?= $e['id'] ?>">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Calificación (0 – 100)</label>
                <input type="number" name="calificacion" class="form-control"
                       min="0" max="100" placeholder="Ej: 85" required>
              </div>
              <div class="form-group">
                <label class="form-label">Comentario para el estudiante</label>
                <textarea name="comentario" class="form-control" rows="2"
                          placeholder="Retroalimentación..."></textarea>
              </div>
            </div>
            <button type="submit" name="calificar" class="btn btn-primary btn-sm">
              ✓ Guardar calificación
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Revisadas -->
    <div id="tab-revisadas" class="tab-panel">
      <?php if (empty($revisadas)): ?>
        <div class="card text-center">
          <p style="color:var(--text-muted); margin:0;">Aún no hay entregas revisadas.</p>
        </div>
      <?php else: foreach ($revisadas as $e): ?>
      <div class="entrega-card revisada">
        <div class="entrega-meta">
          <span>👤 <strong><?= sanitizar($e['estudiante']) ?></strong></span>
          <span>✎ <?= sanitizar($e['examen']) ?></span>
          <span>🕐 <?= date('d/m/Y', strtotime($e['created_at'])) ?></span>
        </div>
        <p style="color:var(--text-soft); font-size:0.88rem; font-style:italic; margin-bottom:0.75rem;">
          <?= sanitizar($e['pregunta']) ?>
        </p>
        <?php if ($e['tipo']==='abierta' && $e['respuesta_texto']): ?>
          <div class="respuesta-texto" style="max-height:90px; overflow:hidden;">
            <?= sanitizar($e['respuesta_texto']) ?>
          </div>
        <?php elseif ($e['archivo_respuesta']): ?>
          <a href="../api/archivo_respuesta.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm mb-2">📎 Ver archivo</a>
        <?php endif; ?>

        <div class="d-flex align-center gap-2" style="margin-top:0.5rem;">
          <div class="nota-display"><?= $e['calificacion'] ?></div>
          <span style="color:var(--text-muted);">/100</span>
          <?php if ($e['comentario_pastor']): ?>
            <span style="font-size:0.85rem; color:var(--text-soft);">— <?= sanitizar($e['comentario_pastor']) ?></span>
          <?php endif; ?>
        </div>

        <details style="margin-top:0.75rem;">
          <summary style="font-size:0.8rem; color:var(--text-muted); cursor:pointer;">Editar calificación</summary>
          <div class="cal-form mt-2">
            <form method="POST">
              <input type="hidden" name="respuesta_id" value="<?= $e['id'] ?>">
              <div class="grid-2">
                <div class="form-group">
                  <label class="form-label">Nueva calificación</label>
                  <input type="number" name="calificacion" class="form-control"
                         min="0" max="100" value="<?= $e['calificacion'] ?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Comentario</label>
                  <textarea name="comentario" class="form-control" rows="2"><?= sanitizar($e['comentario_pastor']??'') ?></textarea>
                </div>
              </div>
              <button type="submit" name="calificar" class="btn btn-outline btn-sm">Actualizar</button>
            </form>
          </div>
        </details>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </main>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">RV 1865 — Formación bíblica en línea</p>
</footer>

<script>
function showTab(id, el) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  el.classList.add('active');
}
</script>
</body>
</html>

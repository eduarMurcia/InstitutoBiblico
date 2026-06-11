<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notificaciones.php';
requerir_admin();
require_once __DIR__ . '/../includes/admin_sidebar.php';

$conn  = conectar();
$exito = $error = '';

// ── Guardar respuesta del pastor ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder'])) {
    $cid      = (int)$_POST['comentario_id'];
    $respuesta = sanitizar($_POST['respuesta'] ?? '');
    if ($respuesta && $cid) {
        $s = $conn->prepare("UPDATE comentarios SET respuesta_pastor=?, fecha_respuesta=NOW() WHERE id=?");
        $s->bind_param("si", $respuesta, $cid);
        $s->execute(); $s->close();

        // Obtener datos del comentario y del estudiante
        $s = $conn->prepare("
            SELECT co.usuario_id, u.nombre, u.email, u.notif_email,
                   l.titulo AS leccion, c.titulo AS curso, mo.curso_id
            FROM comentarios co
            JOIN usuarios u  ON u.id  = co.usuario_id
            JOIN lecciones l ON l.id  = co.leccion_id
            JOIN modulos m   ON m.id  = l.modulo_id
            JOIN cursos c    ON c.id  = m.curso_id
            WHERE co.id=?
        ");
        $s->bind_param("i", $cid); $s->execute();
        $meta = $s->get_result()->fetch_assoc(); $s->close();

        if ($meta) {
            // Notificación interna (siempre) + email (si activado)
            notificar_estudiante(
                $meta['usuario_id'],
                'comentario_respondido',
                'El pastor respondió tu comentario',
                "En la lección \"{$meta['leccion']}\" del curso \"{$meta['curso']}\": {$respuesta}",
                'cursos.php?id=' . $meta['curso_id']
            );
        }

        $exito = 'Respuesta guardada.';
    }
}

// ── Filtro ──
$filtro_curso = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
$filtro_estado = $_GET['estado'] ?? 'pendientes'; // pendientes | todos

$where_curso  = $filtro_curso ? "AND mo.curso_id=$filtro_curso" : '';
$where_estado = $filtro_estado === 'pendientes'
    ? "AND (co.respuesta_pastor IS NULL OR co.respuesta_pastor='')"
    : '';

$comentarios = $conn->query("
    SELECT co.id, co.mensaje, co.created_at, co.respuesta_pastor, co.fecha_respuesta,
           u.nombre AS estudiante, u.email,
           l.titulo AS leccion, l.id AS leccion_id,
           m.titulo AS modulo,
           c.titulo AS curso, c.id AS curso_id
    FROM comentarios co
    JOIN usuarios u  ON u.id  = co.usuario_id
    JOIN lecciones l ON l.id  = co.leccion_id
    JOIN modulos m   ON m.id  = l.modulo_id
    JOIN cursos c    ON c.id  = m.curso_id
    WHERE 1=1 $where_curso $where_estado
    ORDER BY co.created_at DESC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

$cursos_lista = $conn->query("SELECT id, titulo FROM cursos ORDER BY orden, titulo")->fetch_all(MYSQLI_ASSOC);

// Contar pendientes
$n_pend = $conn->query("SELECT COUNT(*) AS n FROM comentarios WHERE respuesta_pastor IS NULL OR respuesta_pastor=''")->fetch_assoc()['n'];

$notif_sin_leer = contar_notificaciones_sin_leer();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Comentarios — Admin IBB</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .com-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      margin-bottom: 1.1rem;
      box-shadow: var(--shadow);
    }
    .com-card.pendiente { border-left: 3px solid var(--gold); }
    .com-card.respondido { border-left: 3px solid #27ae60; opacity:0.88; }
    .com-meta {
      font-size: 0.8rem; color: var(--text-muted);
      display: flex; flex-wrap: wrap; gap: 0.4rem 1.25rem;
      margin-bottom: 0.85rem;
    }
    .com-meta strong { color: var(--navy); }
    .com-mensaje {
      background: var(--bg); border: 1px solid var(--border);
      border-radius: var(--radius); padding: 0.9rem 1rem;
      font-size: 0.95rem; color: var(--text-soft);
      line-height: 1.6; margin-bottom: 1rem;
      white-space: pre-wrap;
    }
    .com-respuesta {
      background: rgba(39,174,96,0.07);
      border: 1px solid rgba(39,174,96,0.25);
      border-radius: var(--radius); padding: 0.9rem 1rem;
      font-size: 0.9rem; color: var(--text-soft);
      line-height: 1.6; margin-bottom: 0.5rem;
    }
    .reply-form {
      background: var(--bg);
      border: 1px solid var(--border-gold);
      border-radius: var(--radius);
      padding: 1rem 1.25rem;
      margin-top: 0.5rem;
    }
    .tabs-nav { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:2rem; }
    .tab-item { padding:0.65rem 1.5rem; font-family:var(--font-ui); font-size:0.78rem; font-weight:700; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; color:var(--text-muted); transition:all 0.2s; }
    .tab-item.active { color:var(--navy); border-bottom-color:var(--gold); }
  </style>
</head>
<body>

<?php admin_navbar("comentarios"); ?>

<div class="main-layout">
  <?php admin_sidebar("comentarios"); ?>

  <main class="main-content">
    <span style="font-family:var(--font-ui);font-size:0.72rem;letter-spacing:0.15em;text-transform:uppercase;color:var(--gold);">Interacción</span>
    <h2 style="margin:0.4rem 0 0.25rem;">Comentarios de estudiantes</h2>
    <hr class="divider-gold left" style="margin-bottom:1.5rem;">

    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>

    <!-- Filtros -->
    <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;margin-bottom:1.5rem;">
      <select name="curso_id" class="form-control" style="max-width:300px;">
        <option value="">— Todos los cursos —</option>
        <?php foreach ($cursos_lista as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= $filtro_curso===$cl['id']?'selected':'' ?>><?= sanitizar($cl['titulo']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="estado" id="estado-hidden" value="<?= htmlspecialchars($filtro_estado) ?>">
      <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
      <?php if ($filtro_curso): ?>
        <a href="comentarios.php" class="btn btn-ghost btn-sm">✕ Ver todos</a>
      <?php endif; ?>
    </form>

    <!-- Tabs -->
    <div class="tabs-nav">
      <div class="tab-item <?= $filtro_estado==='pendientes'?'active':'' ?>"
           onclick="cambiarEstado('pendientes')">
        Sin responder
        <span class="badge badge-gold" style="margin-left:0.4rem;"><?= $n_pend ?></span>
      </div>
      <div class="tab-item <?= $filtro_estado==='todos'?'active':'' ?>"
           onclick="cambiarEstado('todos')">
        Todos
      </div>
    </div>

    <!-- Lista -->
    <?php if (empty($comentarios)): ?>
      <div class="card text-center">
        <p style="color:var(--text-muted);margin:0;">
          <?= $filtro_estado==='pendientes' ? '✓ No hay comentarios sin responder.' : 'No hay comentarios aún.' ?>
        </p>
      </div>
    <?php else: foreach ($comentarios as $com):
      $respondido = !empty($com['respuesta_pastor']);
    ?>
    <div class="com-card <?= $respondido ? 'respondido' : 'pendiente' ?>">
      <div class="com-meta">
        <span>👤 <strong><?= sanitizar($com['estudiante']) ?></strong> — <?= sanitizar($com['email']) ?></span>
        <span>📖 <?= sanitizar($com['curso']) ?> › <?= sanitizar($com['modulo']) ?> › <?= sanitizar($com['leccion']) ?></span>
        <span>🕐 <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?></span>
        <?php if ($respondido): ?>
          <span class="badge badge-success" style="font-size:0.7rem;">✓ Respondido</span>
        <?php else: ?>
          <span class="badge badge-gold" style="font-size:0.7rem;">⏳ Sin responder</span>
        <?php endif; ?>
      </div>

      <!-- Mensaje del estudiante -->
      <div class="com-mensaje"><?= sanitizar($com['mensaje']) ?></div>

      <!-- Respuesta existente -->
      <?php if ($respondido): ?>
        <div class="com-respuesta">
          <strong style="font-size:0.78rem;color:#27ae60;">Respuesta del pastor</strong>
          <span style="font-size:0.75rem;color:var(--text-muted);margin-left:0.5rem;"><?= date('d/m/Y H:i', strtotime($com['fecha_respuesta'])) ?></span>
          <p style="margin:0.4rem 0 0;"><?= sanitizar($com['respuesta_pastor']) ?></p>
        </div>
        <details style="margin-top:0.5rem;">
          <summary style="font-size:0.8rem;color:var(--text-muted);cursor:pointer;">Editar respuesta</summary>
          <div class="reply-form mt-2">
            <form method="POST">
              <input type="hidden" name="comentario_id" value="<?= $com['id'] ?>">
              <div class="form-group">
                <textarea name="respuesta" class="form-control" rows="3" required><?= sanitizar($com['respuesta_pastor']) ?></textarea>
              </div>
              <button type="submit" name="responder" class="btn btn-outline btn-sm">Actualizar</button>
            </form>
          </div>
        </details>
      <?php else: ?>
        <!-- Formulario de respuesta -->
        <div class="reply-form">
          <form method="POST">
            <input type="hidden" name="comentario_id" value="<?= $com['id'] ?>">
            <div class="form-group">
              <label class="form-label">Respuesta del pastor</label>
              <textarea name="respuesta" class="form-control" rows="3"
                        placeholder="Escribe tu respuesta al estudiante..." required></textarea>
            </div>
            <button type="submit" name="responder" class="btn btn-primary btn-sm">
              ✓ Enviar respuesta
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>

  </main>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865</p>
</footer>

<script>
function cambiarEstado(estado) {
  document.getElementById('estado-hidden').value = estado;
  document.querySelector('form[method="GET"]').submit();
}
</script>
</body>
</html>

<?php
require_once 'includes/auth.php';
require_once 'includes/estudiante_layout.php';
require_once 'config/db.php';
require_once 'includes/portada_curso.php';
require_once 'includes/iconos.php';
require_once 'includes/inspiracion.php';
requerir_login();

$conn = conectar();
$uid  = $_SESSION['usuario_id'];

$cursos_q = $conn->prepare("
    SELECT c.id, c.titulo, c.descripcion, c.instructor, c.imagen, c.color_portada, c.icono_portada,
           COUNT(DISTINCT l.id) AS total_lecciones,
           COUNT(DISTINCT CASE WHEN p.completado=1 THEN p.leccion_id END) AS completadas
    FROM cursos c
    LEFT JOIN modulos m ON m.curso_id=c.id
    LEFT JOIN lecciones l ON l.modulo_id=m.id
    LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=?
    WHERE c.publicado=1
    GROUP BY c.id ORDER BY c.orden, c.id
");
$cursos_q->bind_param("i", $uid); $cursos_q->execute();
$cursos = $cursos_q->get_result()->fetch_all(MYSQLI_ASSOC);

$total_l   = array_sum(array_column($cursos, 'total_lecciones'));
$total_c   = array_sum(array_column($cursos, 'completadas'));
$pct_total = $total_l > 0 ? round(($total_c / $total_l) * 100) : 0;

// Examenes pendientes de revisión
$r = $conn->prepare("SELECT COUNT(*) AS n FROM resultados_examen WHERE usuario_id=? AND pendiente_revision=1");
$r->bind_param("i",$uid); $r->execute();
$pendientes_revision = $r->get_result()->fetch_assoc()['n'];

// Comentarios recientes
$comentarios_q = $conn->prepare("
    SELECT co.mensaje, co.created_at, l.titulo AS leccion, c.titulo AS curso
    FROM comentarios co
    JOIN lecciones l ON l.id=co.leccion_id
    JOIN modulos mo ON mo.id=l.modulo_id
    JOIN cursos c ON c.id=mo.curso_id
    WHERE co.usuario_id=? ORDER BY co.created_at DESC LIMIT 4
");
$comentarios_q->bind_param("i",$uid); $comentarios_q->execute();
$comentarios = $comentarios_q->get_result()->fetch_all(MYSQLI_ASSOC);

// Notificaciones del estudiante (sin leer)
$notif_q = $conn->prepare("
    SELECT id, tipo, titulo, mensaje, url, created_at
    FROM notificaciones_estudiante
    WHERE usuario_id=? AND leida=0
    ORDER BY created_at DESC LIMIT 10
");
$notif_q->bind_param("i",$uid); $notif_q->execute();
$mis_notificaciones = $notif_q->get_result()->fetch_all(MYSQLI_ASSOC);
$n_notif = count($mis_notificaciones);

// Marcar como leídas al ver el dashboard
if ($n_notif > 0) {
    $conn->query("UPDATE notificaciones_estudiante SET leida=1 WHERE usuario_id=$uid AND leida=0");
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Mi Panel — Instituto Bíblico Bautista</title>
  <link rel="stylesheet" href="css/styles.css">
  <?php portada_css(); ?>
  <style>
    .curso-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      transition: all 0.2s;
    }
    .curso-card:hover { border-color:var(--azul); box-shadow:var(--shadow-lg); }
    .comment-item { padding:0.85rem 0; border-bottom:1px solid var(--border); }
    .comment-item:last-child { border-bottom:none; }
    .comment-meta { font-size:0.78rem; color:var(--text-muted); margin-bottom:0.25rem; }
  </style>
</head>
<body>

<?php estudiante_navbar("dashboard"); ?><div class="main-layout">
  <?php estudiante_sidebar("dashboard"); ?>

  <main class="main-content">

    <!-- Aviso exámenes pendientes -->
    <?php if ($pendientes_revision > 0): ?>
    <div class="notif-bar">
      ⏳ Tienes <?= $pendientes_revision ?> examen<?= $pendientes_revision>1?'es':'' ?> pendiente<?= $pendientes_revision>1?'s':'' ?> de calificación por el pastor.
    </div>
    <?php endif; ?>

    <?php if (!empty($mis_notificaciones)): ?>
    <div style="margin-bottom:1.5rem;">
      <?php
      $iconos = ['comentario_respondido'=>'💬', 'examen_calificado'=>'📋'];
      foreach ($mis_notificaciones as $notif):
      ?>
      <div style="display:flex; align-items:flex-start; gap:0.85rem; padding:0.85rem 1.1rem; background:var(--bg-card); border:1px solid var(--border-gold); border-left:3px solid var(--gold); border-radius:var(--radius); margin-bottom:0.5rem; box-shadow:var(--shadow);">
        <span style="font-size:1.2rem; flex-shrink:0; margin-top:2px;"><?= $iconos[$notif['tipo']] ?? '🔔' ?></span>
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; color:var(--navy); font-size:0.92rem;"><?= sanitizar($notif['titulo']) ?></div>
          <div style="font-size:0.85rem; color:var(--text-soft); margin-top:0.15rem; line-height:1.5;"><?= sanitizar(mb_strimwidth($notif['mensaje'], 0, 140, '…')) ?></div>
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap; flex-shrink:0; margin-top:2px;">
          <?= date('d/m H:i', strtotime($notif['created_at'])) ?>
        </div>
        <?php if ($notif['url']): ?>
        <a href="<?= htmlspecialchars($notif['url']) ?>" class="btn btn-outline btn-sm" style="flex-shrink:0;">Ver →</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Hero de bienvenida -->
    <?php $v = versiculo_del_dia(); ?>
    <section class="hero-welcome">
      <div class="hero-text">
        <div class="hero-eyebrow">¡Bienvenido de nuevo!</div>
        <h1><?= sanitizar($_SESSION['nombre']) ?>, <?= sanitizar(frase_del_dia()) ?></h1>
        <?php if ($v['texto']): ?>
        <p class="hero-verse">
          “<?= sanitizar($v['texto']) ?>”
          <cite><?= sanitizar($v['cita']) ?></cite>
        </p>
        <?php endif; ?>
        <div class="hero-actions">
          <a href="cursos.php?filtro=iniciados" class="btn btn-primary">Ver mis cursos →</a>
          <a href="cursos.php" class="btn btn-outline btn-lift">Ver todos los cursos →</a>
        </div>
      </div>
      <div class="hero-figure">
        <img class="hero-img" src="assets/hero-biblia.jpg" alt="Estudio bíblico">
        <span class="hero-dot-gold"></span>
        <span class="hero-dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
      </div>
    </section>

    <!-- Stats -->
    <div class="grid-3 mb-4">
      <div class="stat-card">
        <div class="stat-icon"><?= icono('cursos') ?></div>
        <div>
          <div class="stat-num"><?= count($cursos) ?></div>
          <div class="stat-label">Cursos disponibles</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold"><?= icono('completadas') ?></div>
        <div>
          <div class="stat-num"><?= $total_c ?></div>
          <div class="stat-label">Lecciones completadas</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= icono('progreso') ?></div>
        <div>
          <div class="stat-num"><?= $pct_total ?>%</div>
          <div class="stat-label">Progreso general</div>
        </div>
      </div>
    </div>

    <!-- Progreso general -->
    <?php if ($total_l > 0): ?>
    <div class="card mb-4">
      <div class="d-flex justify-between align-center mb-2">
        <h3 style="margin:0;">Progreso general</h3>
        <span style="font-size:0.85rem; color:var(--text-muted);"><?= $total_c ?> de <?= $total_l ?> lecciones</span>
      </div>
      <div class="progress-wrap">
        <div class="progress-bar" style="width:<?= $pct_total ?>%"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Mis cursos -->
    <h3 class="mb-2">Mis cursos</h3>
    <?php if (empty($cursos)): ?>
      <div class="card text-center">
        <p style="margin:0; color:var(--text-muted);">Aún no hay cursos disponibles. El pastor publicará el material pronto.</p>
      </div>
    <?php else: ?>
    <div class="cursos-portada-grid mb-4">
      <?php foreach ($cursos as $c):
        $pct = $c['total_lecciones'] > 0 ? round(($c['completadas'] / $c['total_lecciones']) * 100) : 0;
      ?>
        <?php portada_curso($c, [
          'modo'   => 'card',
          'pct'    => $pct,
          'enlace' => 'cursos.php?id=' . $c['id'],
        ]); ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Comentarios recientes -->
    <?php if (!empty($comentarios)): ?>
    <div class="card">
      <h3 class="mb-2">Mis participaciones recientes</h3>
      <?php foreach ($comentarios as $com): ?>
      <div class="comment-item">
        <div class="comment-meta">
          <strong><?= sanitizar($com['curso']) ?></strong> › <?= sanitizar($com['leccion']) ?>
          — <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?>
        </div>
        <p style="margin:0; font-size:0.95rem;"><?= sanitizar($com['mensaje']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865 — Formación bíblica en línea</p>
</footer>
</body>
</html>

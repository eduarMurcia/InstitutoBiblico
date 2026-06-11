<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_admin();
require_once __DIR__ . '/../includes/admin_sidebar.php';
require_once __DIR__ . '/../includes/inspiracion.php';

$conn = conectar();
$stats = [];
foreach (['cursos','lecciones','comentarios'] as $t) {
    $r = $conn->query("SELECT COUNT(*) AS n FROM `$t`");
    $stats[$t] = $r->fetch_assoc()['n'];
}
$r = $conn->query("SELECT COUNT(*) AS n FROM usuarios WHERE rol='estudiante'");
$stats['estudiantes'] = $r->fetch_assoc()['n'];

$usuarios_q = $conn->query("SELECT nombre, email, created_at FROM usuarios WHERE rol='estudiante' ORDER BY created_at DESC LIMIT 8");
$ultimos_usuarios = $usuarios_q->fetch_all(MYSQLI_ASSOC);

$cursos_q = $conn->query("SELECT id, titulo, publicado, created_at FROM cursos ORDER BY created_at DESC LIMIT 5");
$ultimos_cursos = $cursos_q->fetch_all(MYSQLI_ASSOC);

// Entregas pendientes
$r = $conn->query("SELECT COUNT(*) AS n FROM respuestas_examen re JOIN preguntas p ON p.id=re.pregunta_id WHERE p.tipo IN ('abierta','archivo') AND re.revisado=0");
$pendientes = $r ? $r->fetch_assoc()['n'] : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Panel Administrativo — Instituto Bíblico Bautista</title>
  <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<?php admin_navbar("resumen"); ?>

<div class="main-layout">
  <?php admin_sidebar("resumen"); ?>

  <main class="main-content">
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
          <a href="cursos.php?accion=nuevo" class="btn btn-primary">+ Nuevo curso</a>
          <a href="entregas.php" class="btn btn-outline btn-lift">Ver entregas →</a>
        </div>
      </div>
      <div class="hero-figure">
        <img class="hero-img" src="../assets/hero-biblia.jpg" alt="Estudio bíblico">
        <span class="hero-dot-gold"></span>
        <span class="hero-dots"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
      </div>
    </section>

    <!-- Estadísticas -->
    <div class="grid-3 mb-4">
      <div class="stat-card">
        <div class="stat-icon"><?= icono('usuarios') ?></div>
        <div>
          <div class="stat-num"><?= $stats['estudiantes'] ?></div>
          <div class="stat-label">Estudiantes</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold"><?= icono('cursos') ?></div>
        <div>
          <div class="stat-num"><?= $stats['cursos'] ?></div>
          <div class="stat-label">Cursos</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= icono('lecciones') ?></div>
        <div>
          <div class="stat-num"><?= $stats['lecciones'] ?></div>
          <div class="stat-label">Lecciones</div>
        </div>
      </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="card mb-4">
      <h3 class="mb-3">Acciones rápidas</h3>
      <div class="d-flex gap-2" style="flex-wrap:wrap;">
        <a href="cursos.php?accion=nuevo" class="btn btn-outline btn-lift">+ Nuevo curso</a>
        <a href="cursos.php"   class="btn btn-outline btn-lift">📖 Gestionar cursos</a>
        <a href="usuarios.php" class="btn btn-outline btn-lift">👥 Ver estudiantes</a>
        <a href="examenes.php" class="btn btn-outline btn-lift">✎ Gestionar exámenes</a>
        <a href="entregas.php" class="btn btn-outline btn-lift">
          📬 Entregas
          <?php if ($pendientes > 0): ?>
            <span class="badge badge-gold" style="margin-left:0.25rem;"><?= $pendientes ?> pendiente<?= $pendientes>1?'s':'' ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>

    <div class="grid-2">
      <!-- Últimos registros -->
      <div class="card">
        <h3 class="mb-3">Últimos registros</h3>
        <?php if (empty($ultimos_usuarios)): ?>
          <p style="color:var(--text-muted); margin:0;">Aún no hay estudiantes registrados.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Nombre</th><th>Correo</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php foreach ($ultimos_usuarios as $u): ?>
            <tr>
              <td><?= sanitizar($u['nombre']) ?></td>
              <td style="font-size:0.82rem; color:var(--text-muted);"><?= sanitizar($u['email']) ?></td>
              <td style="font-size:0.8rem;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <a href="usuarios.php" class="btn btn-ghost btn-sm btn-hover-azul btn-lift mt-2">Ver todos →</a>
        <?php endif; ?>
      </div>

      <!-- Cursos recientes -->
      <div class="card">
        <h3 class="mb-3">Cursos recientes</h3>
        <?php if (empty($ultimos_cursos)): ?>
          <p style="color:var(--text-muted); margin:0;">Aún no hay cursos creados.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Título</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($ultimos_cursos as $c): ?>
            <tr>
              <td><?= sanitizar($c['titulo']) ?></td>
              <td>
                <span class="badge <?= $c['publicado'] ? 'badge-success' : 'badge-gray' ?>">
                  <?= $c['publicado'] ? 'Publicado' : 'Borrador' ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <a href="cursos.php" class="btn btn-ghost btn-sm btn-hover-azul btn-lift mt-2">Gestionar →</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">RV 1865 — Formación bíblica en línea</p>
</footer>
</body>
</html>

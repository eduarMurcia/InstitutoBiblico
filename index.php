<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (esta_logueado()) {
    redirigir(es_admin() ? 'admin/index.php' : 'dashboard.php');
}

$conn = conectar();
$cursos_q = $conn->query("SELECT id, titulo, descripcion, instructor FROM cursos WHERE publicado=1 ORDER BY orden, id");
$cursos = $cursos_q ? $cursos_q->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Instituto Bíblico Bautista — Formación Bíblica en Línea</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    body { display:flex; flex-direction:column; min-height:100vh; }
    main { flex:1; }
    .curso-landing-card {
      background: rgba(0,20,64,0.4);
      border: 1px solid rgba(209,147,9,0.2);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      transition: border-color 0.2s, background 0.2s;
    }
    .curso-landing-card:hover {
      border-color: var(--gold);
      background: rgba(0,20,64,0.6);
    }
    .curso-landing-card h4 { color: #f5f0e8; margin-bottom: 0.35rem; }
    .curso-landing-card .instructor { font-size: 0.8rem; color: var(--gold); margin-bottom: 0.5rem; }
    .curso-landing-card p { color: rgba(245,240,232,0.65); font-size: 0.88rem; margin: 0; line-height: 1.5; }
  </style>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="navbar-brand">
    <div class="brand-icon">✦</div>
    <div class="brand-name">Instituto Bíblico Bautista<span>RV 1865</span></div>
  </a>
  <ul class="navbar-links">
    <li><a href="#cursos">Cursos</a></li>
    <li><a href="#acerca">Acerca</a></li>
    <li><a href="login.php" class="btn-nav">Ingresar</a></li>
  </ul>
  <button class="nav-toggle" onclick="document.querySelector('.navbar-links').classList.toggle('open')" aria-label="Menú">
    <span></span><span></span><span></span>
  </button>
</nav>

<main>
  <!-- Hero -->
  <section class="hero">
    <div class="hero-content">
      <div class="hero-eyebrow">Formación Bíblica Gratuita</div>
      <h1>Escucha y estudia la<br>Palabra de Dios</h1>
      <p class="hero-subtitle">Accede a clases bíblicas y teológicas grabadas por nuestros pastores, a tu propio ritmo y desde cualquier lugar.</p>
      <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
        <a href="login.php?modo=registro" class="btn btn-primary" style="font-size:1rem; padding:0.75rem 2rem;">Comenzar gratis</a>
        <a href="login.php" class="btn btn-outline" style="font-size:1rem; padding:0.75rem 2rem; border-color:rgba(255,255,255,0.3); color:#f5f0e8;">Ya tengo cuenta</a>
      </div>
      <p class="hero-verse">"Escudriñad las Escrituras" — Juan 5:39</p>
    </div>
  </section>

  <!-- Características -->
  <section class="section" style="background:var(--bg);">
    <div style="text-align:center; margin-bottom:3rem;">
      <span class="section-label">Lo que ofrecemos</span>
      <h2>Todo lo que necesitas para crecer en la fe</h2>
    </div>
    <div class="features-grid" style="background:transparent;">
      <?php
      $features = [
        ['🎧', 'Clases en audio', 'Lecciones grabadas por pastores, disponibles sin conexión.'],
        ['📚', 'Módulos progresivos', 'Contenido organizado paso a paso para un aprendizaje sólido.'],
        ['📝', 'Exámenes de comprensión', 'Evalúa tu entendimiento al final de cada módulo.'],
        ['🏅', 'Certificados', 'Recibe un certificado al completar cada curso.'],
        ['💬', 'Foros de discusión', 'Comenta y dialoga sobre cada lección con tu pastor.'],
        ['📊', 'Seguimiento de progreso', 'Visualiza tu avance en todos los cursos.'],
      ];
      foreach ($features as [$icon, $title, $desc]):
      ?>
      <div class="feature-card">
        <span class="feature-icon"><?= $icon ?></span>
        <h3 style="color:#f5f0e8; margin-bottom:0.5rem;"><?= $title ?></h3>
        <p style="color:rgba(245,240,232,0.6); margin:0; font-size:0.9rem;"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Cursos disponibles -->
  <?php if (!empty($cursos)): ?>
  <section class="section" id="cursos" style="background:var(--navy);">
    <div style="text-align:center; margin-bottom:3rem;">
      <span class="section-label">Catálogo</span>
      <h2 style="color:#f5f0e8;">Cursos disponibles</h2>
    </div>
    <div class="grid-2" style="max-width:900px; margin:0 auto;">
      <?php foreach ($cursos as $c): ?>
      <div class="curso-landing-card">
        <h4><?= sanitizar($c['titulo']) ?></h4>
        <?php if ($c['instructor']): ?>
          <div class="instructor">Instructor: <?= sanitizar($c['instructor']) ?></div>
        <?php endif; ?>
        <?php if ($c['descripcion']): ?>
          <p><?= sanitizar(mb_strimwidth($c['descripcion'], 0, 120, '…')) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center; margin-top:2.5rem;">
      <a href="login.php?modo=registro" class="btn btn-primary" style="font-size:1rem; padding:0.75rem 2rem;">Registrarme para estudiar</a>
    </div>
  </section>
  <?php endif; ?>

  <!-- Misión -->
  <section class="cta-section" id="acerca">
    <div style="max-width:680px; margin:0 auto;">
      <span class="section-label" style="display:block; text-align:center;">Nuestra misión</span>
      <h2 style="text-align:center; margin-bottom:1rem;">Formación bíblica sin barreras</h2>
      <p style="text-align:center; color:var(--text-soft); font-size:1.05rem; line-height:1.7; margin-bottom:2rem;">
        Llevamos educación bíblica gratuita y de calidad a comunidades hispanohablantes alrededor del mundo,
        siguiendo fielmente la Biblia Reina-Valera 1865.
      </p>
      <div class="verse-block">
        "Toda la Escritura es inspirada divinamente, y útil para enseñar..."
        <span class="verse-ref">2 Timoteo 3:16</span>
      </div>
    </div>
  </section>
</main>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865 — Formación bíblica en línea</p>
</footer>

</body>
</html>

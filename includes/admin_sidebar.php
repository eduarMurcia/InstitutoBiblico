<?php
// includes/admin_sidebar.php
// Include en TODAS las páginas admin después de conectar()
// Carga contadores del sidebar y renderiza navbar + sidebar de forma centralizada

require_once __DIR__ . '/iconos.php';

if (!isset($conn) || !$conn) {
    $conn = conectar();
}

if (!function_exists('contar_notificaciones_sin_leer')) {
    require_once __DIR__ . '/notificaciones.php';
}

// Contadores
$_sb_notif       = contar_notificaciones_sin_leer();
$_r = $conn->query("SELECT COUNT(*) AS n FROM comentarios WHERE respuesta_pastor IS NULL OR respuesta_pastor=''");
$_sb_comentarios = $_r ? (int)$_r->fetch_assoc()['n'] : 0;
$_r = $conn->query("SELECT COUNT(*) AS n FROM respuestas_examen re JOIN preguntas p ON p.id=re.pregunta_id WHERE p.tipo IN ('abierta','archivo') AND re.revisado=0");
$_sb_entregas    = $_r ? (int)$_r->fetch_assoc()['n'] : 0;

// ── Navbar unificado ──
function admin_navbar(string $activa = ''): void {
    $prefix = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '' : 'admin/';
    ?>
    <nav class="navbar">
      <a href="<?= $prefix ?>index.php" class="navbar-brand">
        <div class="brand-icon">✦</div>
        <div class="brand-name">Instituto Bíblico Bautista<span>Admin</span></div>
      </a>
      <ul class="navbar-links">
        <li><a href="<?= $prefix ?>index.php" <?= $activa==='resumen' ? 'class="active"' : '' ?>>Panel</a></li>
        <li><a href="<?= $prefix ?>../dashboard.php">Ver plataforma</a></li>
        <li><a href="<?= $prefix ?>../api/logout.php" style="display:inline-flex;align-items:center;gap:0.4rem;"><?= icono('power') ?> Salir</a></li>
      </ul>
      <button class="nav-toggle" aria-label="Menú"
              onclick="document.querySelector('.navbar-links').classList.toggle('open')">
        <span></span><span></span><span></span>
      </button>
    </nav>
    <?php
}

// ── Sidebar unificado ──
function admin_sidebar(string $activa = ''): void {
    global $_sb_comentarios, $_sb_entregas;
    $prefix = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '' : 'admin/';
    ?>
    <aside class="sidebar">
      <p class="sidebar-section-label">Administración</p>
      <a href="<?= $prefix ?>index.php"        class="sidebar-link <?= $activa==='resumen'      ?'active':'' ?>"><?= icono('panel') ?> Resumen</a>
      <a href="<?= $prefix ?>cursos.php"       class="sidebar-link <?= $activa==='cursos'       ?'active':'' ?>"><?= icono('cursos') ?> Cursos</a>
      <a href="<?= $prefix ?>usuarios.php"     class="sidebar-link <?= $activa==='usuarios'     ?'active':'' ?>"><?= icono('usuarios') ?> Usuarios</a>
      <a href="<?= $prefix ?>examenes.php"     class="sidebar-link <?= $activa==='examenes'     ?'active':'' ?>"><?= icono('examen') ?> Exámenes</a>
      <a href="<?= $prefix ?>entregas.php"     class="sidebar-link <?= $activa==='entregas'     ?'active':'' ?>">
        <?= icono('entregas') ?> Entregas
        <?php if ($_sb_entregas > 0): ?>
          <span style="margin-left:auto;background:var(--gold);color:var(--navy);font-size:0.65rem;font-weight:700;border-radius:999px;padding:1px 6px;"><?= $_sb_entregas ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= $prefix ?>comentarios.php"  class="sidebar-link <?= $activa==='comentarios' ?'active':'' ?>">
        <?= icono('comentarios') ?> Comentarios
        <?php if ($_sb_comentarios > 0): ?>
          <span style="margin-left:auto;background:var(--gold);color:var(--navy);font-size:0.65rem;font-weight:700;border-radius:999px;padding:1px 6px;"><?= $_sb_comentarios ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= $prefix ?>invitaciones.php" class="sidebar-link <?= $activa==='invitaciones'?'active':'' ?>"><?= icono('invitaciones') ?> Invitaciones</a>
      <hr style="border-color:var(--border);margin:1rem 1.25rem;">
      <p class="sidebar-section-label">Estudiante</p>
      <a href="<?= $prefix ?>../dashboard.php"  class="sidebar-link"><?= icono('externo') ?> Ver plataforma</a>
      <a href="<?= $prefix ?>../api/logout.php" class="sidebar-link" style="color:#c0392b;"><?= icono('salir') ?> Cerrar sesión</a>
    </aside>
    <?php
}

// Stub de compatibilidad (ya no muestra nada)
function admin_campana(): void {}

<?php
// includes/estudiante_layout.php
// Navbar y sidebar unificados para páginas del estudiante
// Requiere: auth.php ya incluido, sesión activa
require_once __DIR__ . '/iconos.php';

function estudiante_navbar(string $activa = '', array $extra = []): void {
    // $extra: ['label'=>'← Volver al curso', 'href'=>'cursos.php?id=1']
    $nombre = sanitizar($_SESSION['nombre'] ?? '');
    ?>
    <nav class="navbar">
      <a href="index.php" class="navbar-brand">
        <div class="brand-icon">✦</div>
        <div class="brand-name">Instituto Bíblico Bautista<span>RV 1865</span></div>
      </a>
      <ul class="navbar-links">
        <?php if (!empty($extra)): ?>
          <li><a href="<?= htmlspecialchars($extra['href']) ?>"><?= htmlspecialchars($extra['label']) ?></a></li>
        <?php else: ?>
          <li><a href="dashboard.php" <?= $activa==='dashboard' ? 'class="active"' : '' ?>>Mi Panel</a></li>
          <li><a href="cursos.php"    <?= $activa==='cursos'    ? 'class="active"' : '' ?>>Cursos</a></li>
          <li><a href="perfil.php"    <?= $activa==='perfil'    ? 'class="active"' : '' ?>>Mi Perfil</a></li>
          <?php if (es_admin()): ?>
          <li><a href="admin/index.php" style="display:inline-flex;align-items:center;gap:0.35rem;color:var(--gold-light);"><?= icono('config') ?> Admin</a></li>
          <?php endif; ?>
        <?php endif; ?>
        <li><a href="api/logout.php" style="display:inline-flex;align-items:center;gap:0.4rem;"><?= icono('power') ?> Salir</a></li>
      </ul>
      <button class="nav-toggle" aria-label="Menú"
              onclick="document.querySelector('.navbar-links').classList.toggle('open')">
        <span></span><span></span><span></span>
      </button>
    </nav>
    <?php
}

function estudiante_sidebar(string $activa = ''): void {
    $nombre = sanitizar($_SESSION['nombre'] ?? '');
    $inicial = strtoupper(mb_substr($nombre, 0, 1));
    $rol_txt = es_admin() ? '⚙ Administrador' : '✦ Estudiante';
    ?>
    <aside class="sidebar">
      <div style="padding:0 1.25rem 1.25rem;border-bottom:1px solid var(--border);margin-bottom:1.25rem;text-align:center;">
        <div class="avatar"><?= $inicial ?></div>
        <div style="font-family:var(--font-title);font-size:1rem;color:var(--navy);"><?= $nombre ?></div>
        <div style="font-size:0.78rem;color:var(--gold);margin-top:0.2rem;"><?= $rol_txt ?></div>
      </div>
      <p class="sidebar-section-label">Navegación</p>
      <a href="dashboard.php" class="sidebar-link <?= $activa==='dashboard'?'active':'' ?>"><?= icono('panel') ?> Mi Panel</a>
      <a href="cursos.php"    class="sidebar-link <?= $activa==='cursos'   ?'active':'' ?>"><?= icono('cursos') ?> Cursos</a>
      <a href="perfil.php"    class="sidebar-link <?= $activa==='perfil'   ?'active':'' ?>"><?= icono('perfil') ?> Mi Perfil</a>
      <?php if (es_admin()): ?>
      <hr style="border-color:var(--border);margin:1rem 1.25rem;">
      <p class="sidebar-section-label">Administración</p>
      <a href="admin/index.php"  class="sidebar-link"><?= icono('config') ?> Panel Admin</a>
      <a href="admin/cursos.php" class="sidebar-link"><?= icono('mas') ?> Gestionar Cursos</a>
      <?php endif; ?>
      <hr style="border-color:var(--border);margin:1rem 1.25rem;">
      <a href="api/logout.php" class="sidebar-link" style="color:#c0392b;">
        <?= icono('salir') ?> Cerrar sesión
      </a>
    </aside>
    <?php
}

<?php
// admin/notificaciones.php — Página completa de notificaciones del admin
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notificaciones.php';
requerir_admin();
require_once __DIR__ . '/../includes/admin_sidebar.php';

$conn = conectar();

// Marcar como leídas al visitar la página
marcar_todas_leidas();

// Traer todas, las más recientes primero
$notifs = $conn->query(
    "SELECT * FROM notificaciones ORDER BY created_at DESC LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);

$conn->close();

$iconos = ['comentario' => '💬', 'entrega' => '📬', 'sistema' => 'ℹ'];
$colores = ['comentario' => 'badge-gold', 'entrega' => 'badge-teal', 'sistema' => 'badge-gray'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Notificaciones — Admin</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .notif-row {
      display: flex;
      gap: 1rem;
      align-items: flex-start;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }
    .notif-row:last-child { border-bottom: none; }
    .notif-row:hover { background: var(--bg); }
    .notif-icon {
      font-size: 1.4rem;
      flex-shrink: 0;
      margin-top: 0.1rem;
    }
    .notif-titulo { font-weight: 700; color: var(--navy); font-size: 0.95rem; margin-bottom: 0.2rem; }
    .notif-msg    { font-size: 0.88rem; color: var(--text-soft); margin-bottom: 0.35rem; }
    .notif-meta   { font-size: 0.75rem; color: var(--text-muted); }
  </style>
</head>
<body>

<?php admin_navbar(""); ?>

<div class="main-layout">
  <?php admin_sidebar("notificaciones"); ?>

  <main class="main-content">
    <span style="font-family:var(--font-ui); font-size:0.72rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold);">Centro de avisos</span>
    <h2 style="margin:0.4rem 0 0.25rem;">Notificaciones</h2>
    <hr class="divider-gold left" style="margin-bottom:2rem;">

    <div class="card" style="padding:0;">
      <?php if (empty($notifs)): ?>
        <div style="padding:2.5rem; text-align:center; color:var(--text-muted);">
          No hay notificaciones aún.
        </div>
      <?php else: ?>
        <?php foreach ($notifs as $n): ?>
        <div class="notif-row">
          <div class="notif-icon"><?= $iconos[$n['tipo']] ?? 'ℹ' ?></div>
          <div style="flex:1;">
            <div class="notif-titulo"><?= sanitizar($n['titulo']) ?></div>
            <?php if ($n['mensaje']): ?>
              <div class="notif-msg"><?= sanitizar($n['mensaje']) ?></div>
            <?php endif; ?>
            <div class="notif-meta">
              <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
              &nbsp;·&nbsp;
              <span class="badge <?= $colores[$n['tipo']] ?? 'badge-gray' ?>" style="font-size:0.68rem;">
                <?= $n['tipo'] ?>
              </span>
              <?php if ($n['url']): ?>
                &nbsp;·&nbsp;
                <a href="../<?= sanitizar($n['url']) ?>" style="font-size:0.78rem;">Ver →</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865</p>
</footer>
</body>
</html>

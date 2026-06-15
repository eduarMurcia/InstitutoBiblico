<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/admin_sidebar.php';
requerir_admin();

$conn  = conectar();
$exito = $error = '';

// ── Crear invitación ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invitar'])) {
    $email  = sanitizar($_POST['email'] ?? '');
    $nombre = sanitizar($_POST['nombre'] ?? '');
    $dias   = max(1, min(30, (int)($_POST['dias'] ?? 7)));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } else {
        // Verificar que no exista ya una invitación activa para ese email
        $s = $conn->prepare("SELECT id FROM invitaciones WHERE email=? AND usado=0 AND expires_at > NOW()");
        $s->bind_param("s", $email); $s->execute();
        $existe = $s->get_result()->fetch_assoc(); $s->close();

        // Verificar que no sea ya un usuario registrado
        $s = $conn->prepare("SELECT id FROM usuarios WHERE email=?");
        $s->bind_param("s", $email); $s->execute();
        $ya_existe = $s->get_result()->fetch_assoc(); $s->close();

        if ($ya_existe) {
            $error = 'Ya existe una cuenta registrada con ese correo.';
        } elseif ($existe) {
            $error = 'Ya hay una invitación activa pendiente para ese correo.';
        } else {
            $token  = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime("+{$dias} days"));

            $s = $conn->prepare("INSERT INTO invitaciones (email, token, nombre, expires_at) VALUES (?,?,?,?)");
            $s->bind_param("ssss", $email, $token, $nombre, $expira);
            $s->execute(); $s->close();

            // Enviar email con el link
            $link    = "https://institutobiblicobautistacolombia.com/lms/registro_profesor.php?token={$token}";
            $subject = '=?UTF-8?B?' . base64_encode('[IBB] Invitación para unirte como profesor') . '?=';
            $saludo  = $nombre ? "Hola {$nombre}," : "Hola,";
            $body    = "{$saludo}\n\n"
                     . "Has sido invitado a unirte como profesor en la plataforma del Instituto Bíblico Bautista.\n\n"
                     . "Haz clic en el siguiente enlace para crear tu cuenta:\n{$link}\n\n"
                     . "Este enlace es válido por {$dias} día" . ($dias > 1 ? 's' : '') . " y es exclusivo para este correo.\n\n"
                     . "—\nInstituto Bíblico Bautista\nhttps://institutobiblicobautistacolombia.com/lms/";
            $from    = 'no-responder@institutobiblicobautistacolombia.com';
            $headers = "From: Instituto Bíblico Bautista <{$from}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            $enviado = @mail($email, $subject, $body, $headers);

            $exito = "Invitación enviada a <strong>{$email}</strong>." . (!$enviado ? ' (El email podría no haberse entregado — verifica la configuración SMTP del hosting.)' : '');
        }
    }
}

// ── Revocar invitación ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revocar'])) {
    $id = (int)$_POST['inv_id'];
    $conn->query("UPDATE invitaciones SET usado=1 WHERE id=$id AND usado=0");
    $exito = 'Invitación revocada.';
}

// ── Listar invitaciones ──
$invitaciones = $conn->query("
    SELECT id, email, nombre, usado, expires_at, created_at,
           expires_at > NOW() AND usado=0 AS activa
    FROM invitaciones
    ORDER BY created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invitaciones — Admin IBB</title>
  <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<nav class="navbar">
  <a href="../index.php" class="navbar-brand">
    <div class="brand-icon">✦</div>
    <div class="brand-name">Instituto Bíblico Bautista<span>Admin</span></div>
  </a>
  <ul class="navbar-links">
    <li><a href="index.php">Panel</a></li>
    <li><a href="../dashboard.php">Ver plataforma</a></li>
    <li><a href="../api/logout.php" class="btn-nav" style="background:rgba(255,255,255,0.15);color:#f5f0e8 !important;">Salir</a></li>
  </ul>
</nav>

<div class="main-layout">
  <?php admin_sidebar('invitaciones'); ?>

  <main class="main-content">
    <span style="font-family:var(--font-ui);font-size:0.72rem;letter-spacing:.15em;text-transform:uppercase;color:var(--gold);">Acceso de profesores</span>
    <h2 style="margin:.4rem 0 .25rem;">Invitaciones</h2>
    <hr class="divider-gold left" style="margin-bottom:2rem;">

    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <!-- Formulario nueva invitación -->
    <div class="card card-gold-top mb-4">
      <h3 class="mb-1">Invitar profesor</h3>
      <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:1.5rem;">
        El profesor recibirá un link único en su correo para crear su cuenta con acceso de administrador. Solo ese correo puede usar el link.
      </p>
      <form method="POST">
        <div class="grid-2" style="gap:1rem;">
          <div class="form-group">
            <label class="form-label">Correo del profesor *</label>
            <input type="email" name="email" class="form-control"
                   placeholder="pastor@correo.com" required
                   value="<?= sanitizar($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Nombre (opcional)</label>
            <input type="text" name="nombre" class="form-control"
                   placeholder="Ej: Pastor Rodrigo"
                   value="<?= sanitizar($_POST['nombre'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group" style="max-width:200px;">
          <label class="form-label">Válido por</label>
          <select name="dias" class="form-control">
            <option value="3">3 días</option>
            <option value="7" selected>7 días</option>
            <option value="15">15 días</option>
            <option value="30">30 días</option>
          </select>
        </div>
        <button type="submit" name="invitar" class="btn btn-primary">
          <?= icono('correo','ico') ?> Enviar invitación
        </button>
      </form>
    </div>

    <!-- Lista de invitaciones -->
    <h3 class="mb-2">Historial de invitaciones</h3>
    <?php if (empty($invitaciones)): ?>
      <div class="card text-center">
        <p style="margin:0;color:var(--text-muted);">No hay invitaciones enviadas aún.</p>
      </div>
    <?php else: ?>
    <div class="card" style="padding:0;">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Correo</th>
              <th>Nombre</th>
              <th>Enviada</th>
              <th>Expira</th>
              <th style="text-align:center;">Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invitaciones as $inv): ?>
            <tr>
              <td style="font-weight:600;color:var(--navy);"><?= sanitizar($inv['email']) ?></td>
              <td style="color:var(--text-muted);font-size:.88rem;"><?= sanitizar($inv['nombre'] ?: '—') ?></td>
              <td style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y', strtotime($inv['created_at'])) ?></td>
              <td style="font-size:.85rem;color:var(--text-muted);"><?= date('d/m/Y', strtotime($inv['expires_at'])) ?></td>
              <td style="text-align:center;">
                <?php if ($inv['usado']): ?>
                  <span class="badge badge-success">✓ Usado</span>
                <?php elseif (!$inv['activa']): ?>
                  <span class="badge badge-gray">✕ Expirado</span>
                <?php else: ?>
                  <span class="badge badge-gold"><?= icono('pendiente','ico') ?> Pendiente</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($inv['activa']): ?>
                <form method="POST" onsubmit="return confirm('¿Revocar esta invitación?')">
                  <input type="hidden" name="inv_id" value="<?= $inv['id'] ?>">
                  <button type="submit" name="revocar" class="btn btn-ghost btn-sm" style="color:#c0392b;">
                    Revocar
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865</p>
</footer>
</body>
</html>

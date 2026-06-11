<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (esta_logueado()) redirigir('dashboard.php');

$token  = sanitizar($_GET['token'] ?? '');
$exito  = $error = '';
$inv    = null;

// Validar token
if (!$token) {
    redirigir('login.php');
}

$conn = conectar();
$s = $conn->prepare("
    SELECT id, email, nombre FROM invitaciones
    WHERE token=? AND usado=0 AND expires_at > NOW()
");
$s->bind_param("s", $token); $s->execute();
$inv = $s->get_result()->fetch_assoc(); $s->close();

if (!$inv) {
    $conn->close();
    // Token inválido o expirado
    $error_fatal = 'Este enlace de invitación no es válido o ha expirado. Contacta al administrador para recibir uno nuevo.';
}

// ── Procesar registro ──
if (!isset($error_fatal) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar'])) {
    $nombre    = sanitizar($_POST['nombre'] ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$nombre) {
        $error = 'Ingresa tu nombre completo.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        // Verificar de nuevo que el token sigue válido (doble check)
        $s = $conn->prepare("SELECT id FROM invitaciones WHERE token=? AND usado=0 AND expires_at > NOW()");
        $s->bind_param("s", $token); $s->execute();
        $still_valid = $s->get_result()->fetch_assoc(); $s->close();

        if (!$still_valid) {
            $error = 'El enlace expiró mientras completabas el formulario. Solicita uno nuevo.';
        } else {
            // Verificar que el email no esté ya registrado
            $s = $conn->prepare("SELECT id FROM usuarios WHERE email=?");
            $s->bind_param("s", $inv['email']); $s->execute();
            $ya_existe = $s->get_result()->fetch_assoc(); $s->close();

            if ($ya_existe) {
                $error = 'Ya existe una cuenta con ese correo. Inicia sesión directamente.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Crear cuenta con rol admin
                $s = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,'admin')");
                $s->bind_param("sss", $nombre, $inv['email'], $hash);
                $s->execute(); $uid = $conn->insert_id; $s->close();

                // Marcar invitación como usada
                $s = $conn->prepare("UPDATE invitaciones SET usado=1 WHERE token=?");
                $s->bind_param("s", $token); $s->execute(); $s->close();

                // Iniciar sesión automáticamente
                $_SESSION['usuario_id'] = $uid;
                $_SESSION['nombre']     = $nombre;
                $_SESSION['rol']        = 'admin';

                $conn->close();
                redirigir('admin/index.php');
            }
        }
    }
}
if (isset($conn) && $conn) $conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Registro de profesor — Instituto Bíblico Bautista</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>body{display:flex;flex-direction:column;min-height:100vh;}</style>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="navbar-brand">
    <div class="brand-icon">✦</div>
    <div class="brand-name">Instituto Bíblico Bautista<span>RV 1865</span></div>
  </a>
  <ul class="navbar-links">
    <li><a href="login.php">← Iniciar sesión</a></li>
  </ul>
</nav>

<div class="auth-bg">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="icon">✦</div>
      <h2>Registro de profesor</h2>
      <?php if ($inv): ?>
        <p>Invitación para <strong style="color:var(--gold);"><?= sanitizar($inv['email']) ?></strong></p>
      <?php endif; ?>
    </div>

    <?php if (isset($error_fatal)): ?>
      <div class="alert alert-error"><?= $error_fatal ?></div>
      <div class="auth-footer">
        <a href="login.php">Volver al inicio de sesión</a>
      </div>

    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

      <!-- El email viene fijo de la invitación -->
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" class="form-control"
               value="<?= sanitizar($inv['email']) ?>" disabled
               style="opacity:.65;cursor:not-allowed;">
        <small style="font-size:.78rem;color:var(--text-muted);margin-top:.25rem;display:block;">
          Este correo fue registrado en la invitación y no se puede cambiar.
        </small>
      </div>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Nombre completo *</label>
          <input type="text" name="nombre" class="form-control"
                 placeholder="Tu nombre" required
                 value="<?= sanitizar($_POST['nombre'] ?? $inv['nombre'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Contraseña *</label>
          <input type="password" name="password" class="form-control"
                 placeholder="Mínimo 6 caracteres" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar contraseña *</label>
          <input type="password" name="password2" class="form-control"
                 placeholder="Repite la contraseña" required>
        </div>

        <!-- Info de acceso -->
        <div style="background:rgba(209,147,9,0.08);border:1px solid var(--border-gold);border-radius:var(--radius);padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.85rem;color:#7a5500;">
          🔑 Esta cuenta tendrá acceso completo al panel de administración.
        </div>

        <button type="submit" name="registrar" class="btn btn-primary btn-full">
          Crear mi cuenta de profesor
        </button>
      </form>

      <div class="verse">"Escudriñad las Escrituras" — Juan 5:39</div>
    <?php endif; ?>
  </div>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865</p>
</footer>
</body>
</html>

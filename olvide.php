<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (esta_logueado()) redirigir('dashboard.php');

$paso   = isset($_GET['token']) ? 'nueva' : 'solicitar';
$token  = sanitizar($_GET['token'] ?? '');
$exito  = $error = '';

// ── Paso 1: Solicitar reset ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar'])) {
    $email = sanitizar($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo válido.';
    } else {
        $conn = conectar();
        $s = $conn->prepare("SELECT id, nombre FROM usuarios WHERE email=? AND activo=1");
        $s->bind_param("s", $email); $s->execute();
        $u = $s->get_result()->fetch_assoc(); $s->close();

        // Siempre mostrar el mismo mensaje (seguridad)
        if ($u) {
            $tok = bin2hex(random_bytes(32));
            $exp = date('Y-m-d H:i:s', time() + 3600); // 1 hora

            // Eliminar tokens previos del mismo email
            $conn->query("DELETE FROM password_resets WHERE email='" . $conn->real_escape_string($email) . "'");

            $s = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)");
            $s->bind_param("sss", $email, $tok, $exp); $s->execute(); $s->close();

            $link    = "https://institutobiblicobautistacolombia.com/lms/olvide.php?token=" . $tok;
            $subject = '=?UTF-8?B?' . base64_encode('[IBB] Restablecer contraseña') . '?=';
            $body    = "Hola {$u['nombre']},\n\n"
                     . "Recibimos una solicitud para restablecer la contraseña de tu cuenta.\n\n"
                     . "Haz clic en el siguiente enlace (válido por 1 hora):\n{$link}\n\n"
                     . "Si no solicitaste esto, ignora este correo.\n\n"
                     . "—\nInstituto Bíblico Bautista";
            $from    = 'no-responder@institutobiblicobautistacolombia.com';
            $headers = "From: Instituto Bíblico Bautista <{$from}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            @mail($email, $subject, $body, $headers);
        }
        $conn->close();
        $exito = 'Si ese correo está registrado, recibirás las instrucciones en breve.';
    }
}

// ── Paso 2: Establecer nueva contraseña ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_password'])) {
    $pw1 = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';
    $tok = sanitizar($_POST['token'] ?? '');

    if (strlen($pw1) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($pw1 !== $pw2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $conn = conectar();
        $s = $conn->prepare("SELECT email FROM password_resets WHERE token=? AND expires_at > NOW() AND used=0");
        $s->bind_param("s", $tok); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();

        if (!$r) {
            $error = 'El enlace es inválido o ya expiró. Solicita uno nuevo.';
        } else {
            $hash = password_hash($pw1, PASSWORD_BCRYPT);
            $s = $conn->prepare("UPDATE usuarios SET password=? WHERE email=?");
            $s->bind_param("ss", $hash, $r['email']); $s->execute(); $s->close();
            $conn->query("UPDATE password_resets SET used=1 WHERE token='" . $conn->real_escape_string($tok) . "'");
            $conn->close();
            $exito = 'Contraseña actualizada. Ya puedes iniciar sesión.';
            $paso  = 'solicitar';
        }
    }
}

// Validar token si llega por GET
$token_valido = false;
if ($paso === 'nueva' && $token) {
    $conn = conectar();
    $s = $conn->prepare("SELECT id FROM password_resets WHERE token=? AND expires_at > NOW() AND used=0");
    $s->bind_param("s", $token); $s->execute();
    $token_valido = (bool)$s->get_result()->fetch_assoc(); $s->close();
    $conn->close();
    if (!$token_valido) $error = 'El enlace ha expirado o ya fue usado.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Recuperar contraseña — Instituto Bíblico Bautista</title>
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
      <h2>Recuperar contraseña</h2>
      <p><?= $paso==='nueva' ? 'Elige tu nueva contraseña' : 'Te enviaremos un enlace a tu correo' ?></p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>

    <?php if ($paso === 'solicitar' && !$exito): ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="email" class="form-control" placeholder="tu@correo.com" required
               value="<?= sanitizar($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" name="solicitar" class="btn btn-primary btn-full">Enviar enlace de recuperación</button>
    </form>
    <?php elseif ($paso === 'nueva' && $token_valido && !$exito): ?>
    <form method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="form-group">
        <label class="form-label">Nueva contraseña</label>
        <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
      </div>
      <div class="form-group">
        <label class="form-label">Confirmar contraseña</label>
        <input type="password" name="password2" class="form-control" placeholder="Repite la contraseña" required>
      </div>
      <button type="submit" name="nueva_password" class="btn btn-primary btn-full">Cambiar contraseña</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer" style="margin-top:1.5rem;">
      <a href="login.php">← Volver al inicio de sesión</a>
    </div>
    <div class="verse">"Escudriñad las Escrituras" — Juan 5:39</div>
  </div>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865</p>
</footer>
</body>
</html>

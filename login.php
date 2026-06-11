<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

if (esta_logueado()) {
    redirigir(es_admin() ? 'admin/index.php' : 'dashboard.php');
}

$modo  = $_GET['modo'] ?? 'login';
$error = $exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'login') {
        $email    = sanitizar($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email && $password) {
            $conn = conectar();
            $stmt = $conn->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE email=? AND activo=1");
            $stmt->bind_param("s", $email); $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();
            if ($usuario && password_verify($password, $usuario['password'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['rol']        = $usuario['rol'];
                $stmt->close(); $conn->close();
                redirigir($usuario['rol'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
            } else {
                $error = 'Correo o contraseña incorrectos.';
            }
            $stmt->close(); $conn->close();
        } else { $error = 'Por favor completa todos los campos.'; }
        $modo = 'login';
    }

    if ($_POST['accion'] === 'registro') {
        $nombre    = sanitizar($_POST['nombre'] ?? '');
        $apellido  = sanitizar($_POST['apellido'] ?? '');
        $email     = sanitizar($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        if (!$nombre || !$apellido || !$email || !$password || !$password2) {
            $error = 'Por favor completa todos los campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo electrónico válido.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($password !== $password2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $conn = conectar();
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email=?");
            $stmt->bind_param("s", $email); $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = 'Ya existe una cuenta con ese correo.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, email, password, rol) VALUES (?,?,?,?,'estudiante')");
                $stmt->bind_param("ssss", $nombre, $apellido, $email, $hash);
                if ($stmt->execute()) {
                    $exito = 'Cuenta creada. Ya puedes iniciar sesión.';
                    $modo  = 'login';
                } else { $error = 'Error al crear la cuenta. Inténtalo de nuevo.'; }
            }
            $stmt->close(); $conn->close();
        }
        if ($error) $modo = 'registro';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Acceso — Instituto Bíblico Bautista</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    body { display:flex; flex-direction:column; min-height:100vh; }
  </style>
</head>
<body>

<nav class="navbar">
  <a href="index.php" class="navbar-brand">
    <div class="brand-icon">✦</div>
    <div class="brand-name">Instituto Bíblico Bautista<span>RV 1865</span></div>
  </a>
  <ul class="navbar-links">
    <li><a href="index.php">← Inicio</a></li>
  </ul>
  <button class="nav-toggle" onclick="document.querySelector('.navbar-links').classList.toggle('open')" aria-label="Menú">
    <span></span><span></span><span></span>
  </button>
</nav>

<div class="auth-bg">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="icon">✦</div>
      <h2>Instituto Bíblico Bautista</h2>
      <p>RV 1865 — Formación Bíblica</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>

    <div class="tabs">
      <button class="tab-btn <?= $modo==='login'?'active':'' ?>" onclick="cambiarTab('login')">Iniciar sesión</button>
      <button class="tab-btn <?= $modo==='registro'?'active':'' ?>" onclick="cambiarTab('registro')">Registrarse</button>
    </div>

    <!-- Login -->
    <div id="tab-login" style="<?= $modo==='login'?'':'display:none' ?>">
      <form method="POST">
        <input type="hidden" name="accion" value="login">
        <div class="form-group">
          <label class="form-label">Correo electrónico</label>
          <input type="email" name="email" class="form-control"
                 placeholder="tu@correo.com" required
                 value="<?= sanitizar($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control"
                 placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full mt-2">Ingresar a la plataforma</button>
      </form>
      <div class="auth-footer">
        ¿Aún no tienes cuenta?
        <a href="#" onclick="cambiarTab('registro')">Regístrate aquí</a>
      </div>
      <div style="text-align:center; margin-top:0.75rem;">
        <a href="olvide.php" style="font-size:0.82rem; color:var(--text-muted);">¿Olvidaste tu contraseña?</a>
      </div>

      <!-- Google Sign-In -->
      <div style="margin-top:1.25rem; position:relative; text-align:center;">
        <div style="border-top:1px solid var(--border); margin-bottom:1rem;"></div>
        <span style="position:absolute; top:-10px; left:50%; transform:translateX(-50%); background:var(--bg-card); padding:0 0.75rem; font-size:0.78rem; color:var(--text-muted);">o continúa con</span>
      </div>
      <a href="api/google_auth.php" class="btn btn-outline btn-full" style="gap:0.6rem; justify-content:center;">
        <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        Continuar con Google
      </a>
    </div>

    <!-- Registro -->
    <div id="tab-registro" style="<?= $modo==='registro'?'':'display:none' ?>">
      <form method="POST">
        <input type="hidden" name="accion" value="registro">
        <div class="form-group" style="display:flex; gap:0.75rem;">
          <div style="flex:1;">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" placeholder="Tu nombre" required
                   value="<?= sanitizar($_POST['nombre'] ?? '') ?>">
          </div>
          <div style="flex:1;">
            <label class="form-label">Apellido</label>
            <input type="text" name="apellido" class="form-control" placeholder="Tu apellido" required
                   value="<?= sanitizar($_POST['apellido'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Correo electrónico</label>
          <input type="email" name="email" class="form-control" placeholder="tu@correo.com" required
                 value="<?= sanitizar($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar contraseña</label>
          <input type="password" name="password2" class="form-control" placeholder="Repite tu contraseña" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full mt-2">Crear mi cuenta</button>
      </form>
      <div class="auth-footer">
        ¿Ya tienes cuenta?
        <a href="#" onclick="cambiarTab('login')">Inicia sesión</a>
      </div>
    </div>

    <div class="verse">
      "Escudriñad las Escrituras" — Juan 5:39
    </div>
  </div>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865</p>
</footer>

<script>
function cambiarTab(tab) {
  document.getElementById('tab-login').style.display    = tab==='login'    ? '' : 'none';
  document.getElementById('tab-registro').style.display = tab==='registro' ? '' : 'none';
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    b.classList.toggle('active', (i===0&&tab==='login')||(i===1&&tab==='registro'));
  });
}
</script>
</body>
</html>

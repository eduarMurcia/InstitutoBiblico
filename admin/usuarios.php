<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_admin();
require_once __DIR__ . '/../includes/admin_sidebar.php';

$conn  = conectar();
$exito = $error = '';

// Activar / desactivar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_activo'])) {
    $uid_t = (int)$_POST['usuario_id'];
    $conn->query("UPDATE usuarios SET activo = NOT activo WHERE id=$uid_t AND rol='estudiante'");
    $exito = 'Estado del usuario actualizado.';
}

// Cambiar rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_rol'])) {
    $uid_t = (int)$_POST['usuario_id'];
    $conn->query("UPDATE usuarios SET rol = IF(rol='admin','estudiante','admin') WHERE id=$uid_t");
    $exito = 'Rol actualizado.';
}

// Eliminar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario'])) {
    $uid_t = (int)$_POST['usuario_id'];
    if ($uid_t !== $_SESSION['usuario_id']) {
        $conn->query("DELETE FROM usuarios WHERE id=$uid_t");
        $exito = 'Usuario eliminado.';
    } else {
        $error = 'No puedes eliminarte a ti mismo.';
    }
}

// Búsqueda
$buscar = sanitizar($_GET['q'] ?? '');
$like   = "%$buscar%";
$stmt = $conn->prepare("SELECT u.*, COUNT(DISTINCT p.leccion_id) AS lecciones_comp FROM usuarios u LEFT JOIN progreso p ON p.usuario_id=u.id AND p.completado=1 WHERE u.nombre LIKE ? OR u.email LIKE ? GROUP BY u.id ORDER BY u.created_at DESC");
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Usuarios — Admin RV 1865</title>
  <link rel="stylesheet" href="../css/styles.css">
</head>
<body>

<?php admin_navbar("usuarios"); ?>

<div class="main-layout">
  <?php admin_sidebar("usuarios"); ?>

  <main class="main-content">
    <h2 style="margin:0 0 0.25rem;">Gestión de Usuarios</h2>
    <hr class="divider-gold left" style="margin-bottom:1.5rem;">

    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <!-- Búsqueda -->
    <form method="GET" style="margin-bottom:1.5rem; display:flex; gap:0.75rem;">
      <input type="text" name="q" class="form-control" style="max-width:320px;"
             placeholder="Buscar por nombre o correo..."
             value="<?= sanitizar($buscar) ?>">
      <button type="submit" class="btn btn-outline btn-sm">Buscar</button>
      <?php if ($buscar): ?>
        <a href="usuarios.php" class="btn btn-ghost btn-sm">✕ Limpiar</a>
      <?php endif; ?>
    </form>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Correo</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Lecciones</th>
              <th>Registro</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($usuarios as $u): ?>
          <tr>
            <td><strong style="color:var(--text);"><?= sanitizar($u['nombre']) ?></strong></td>
            <td style="font-size:0.82rem;"><?= sanitizar($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['rol']==='admin' ? 'badge-gold' : 'badge-teal' ?>">
                <?= $u['rol'] === 'admin' ? icono('config','ico').' Admin' : '✦ Estudiante' ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $u['activo'] ? 'badge-success' : 'badge-gray' ?>">
                <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>
            <td><?= $u['lecciones_comp'] ?></td>
            <td style="font-size:0.8rem;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <?php if ($u['id'] !== $_SESSION['usuario_id']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                  <button type="submit" name="toggle_activo" class="btn btn-ghost btn-sm" title="Activar/Desactivar">
                    <?= $u['activo'] ? '⊘' : '✓' ?>
                  </button>
                </form>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('¿Eliminar este usuario y todo su progreso?')">
                  <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                  <button type="submit" name="eliminar_usuario" class="btn btn-danger btn-sm">✕</button>
                </form>
                <?php else: ?>
                <span style="font-size:0.78rem; color:var(--gray-mid);">Tú</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($usuarios)): ?>
            <tr><td colspan="7" style="text-align:center; color:var(--gray-mid);">No se encontraron usuarios.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <p style="font-size:0.8rem; color:var(--gray-mid); margin:0.75rem 0 0;">
        Total: <?= count($usuarios) ?> usuario(s)
      </p>
    </div>
  </main>
</div>

<footer>
  <p style="margin:0; color:var(--gray-mid); font-size:0.85rem;">
    &copy; <?= date('Y') ?> Instituto Bíblico Bautista
  </p>
</footer>
</body>
</html>

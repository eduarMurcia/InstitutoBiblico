<?php
require_once 'includes/auth.php';
require_once 'includes/estudiante_layout.php';
require_once 'config/db.php';
requerir_login();

$conn = conectar();
$uid  = $_SESSION['usuario_id'];

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id=?");
$stmt->bind_param("i", $uid); $stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc(); $stmt->close();

$exito = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $nombre   = sanitizar($_POST['nombre'] ?? '');
    $apellido = sanitizar($_POST['apellido'] ?? '');
    if ($nombre && $apellido) {
        $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=? WHERE id=?");
        $stmt->bind_param("ssi", $nombre, $apellido, $uid);
        $stmt->execute(); $stmt->close();
        $_SESSION['nombre']   = $nombre;
        $usuario['nombre']    = $nombre;
        $usuario['apellido']  = $apellido;
        $exito = 'Perfil actualizado correctamente.';
    }
    // Toggle notificaciones por email
    $notif_email = isset($_POST['notif_email']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE usuarios SET notif_email=? WHERE id=?");
    $stmt->bind_param("ii", $notif_email, $uid);
    $stmt->execute(); $stmt->close();
    $usuario['notif_email'] = $notif_email;

    if (!empty($_POST['password'])) {
        $pw1 = $_POST['password'];
        $pw2 = $_POST['password2'] ?? '';
        if (strlen($pw1) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } elseif ($pw1 !== $pw2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $hash = password_hash($pw1, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE usuarios SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $uid);
            $stmt->execute(); $stmt->close();
            if (!$error) $exito = 'Contraseña actualizada.';
        }
    }
}

// ── Progreso por curso ──
$stmt = $conn->prepare("
    SELECT c.id, c.titulo,
           COUNT(DISTINCT l.id) AS total_l,
           COUNT(DISTINCT CASE WHEN p.completado=1 THEN p.leccion_id END) AS comp_l
    FROM cursos c
    LEFT JOIN modulos m ON m.curso_id=c.id
    LEFT JOIN lecciones l ON l.modulo_id=m.id
    LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=?
    WHERE c.publicado=1 GROUP BY c.id
");
$stmt->bind_param("i",$uid); $stmt->execute();
$mis_cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// ── Certificados emitidos (cursos al 100%) ──
$stmt = $conn->prepare("
    SELECT ce.id AS cert_id, ce.numero, ce.fecha AS cert_fecha,
           c.id AS curso_id, c.titulo AS curso
    FROM certificados ce
    JOIN cursos c ON c.id = ce.curso_id
    WHERE ce.usuario_id = ?
    ORDER BY ce.fecha DESC
");
$stmt->bind_param("i",$uid); $stmt->execute();
$certificados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// Cursos completados al 100% que aún no tienen certificado
$stmt = $conn->prepare("
    SELECT c.id, c.titulo,
           COUNT(DISTINCT l.id) AS total_l,
           COUNT(DISTINCT CASE WHEN p.completado=1 THEN p.leccion_id END) AS comp_l
    FROM cursos c
    LEFT JOIN modulos m ON m.curso_id=c.id
    LEFT JOIN lecciones l ON l.modulo_id=m.id
    LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=?
    WHERE c.publicado=1
      AND NOT EXISTS (SELECT 1 FROM certificados WHERE usuario_id=? AND curso_id=c.id)
    GROUP BY c.id
    HAVING total_l > 0 AND comp_l = total_l
");
$stmt->bind_param("ii",$uid,$uid); $stmt->execute();
$cursos_listos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// ── Histórico académico por curso ──
require_once 'includes/historico.php';
$historico = historico_por_curso($conn, $uid);
$res_hist  = historico_resumen($historico);

// ── Resumen estadístico ──
$total_examenes   = $res_hist['examenes_presentados'];
$total_aprobados  = $res_hist['examenes_aprobados'];
$puntaje_promedio = $res_hist['promedio'];
$cursos_completados = $res_hist['cursos_completados'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Mi Perfil — Instituto Bíblico Bautista</title>
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
  <style>
    .cert-card {
      background: var(--bg-card);
      border: 2px solid var(--gold);
      border-radius: var(--radius-lg);
      padding: 1.75rem;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    .cert-card::before {
      content: '✦';
      position: absolute;
      top: -12px; left: -12px;
      font-size: 5rem;
      color: rgba(209,147,9,0.06);
      font-family: var(--font-title);
      line-height: 1;
    }
    .cert-card h4 { color: var(--navy); margin-bottom: 0.2rem; font-size: 1.05rem; }
    .cert-card .cert-meta { font-size:0.8rem; color:var(--text-muted); margin-bottom:0.75rem; }
    .cert-ready {
      background: var(--bg-card);
      border: 2px dashed var(--gold);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .cert-ready h4 { color: var(--navy); margin: 0 0 0.2rem; font-size: 1rem; }
    .cert-ready p  { margin: 0; font-size: 0.85rem; color: var(--text-muted); }
    .nota-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.5rem;
      padding: 0.85rem 0;
      border-bottom: 1px solid var(--border);
      font-size: 0.9rem;
    }
    .nota-row:last-child { border-bottom: none; }
    .nota-curso { font-size: 0.75rem; color: var(--text-muted); }
    .nota-score {
      font-family: var(--font-title);
      font-size: 1.3rem;
      color: var(--gold);
      line-height: 1;
      min-width: 3rem;
      text-align: right;
    }
    .avatar {
      width: 64px; height: 64px;
      background: var(--navy);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-title);
      font-size: 1.5rem;
      color: var(--gold);
      margin-bottom: 0.75rem;
    }
    .member-since { font-family: var(--font-title); font-style: italic; color: var(--gold); font-size: 1rem; }
  </style>
</head>
<body>

<?php estudiante_navbar("perfil"); ?><div class="main-layout">
  <?php estudiante_sidebar("perfil"); ?>

  <main class="main-content">
    <span style="font-family:var(--font-ui); font-size:0.72rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold);">Mi cuenta</span>
    <h2 style="margin:0.4rem 0 0.25rem;">Perfil de estudiante</h2>
    <hr class="divider-gold left" style="margin-bottom:2rem;">

    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <div class="grid-2 mb-4">

      <!-- Datos personales -->
      <div class="card card-gold-top">
        <h3 class="mb-3">Datos personales</h3>
        <form method="POST">
          <div class="form-group" style="display:flex; gap:0.75rem;">
            <div style="flex:1;">
              <label class="form-label">Nombre</label>
              <input type="text" name="nombre" class="form-control"
                     value="<?= sanitizar($usuario['nombre']) ?>" required>
            </div>
            <div style="flex:1;">
              <label class="form-label">Apellido</label>
              <input type="text" name="apellido" class="form-control"
                     value="<?= sanitizar($usuario['apellido'] ?? '') ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Correo electrónico</label>
            <input type="email" class="form-control"
                   value="<?= sanitizar($usuario['email']) ?>" disabled
                   style="opacity:0.6; cursor:not-allowed;">
          </div>
          <hr style="border-color:var(--border); margin:1.25rem 0;">
          <h4 style="font-size:0.95rem; margin-bottom:1rem; color:var(--text-soft);">Cambiar contraseña</h4>
          <div class="form-group">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="password" class="form-control" placeholder="Dejar vacío para no cambiar">
          </div>
          <div class="form-group">
            <label class="form-label">Confirmar contraseña</label>
            <input type="password" name="password2" class="form-control" placeholder="Repite la contraseña">
          </div>
          <hr style="border-color:var(--border); margin:1.25rem 0;">
          <div class="form-group">
            <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer;">
              <input type="checkbox" name="notif_email"
                     style="width:18px; height:18px; accent-color:var(--gold); flex-shrink:0;"
                     <?= !empty($usuario['notif_email']) ? 'checked' : '' ?>>
              <span>
                <strong style="font-size:0.9rem; color:var(--navy);">Notificaciones por correo</strong><br>
                <span style="font-size:0.8rem; color:var(--text-muted);">
                  Recibir un email cuando el pastor califique tus exámenes o responda tus comentarios.
                </span>
              </span>
            </label>
          </div>
          <button type="submit" name="actualizar" class="btn btn-primary">Guardar cambios</button>
        </form>
      </div>
      <div>
        <div class="card mb-3" style="text-align:center;">
          <p style="font-family:var(--font-ui); font-size:0.72rem; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.35rem;">
            Miembro desde
          </p>
          <div class="member-since">
            <?= date('d \d\e F \d\e Y', strtotime($usuario['created_at'])) ?>
          </div>
        </div>

        <div class="card">
          <h3 class="mb-3">Mi progreso</h3>
          <?php if (empty($mis_cursos)): ?>
            <p style="color:var(--text-muted); margin:0; font-size:0.9rem;">Aún no hay cursos disponibles.</p>
          <?php else: foreach ($mis_cursos as $c):
            $pct = $c['total_l'] > 0 ? round(($c['comp_l'] / $c['total_l']) * 100) : 0;
          ?>
          <div style="margin-bottom:1rem;">
            <div class="d-flex justify-between align-center mb-1">
              <a href="cursos.php?id=<?= $c['id'] ?>" style="font-size:0.9rem; color:var(--navy); font-weight:600;">
                <?= sanitizar($c['titulo']) ?>
              </a>
              <span class="badge <?= $pct>=100?'badge-success':'badge-gold' ?>"><?= $pct ?>%</span>
            </div>
            <div class="progress-wrap">
              <div class="progress-bar" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Certificados disponibles para descargar ── -->
    <?php if (!empty($cursos_listos)): ?>
    <div class="card mb-4" style="border-color:var(--gold);">
      <h3 class="mb-1" style="color:var(--navy);">🎉 ¡Tienes certificados nuevos disponibles!</h3>
      <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:1.25rem;">
        Completaste estos cursos. Descarga tu certificado oficial.
      </p>
      <div style="display:flex; flex-direction:column; gap:0.85rem;">
        <?php foreach ($cursos_listos as $cl): ?>
        <div class="cert-ready">
          <div>
            <h4><?= sanitizar($cl['titulo']) ?></h4>
            <p>✓ <?= $cl['comp_l'] ?> de <?= $cl['total_l'] ?> lecciones completadas</p>
          </div>
          <a href="api/certificado.php?curso_id=<?= $cl['id'] ?>"
             class="btn btn-primary btn-sm" target="_blank">
            ⬇ Descargar certificado
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Certificados ya emitidos ── -->
    <h3 class="mb-2">🏆 Mis certificados</h3>
    <?php if (empty($certificados) && empty($cursos_listos)): ?>
      <div class="card text-center mb-4">
        <p style="margin:0; color:var(--text-muted);">
          Completa todas las lecciones de un curso para obtener tu certificado. ¡Sigue estudiando!
        </p>
      </div>
    <?php elseif (!empty($certificados)): ?>
    <div class="grid-2 mb-4">
      <?php foreach ($certificados as $cert): ?>
      <div class="cert-card">
        <p style="font-family:var(--font-ui); font-size:0.68rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--text-muted); margin-bottom:0.5rem;">
          Certificado de curso
        </p>
        <h4><?= sanitizar($cert['curso']) ?></h4>
        <p class="cert-meta">
          Emitido el <?= date('d/m/Y', strtotime($cert['cert_fecha'])) ?>
          &nbsp;·&nbsp; Nº <?= sanitizar($cert['numero']) ?>
        </p>
        <a href="api/certificado.php?curso_id=<?= $cert['curso_id'] ?>"
           class="btn btn-outline btn-sm" target="_blank">
          ⬇ Descargar PDF
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Histórico académico por curso ── -->
    <?php if (!empty($historico)): ?>
    <div class="card" style="margin-bottom:0;">
      <div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap; gap:1rem;">
        <h3 style="margin:0;">📋 Historial académico</h3>
        <a href="api/historial_pdf.php" target="_blank" class="btn btn-outline btn-sm btn-lift">
          ⬇ Descargar constancia PDF
        </a>
      </div>

      <!-- Stats rápidas -->
      <div class="grid-3 mb-4" style="gap:0.75rem;">
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:1rem; text-align:center;">
          <div style="font-family:var(--font-title); font-size:1.8rem; color:var(--gold); line-height:1;"><?= $cursos_completados ?></div>
          <div style="font-family:var(--font-ui); font-size:0.7rem; color:var(--text-muted); margin-top:0.2rem; text-transform:uppercase; letter-spacing:0.08em;">Cursos completados</div>
        </div>
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:1rem; text-align:center;">
          <div style="font-family:var(--font-title); font-size:1.8rem; color:var(--gold); line-height:1;"><?= $total_aprobados ?>/<?= $total_examenes ?></div>
          <div style="font-family:var(--font-ui); font-size:0.7rem; color:var(--text-muted); margin-top:0.2rem; text-transform:uppercase; letter-spacing:0.08em;">Exámenes aprobados</div>
        </div>
        <div style="background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:1rem; text-align:center;">
          <div style="font-family:var(--font-title); font-size:1.8rem; color:var(--gold); line-height:1;"><?= $puntaje_promedio ?>%</div>
          <div style="font-family:var(--font-ui); font-size:0.7rem; color:var(--text-muted); margin-top:0.2rem; text-transform:uppercase; letter-spacing:0.08em;">Promedio general</div>
        </div>
      </div>

      <!-- Histórico por curso -->
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Curso</th>
              <th style="text-align:center;">Lecciones</th>
              <th style="text-align:center;">Exámenes</th>
              <th style="text-align:center;">Nota</th>
              <th style="text-align:center;">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historico as $h): ?>
            <tr style="background:var(--azul-pale);">
              <td style="font-weight:700; color:var(--navy);"><?= sanitizar($h['curso']) ?></td>
              <td style="text-align:center; font-size:0.88rem;"><?= $h['lecciones_comp'] ?>/<?= $h['total_lecciones'] ?></td>
              <td style="text-align:center; font-size:0.88rem;"><?= $h['presentados'] ?>/<?= $h['total_examenes'] ?></td>
              <td style="text-align:center;">
                <?php if ($h['nota'] !== null): ?>
                  <span style="font-family:var(--font-title); font-weight:700; font-size:1.15rem; color:<?= $h['estado']==='Reprobado' ? '#c0392b' : 'var(--navy)' ?>;"><?= $h['nota'] ?>%</span>
                <?php else: ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?php if ($h['estado']==='Completado'): ?>
                  <span class="badge badge-success">✓ Completado</span>
                <?php elseif ($h['estado']==='Reprobado'): ?>
                  <span class="badge" style="background:#fdecea; color:#922b21; border-color:rgba(192,57,43,0.3);">✗ Reprobado</span>
                <?php else: ?>
                  <span class="badge badge-teal">En curso</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php foreach ($h['examenes'] as $e): if ((int)$e['intentos']===0) continue; ?>
            <tr>
              <td style="padding-left:1.8rem; font-size:0.85rem; color:var(--text-soft);">↳ <?= sanitizar($e['examen']) ?></td>
              <td style="text-align:center; font-size:0.78rem; color:var(--text-muted);"><?= $e['ultima_fecha'] ? date('d/m/Y', strtotime($e['ultima_fecha'])) : '' ?></td>
              <td style="text-align:center; font-size:0.78rem; color:var(--text-muted);"><?= $e['intentos'] ?> intento<?= $e['intentos']>1?'s':'' ?></td>
              <td style="text-align:center;">
                <?php if ((int)$e['pendientes'] > 0 && !$e['aprobado']): ?>
                  <span style="color:var(--text-muted); font-size:0.85rem;">—</span>
                <?php else: ?>
                  <span style="font-size:0.92rem; color:<?= $e['aprobado'] ? 'var(--azul-deep)' : '#c0392b' ?>;"><?= $e['mejor'] !== null ? round($e['mejor'],1).'%' : '—' ?></span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <?php if ((int)$e['pendientes'] > 0 && !$e['aprobado']): ?>
                  <span class="badge badge-gold">⏳ Pendiente</span>
                <?php elseif ($e['aprobado']): ?>
                  <span class="badge badge-success">✓ Aprobado</span>
                <?php else: ?>
                  <span class="badge" style="background:#fdecea; color:#922b21; border-color:rgba(192,57,43,0.3);">✗ No aprobado</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p style="font-size:0.75rem; color:var(--text-muted); margin:0.75rem 0 0;">La nota de cada examen corresponde al mejor intento calificado; la del curso, al promedio de sus exámenes presentados.</p>
    </div>
    <?php endif; ?>

  </main>
</div>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">&copy; <?= date('Y') ?> RV 1865 — Formación bíblica en línea</p>
</footer>
</body>
</html>

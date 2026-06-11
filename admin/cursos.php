<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_admin();
require_once __DIR__ . '/../includes/admin_sidebar.php';

$conn   = conectar();
$accion = $_GET['accion'] ?? 'listar';
$cid    = isset($_GET['curso_id'])   ? (int)$_GET['curso_id']   : 0;
$mid    = isset($_GET['modulo_id'])  ? (int)$_GET['modulo_id']  : 0;
$exito  = $error = '';

define('PDF_PATH',     __DIR__ . '/../uploads/pdf/');
define('PORTADA_PATH', __DIR__ . '/../uploads/portadas/');

// ── PROCESAR FORMULARIOS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Crear / editar curso
    if (isset($_POST['guardar_curso'])) {
        $titulo         = sanitizar($_POST['titulo'] ?? '');
        $desc           = sanitizar($_POST['descripcion'] ?? '');
        $orden          = (int)($_POST['orden'] ?? 0);
        $pub            = isset($_POST['publicado']) ? 1 : 0;
        $instructor     = sanitizar($_POST['instructor'] ?? '');
        $color_portada  = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color_portada'] ?? '') ? $_POST['color_portada'] : '#1a2744';
        $icono_portada  = sanitizar($_POST['icono_portada'] ?? '📖');
        $id_edit        = (int)($_POST['curso_id_edit'] ?? 0);

        // Manejar imagen de portada
        $imagen_portada = '';
        if (isset($_FILES['imagen_portada']) && $_FILES['imagen_portada']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir(PORTADA_PATH)) mkdir(PORTADA_PATH, 0755, true);
            $ext   = strtolower(pathinfo($_FILES['imagen_portada']['name'], PATHINFO_EXTENSION));
            $allow = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allow) && $_FILES['imagen_portada']['size'] <= 5 * 1024 * 1024) {
                $nombre_img = 'portada_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['imagen_portada']['tmp_name'], PORTADA_PATH . $nombre_img)) {
                    $imagen_portada = $nombre_img;
                    // Borrar imagen anterior si existe
                    if ($id_edit) {
                        $r = $conn->query("SELECT imagen FROM cursos WHERE id=$id_edit");
                        $old = $r->fetch_assoc()['imagen'] ?? '';
                        if ($old && file_exists(PORTADA_PATH . $old)) @unlink(PORTADA_PATH . $old);
                    }
                }
            } else { $error = 'La imagen debe ser JPG, PNG o WebP y pesar menos de 5MB.'; }
        }

        if ($titulo && !$error) {
            if ($id_edit) {
                if ($imagen_portada) {
                    $s = $conn->prepare("UPDATE cursos SET titulo=?,descripcion=?,orden=?,publicado=?,instructor=?,color_portada=?,icono_portada=?,imagen=? WHERE id=?");
                    $s->bind_param("ssisssssi", $titulo, $desc, $orden, $pub, $instructor, $color_portada, $icono_portada, $imagen_portada, $id_edit);
                } else {
                    $s = $conn->prepare("UPDATE cursos SET titulo=?,descripcion=?,orden=?,publicado=?,instructor=?,color_portada=?,icono_portada=? WHERE id=?");
                    $s->bind_param("ssissssi", $titulo, $desc, $orden, $pub, $instructor, $color_portada, $icono_portada, $id_edit);
                }
            } else {
                if ($imagen_portada) {
                    $s = $conn->prepare("INSERT INTO cursos (titulo,descripcion,orden,publicado,instructor,color_portada,icono_portada,imagen) VALUES (?,?,?,?,?,?,?,?)");
                    $s->bind_param("ssisssss", $titulo, $desc, $orden, $pub, $instructor, $color_portada, $icono_portada, $imagen_portada);
                } else {
                    $s = $conn->prepare("INSERT INTO cursos (titulo,descripcion,orden,publicado,instructor,color_portada,icono_portada) VALUES (?,?,?,?,?,?,?)");
                    $s->bind_param("ssissss", $titulo, $desc, $orden, $pub, $instructor, $color_portada, $icono_portada);
                }
            }
            $s->execute();
            $nuevo_curso_id = $id_edit ? 0 : $conn->insert_id;
            $s->close();
            // Todo curso nuevo nace con un módulo inicial (necesario para lecciones y exámenes)
            if ($nuevo_curso_id) {
                $s = $conn->prepare("INSERT INTO modulos (curso_id, titulo, orden) VALUES (?, 'Módulo 1', 1)");
                $s->bind_param("i", $nuevo_curso_id);
                $s->execute(); $s->close();
            }
            $exito = $id_edit ? 'Curso actualizado.' : 'Curso creado.';
        } elseif (!$titulo) { $error = 'El título es obligatorio.'; }
        $accion = 'listar';
    }

    if (isset($_POST['eliminar_curso'])) {
        $id_del = (int)$_POST['curso_id_del'];
        $conn->query("DELETE FROM cursos WHERE id=$id_del");
        $exito = 'Curso eliminado.'; $accion = 'listar';
    }

    if (isset($_POST['toggle_publicado'])) {
        $cid_t = (int)$_POST['curso_id_toggle'];
        $conn->query("UPDATE cursos SET publicado = NOT publicado WHERE id=$cid_t");
        $accion = 'listar';
    }

    // Crear módulo
    if (isset($_POST['guardar_modulo'])) {
        $titulo = sanitizar($_POST['titulo'] ?? '');
        $desc   = sanitizar($_POST['descripcion'] ?? '');
        $orden  = (int)($_POST['orden'] ?? 0);
        $cid_m  = (int)($_POST['curso_id_mod'] ?? 0);
        if ($titulo && $cid_m) {
            $s = $conn->prepare("INSERT INTO modulos (curso_id,titulo,descripcion,orden) VALUES (?,?,?,?)");
            $s->bind_param("issi", $cid_m, $titulo, $desc, $orden);
            $s->execute(); $s->close();
            $exito = 'Módulo creado.';
        }
        $cid = $cid_m; $accion = 'modulos';
    }

    if (isset($_POST['eliminar_modulo'])) {
        $conn->query("DELETE FROM modulos WHERE id=" . (int)$_POST['modulo_id_del']);
        $exito = 'Módulo eliminado.'; $accion = 'modulos';
    }

    if (isset($_POST['editar_modulo'])) {
        $mid_e   = (int)($_POST['modulo_id_edit'] ?? 0);
        $cid_e   = (int)($_POST['curso_id_mod'] ?? 0);
        $tit_e   = sanitizar($_POST['titulo'] ?? '');
        $desc_e  = sanitizar($_POST['descripcion'] ?? '');
        $orden_e = (int)($_POST['orden'] ?? 0);
        if ($mid_e && $tit_e) {
            $s = $conn->prepare("UPDATE modulos SET titulo=?, descripcion=?, orden=? WHERE id=?");
            $s->bind_param("ssii", $tit_e, $desc_e, $orden_e, $mid_e);
            $s->execute(); $s->close();
            $exito = 'Módulo actualizado.';
        } elseif (!$tit_e) { $error = 'El título del módulo es obligatorio.'; }
        $cid = $cid_e; $accion = 'modulos';
    }

    // Crear lección (audio + PDF)
    if (isset($_POST['guardar_leccion'])) {
        $titulo   = sanitizar($_POST['titulo'] ?? '');
        $desc     = sanitizar($_POST['descripcion'] ?? '');
        $orden    = (int)($_POST['orden'] ?? 0);
        $mid_l    = (int)($_POST['modulo_id_lec'] ?? 0);
        $duracion = sanitizar($_POST['duracion'] ?? '');
        $archivo_audio = $archivo_pdf = '';

        // ── Subir audio ──
        if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
            $ext   = strtolower(pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION));
            $allow = ['mp3','m4a','wav','ogg','aac'];
            if (!in_array($ext, $allow)) {
                $error = 'Formato de audio no permitido (MP3, M4A, WAV, OGG, AAC).';
            } elseif ($_FILES['audio']['size'] > MAX_FILE_SIZE) {
                $error = 'Audio supera 150MB.';
            } else {
                $nombre = uniqid('audio_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['audio']['tmp_name'], UPLOAD_PATH . $nombre)) {
                    $archivo_audio = $nombre;
                } else { $error = 'No se pudo guardar el audio. Verifica permisos de uploads/audio/'; }
            }
        }

        // ── Subir PDF ──
        if (!$error && isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['pdf']['size'] > 50 * 1024 * 1024) {
                $error = 'El PDF supera el límite de 50MB.';
            } elseif (strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
                $error = 'Solo se permiten archivos PDF.';
            } else {
                $nombre_pdf = uniqid('pdf_', true) . '.pdf';
                if (!is_dir(PDF_PATH)) mkdir(PDF_PATH, 0755, true);
                if (move_uploaded_file($_FILES['pdf']['tmp_name'], PDF_PATH . $nombre_pdf)) {
                    $archivo_pdf = $nombre_pdf;
                } else { $error = 'No se pudo guardar el PDF. Verifica permisos de uploads/pdf/'; }
            }
        }

        if (!$error && $titulo && $mid_l) {
            $s = $conn->prepare("INSERT INTO lecciones (modulo_id,titulo,descripcion,archivo_audio,archivo_pdf,duracion,orden) VALUES (?,?,?,?,?,?,?)");
            $s->bind_param("isssssi", $mid_l, $titulo, $desc, $archivo_audio, $archivo_pdf, $duracion, $orden);
            $s->execute(); $s->close();
            if (!$error) $exito = 'Lección creada.';
        }
        $mid = $mid_l; $accion = 'lecciones';
    }

    // Eliminar lección
    if (isset($_POST['eliminar_leccion'])) {
        $lid_del = (int)$_POST['leccion_id_del'];
        $r = $conn->query("SELECT archivo_audio, archivo_pdf FROM lecciones WHERE id=$lid_del");
        $la = $r->fetch_assoc();
        if ($la) {
            if ($la['archivo_audio']) @unlink(UPLOAD_PATH . $la['archivo_audio']);
            if ($la['archivo_pdf'])   @unlink(PDF_PATH . $la['archivo_pdf']);
        }
        $conn->query("DELETE FROM lecciones WHERE id=$lid_del");
        $exito = 'Lección eliminada.'; $accion = 'lecciones';
    }

    // Editar PDF de lección existente
    if (isset($_POST['actualizar_pdf'])) {
        $lid_upd = (int)$_POST['leccion_id_upd'];
        if (isset($_FILES['pdf_nuevo']) && $_FILES['pdf_nuevo']['error'] === UPLOAD_ERR_OK) {
            if (strtolower(pathinfo($_FILES['pdf_nuevo']['name'], PATHINFO_EXTENSION)) === 'pdf') {
                // Borrar el viejo
                $r = $conn->query("SELECT archivo_pdf FROM lecciones WHERE id=$lid_upd");
                $old = $r->fetch_assoc();
                if ($old && $old['archivo_pdf']) @unlink(PDF_PATH . $old['archivo_pdf']);
                // Guardar el nuevo
                $nombre_pdf = uniqid('pdf_', true) . '.pdf';
                if (!is_dir(PDF_PATH)) mkdir(PDF_PATH, 0755, true);
                if (move_uploaded_file($_FILES['pdf_nuevo']['tmp_name'], PDF_PATH . $nombre_pdf)) {
                    $s = $conn->prepare("UPDATE lecciones SET archivo_pdf=? WHERE id=?");
                    $s->bind_param("si", $nombre_pdf, $lid_upd); $s->execute(); $s->close();
                    $exito = 'PDF actualizado.';
                } else { $error = 'No se pudo guardar el PDF.'; }
            }
        } elseif (isset($_POST['quitar_pdf'])) {
            $r = $conn->query("SELECT archivo_pdf FROM lecciones WHERE id=$lid_upd");
            $old = $r->fetch_assoc();
            if ($old && $old['archivo_pdf']) @unlink(PDF_PATH . $old['archivo_pdf']);
            $conn->query("UPDATE lecciones SET archivo_pdf=NULL WHERE id=$lid_upd");
            $exito = 'PDF eliminado.';
        }
        $accion = 'lecciones';
    }
}

// ── Cargar datos ──
$cursos = $conn->query("SELECT * FROM cursos ORDER BY orden, id")->fetch_all(MYSQLI_ASSOC);
$modulos = $lecciones = $curso_actual = $modulo_actual = null;

if ($accion === 'modulos' && $cid) {
    $s = $conn->prepare("SELECT * FROM cursos WHERE id=?"); $s->bind_param("i",$cid); $s->execute();
    $curso_actual = $s->get_result()->fetch_assoc(); $s->close();
    $s = $conn->prepare("SELECT mo.*, COUNT(l.id) AS num_lecciones FROM modulos mo LEFT JOIN lecciones l ON l.modulo_id=mo.id WHERE mo.curso_id=? GROUP BY mo.id ORDER BY mo.orden");
    $s->bind_param("i",$cid); $s->execute();
    $modulos = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
}
if ($accion === 'lecciones' && $mid) {
    $s = $conn->prepare("SELECT mo.*, c.titulo AS curso_titulo, c.id AS curso_id FROM modulos mo JOIN cursos c ON c.id=mo.curso_id WHERE mo.id=?");
    $s->bind_param("i",$mid); $s->execute();
    $modulo_actual = $s->get_result()->fetch_assoc(); $s->close();
    $s = $conn->prepare("SELECT * FROM lecciones WHERE modulo_id=? ORDER BY orden");
    $s->bind_param("i",$mid); $s->execute();
    $lecciones = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
}
$conn->close();

$iconos_disponibles = [
    ['val'=>'libro',     'ti'=>'ti-book',            'label'=>'Libro'],
    ['val'=>'cruz',      'ti'=>'ti-cross',           'label'=>'Cruz'],
    ['val'=>'paloma',    'ti'=>'ti-feather',         'label'=>'Paloma'],
    ['val'=>'pergamino', 'ti'=>'ti-file-text',       'label'=>'Pergamino'],
    ['val'=>'oracion',   'ti'=>'ti-heart',           'label'=>'Oración'],
    ['val'=>'iglesia',   'ti'=>'ti-building-church', 'label'=>'Iglesia'],
    ['val'=>'hoja',      'ti'=>'ti-leaf',            'label'=>'Hoja'],
    ['val'=>'llama',     'ti'=>'ti-flame',           'label'=>'Llama'],
    ['val'=>'mapa',      'ti'=>'ti-map',             'label'=>'Mapa'],
    ['val'=>'estrella',  'ti'=>'ti-star',            'label'=>'Estrella'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Gestionar Cursos — Admin RV 1865</title>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
  <style>
    .upload-area { border:2px dashed var(--border); border-radius:var(--radius); padding:1.25rem; text-align:center; cursor:pointer; transition:border-color 0.2s; }
    .upload-area:hover { border-color:var(--gold); }
    .upload-area input[type=file] { display:none; }
    .breadcrumb { font-size:0.82rem; color:var(--gray-mid); margin-bottom:1.5rem; }
    .breadcrumb a { color:var(--gold); }
    .pdf-badge { display:inline-flex; align-items:center; gap:0.4rem; background:rgba(10,138,163,0.15); color:var(--teal); border:1px solid rgba(10,138,163,0.3); border-radius:var(--radius); padding:0.2rem 0.6rem; font-size:0.78rem; }
  </style>
</head>
<body>
<?php admin_navbar("cursos"); ?>

<div class="main-layout">
  <?php admin_sidebar("cursos"); ?>

  <main class="main-content">
    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <?php if ($accion === 'listar' || $accion === 'nuevo'): ?>
    <!-- ══ LISTA CURSOS ══ -->
    <div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:1rem;">
      <h2 style="margin:0;">Gestionar Cursos</h2>
      <button onclick="toggleForm()" class="btn btn-primary">+ Nuevo Curso</button>
    </div>
    <hr class="divider-gold left" style="margin-bottom:2rem;">
    <div id="form-curso" class="card mb-4" style="<?= $accion==='nuevo'?'':'display:none' ?>">
      <h3 class="mb-3">Crear nuevo curso</h3>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="curso_id_edit" value="0">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input type="text" name="titulo" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Orden</label>
            <input type="number" name="orden" class="form-control" value="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Instructor / Pastor</label>
          <input type="text" name="instructor" class="form-control" placeholder="Ej: Pastor Juan Pérez">
        </div>

        <!-- Portada visual -->
        <div style="border:1px solid var(--border); border-radius:var(--radius-lg); padding:1.25rem; margin-bottom:1rem; background:var(--bg);">
          <p style="font-family:var(--font-ui); font-size:0.72rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--gold); margin-bottom:1rem;">Portada del curso</p>
          <div class="grid-2" style="gap:1.25rem; align-items:start;">
            <!-- Preview -->
            <div>
              <div id="portada-preview" style="
                height:160px; border-radius:var(--radius-lg);
                background:#1a2744; display:flex; flex-direction:column;
                align-items:center; justify-content:center; gap:0.5rem;
                position:relative; overflow:hidden; transition:background 0.3s;
              ">
                <div id="portada-img-wrap" style="display:none; position:absolute; inset:0;">
                  <img id="portada-img" src="" style="width:100%; height:100%; object-fit:cover; border-radius:var(--radius-lg);">
                  <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(0,5,20,0.75) 0%, transparent 60%); border-radius:var(--radius-lg);"></div>
                </div>
                <div id="portada-icono-wrap" style="font-size:2.5rem; z-index:1;">
                  <i class="ti ti-book" style="font-size:2.5rem;color:var(--gold);"></i>
                </div>
                <div id="portada-titulo-wrap" style="z-index:1; color:#d19309; font-size:0.85rem; font-weight:700; text-align:center; padding:0 0.5rem;">Vista previa</div>
              </div>
              <p style="font-size:0.75rem; color:var(--gray-mid); margin-top:0.5rem; text-align:center;">Vista previa en tiempo real</p>
            </div>
            <!-- Controles -->
            <div>
              <div class="form-group">
                <label class="form-label">Imagen de portada (opcional)</label>
                <div class="upload-area" onclick="document.getElementById('img-portada-input').click()" style="padding:0.85rem;">
                  <p style="margin:0; font-size:0.82rem; color:var(--gray-mid);" id="img-portada-lbl">📷 Subir imagen (JPG, PNG, WebP — máx. 5MB)</p>
                  <input type="file" id="img-portada-input" name="imagen_portada" accept=".jpg,.jpeg,.png,.webp"
                         onchange="previewPortada(this)">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Color de fondo (si no hay imagen)</label>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                  <input type="color" name="color_portada" id="color-portada" value="#1a2744"
                         style="width:44px; height:36px; border:1px solid var(--border); border-radius:var(--radius); cursor:pointer; background:none; padding:2px;"
                         oninput="actualizarColor(this.value)">
                  <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                    <?php foreach (['#1a2744','#1a3a2a','#3a1a2a','#2a1a3a','#1a2a3a','#3a2a1a'] as $col): ?>
                    <div onclick="setColor('<?= $col ?>')" style="width:24px;height:24px;background:<?= $col ?>;border-radius:50%;cursor:pointer;border:2px solid transparent;" class="color-swatch"></div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Ícono</label>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;" id="iconos-crear">
                  <?php foreach ($iconos_disponibles as $ic): ?>
                  <button type="button" onclick="setIcono('<?= $ic['val'] ?>')"
                          title="<?= $ic['label'] ?>"
                          style="width:42px;height:42px;background:var(--navy);border:2px solid rgba(209,147,9,0.3);border-radius:var(--radius);cursor:pointer;transition:border-color 0.15s;display:flex;align-items:center;justify-content:center;"
                          class="icono-btn" data-val="<?= $ic['val'] ?>">
                    <i class="ti <?= $ic['ti'] ?>" style="font-size:20px;color:var(--gold);"></i>
                  </button>
                  <?php endforeach; ?>
                </div>
                <input type="hidden" name="icono_portada" id="icono-portada" value="libro">
              </div>
            </div>
          </div>
        </div>

        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-bottom:1rem;">
          <input type="checkbox" name="publicado" style="accent-color:var(--gold);">
          <span style="font-size:0.85rem;color:var(--gray-soft);">Publicar inmediatamente</span>
        </label>
        <button type="submit" name="guardar_curso" class="btn btn-primary">Crear</button>
        <button type="button" onclick="toggleForm()" class="btn btn-ghost" style="margin-left:0.5rem;">Cancelar</button>
      </form>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Título</th><th>Estado</th><th>Orden</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php if (empty($cursos)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--gray-mid);">Sin cursos aún.</td></tr>
          <?php else: foreach ($cursos as $c): ?>
          <tr>
            <td><strong style="color:var(--navy);"><?= sanitizar($c['titulo']) ?></strong></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="curso_id_toggle" value="<?= $c['id'] ?>">
                <button type="submit" name="toggle_publicado"
                        class="badge <?= $c['publicado']?'badge-success':'badge-gray' ?>"
                        style="border:none;cursor:pointer;">
                  <?= $c['publicado']?'✓ Publicado':'○ Borrador' ?>
                </button>
              </form>
            </td>
            <td><?= $c['orden'] ?></td>
            <td>
              <div class="d-flex gap-1" style="flex-wrap:wrap;">
                <a href="cursos.php?accion=modulos&curso_id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">📖 Módulos</a>
                <a href="examenes.php?curso_id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">✎ Exámenes</a>
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="abrirEditar(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['titulo'])) ?>, <?= htmlspecialchars(json_encode($c['descripcion'] ?? '')) ?>, <?= htmlspecialchars(json_encode($c['instructor'] ?? '')) ?>, <?= (int)$c['orden'] ?>, <?= (int)$c['publicado'] ?>, <?= htmlspecialchars(json_encode($c['color_portada'] ?? '#1a2744')) ?>, <?= htmlspecialchars(json_encode($c['icono_portada'] ?? '📖')) ?>)">
                  ✎ Editar
                </button>
                <form method="POST" onsubmit="return confirm('¿Eliminar este curso?')">
                  <input type="hidden" name="curso_id_del" value="<?= $c['id'] ?>">
                  <button type="submit" name="eliminar_curso" class="btn btn-danger btn-sm">✕</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($accion === 'modulos' && $curso_actual): ?>
    <!-- ══ MÓDULOS ══ -->
    <div class="breadcrumb"><a href="cursos.php">Cursos</a> › <?= sanitizar($curso_actual['titulo']) ?></div>
    <h2 style="margin:0 0 0.25rem;">Módulos — <?= sanitizar($curso_actual['titulo']) ?></h2>
    <hr class="divider-gold left" style="margin-bottom:2rem;">
    <div class="card mb-4">
      <h3 class="mb-3">Agregar módulo</h3>
      <form method="POST">
        <input type="hidden" name="curso_id_mod" value="<?= $cid ?>">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input type="text" name="titulo" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Orden</label>
            <input type="number" name="orden" class="form-control" value="<?= count($modulos??[]) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Descripción</label>
          <textarea name="descripcion" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" name="guardar_modulo" class="btn btn-primary">Agregar módulo</button>
      </form>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Módulo</th><th>Lecciones</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php if (empty($modulos)): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--gray-mid);">Sin módulos aún.</td></tr>
          <?php else: foreach ($modulos as $m): ?>
          <tr>
            <td><strong style="color:var(--navy);"><?= sanitizar($m['titulo']) ?></strong>
              <?php if (!empty($m['descripcion'])): ?>
                <div style="font-size:0.78rem; color:var(--text-muted);"><?= sanitizar($m['descripcion']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-gold"><?= $m['num_lecciones'] ?></span></td>
            <td>
              <div class="d-flex gap-1">
                <a href="cursos.php?accion=lecciones&modulo_id=<?= $m['id'] ?>&curso_id=<?= $cid ?>" class="btn btn-outline btn-sm">🎧 Lecciones</a>
                <button type="button" class="btn btn-ghost btn-sm btn-hover-azul"
                        onclick="var f=document.getElementById('edit-mod-<?= $m['id'] ?>'); f.style.display = f.style.display==='none' ? 'table-row' : 'none';">✎ Editar</button>
                <form method="POST" onsubmit="return confirm('¿Eliminar módulo?')">
                  <input type="hidden" name="modulo_id_del" value="<?= $m['id'] ?>">
                  <button type="submit" name="eliminar_modulo" class="btn btn-danger btn-sm">✕</button>
                </form>
              </div>
            </td>
          </tr>
          <tr id="edit-mod-<?= $m['id'] ?>" style="display:none; background:var(--azul-pale);">
            <td colspan="3" style="padding:1rem 1.25rem;">
              <form method="POST">
                <input type="hidden" name="modulo_id_edit" value="<?= $m['id'] ?>">
                <input type="hidden" name="curso_id_mod" value="<?= $cid ?>">
                <div class="grid-2">
                  <div class="form-group">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control" required value="<?= sanitizar($m['titulo']) ?>">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" class="form-control" value="<?= (int)$m['orden'] ?>">
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Descripción</label>
                  <textarea name="descripcion" class="form-control" rows="2"><?= sanitizar($m['descripcion'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="editar_modulo" class="btn btn-primary btn-sm">Guardar cambios</button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <a href="cursos.php" class="btn btn-ghost btn-sm mt-3">← Volver a cursos</a>

    <?php elseif ($accion === 'lecciones' && $modulo_actual): ?>
    <!-- ══ LECCIONES ══ -->
    <div class="breadcrumb">
      <a href="cursos.php">Cursos</a> ›
      <a href="cursos.php?accion=modulos&curso_id=<?= $modulo_actual['curso_id'] ?>"><?= sanitizar($modulo_actual['curso_titulo']) ?></a> ›
      <?= sanitizar($modulo_actual['titulo']) ?>
    </div>
    <h2 style="margin:0 0 0.25rem;">Lecciones — <?= sanitizar($modulo_actual['titulo']) ?></h2>
    <hr class="divider-gold left" style="margin-bottom:2rem;">

    <!-- Formulario nueva lección -->
    <div class="card mb-4">
      <h3 class="mb-3">Agregar lección</h3>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="modulo_id_lec" value="<?= $mid ?>">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input type="text" name="titulo" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Duración (ej: 45 min)</label>
            <input type="text" name="duracion" class="form-control" placeholder="45 min">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Orden</label>
            <input type="number" name="orden" class="form-control" value="<?= count($lecciones??[]) ?>">
          </div>
        </div>
        <div class="grid-2">
          <!-- Audio -->
          <div class="form-group">
            <label class="form-label">Audio (MP3, M4A, WAV — máx 150MB)</label>
            <div class="upload-area" onclick="document.getElementById('inp-audio').click()">
              <span style="font-size:1.5rem;">🎧</span>
              <p style="margin:0.4rem 0 0;color:var(--gray-mid);font-size:0.85rem;" id="lbl-audio">Seleccionar audio</p>
              <input type="file" id="inp-audio" name="audio" accept="audio/*"
                     onchange="document.getElementById('lbl-audio').textContent=this.files[0]?.name||'Seleccionar audio'">
            </div>
          </div>
          <!-- PDF -->
          <div class="form-group">
            <label class="form-label">Material PDF (opcional — máx 50MB)</label>
            <div class="upload-area" onclick="document.getElementById('inp-pdf').click()">
              <span style="font-size:1.5rem;">📄</span>
              <p style="margin:0.4rem 0 0;color:var(--gray-mid);font-size:0.85rem;" id="lbl-pdf">Seleccionar PDF</p>
              <input type="file" id="inp-pdf" name="pdf" accept="application/pdf"
                     onchange="document.getElementById('lbl-pdf').textContent=this.files[0]?.name||'Seleccionar PDF'">
            </div>
          </div>
        </div>
        <button type="submit" name="guardar_leccion" class="btn btn-primary">Guardar lección</button>
      </form>
    </div>

    <!-- Lista lecciones -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Lección</th><th>Audio</th><th>PDF</th><th>Duración</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php if (empty($lecciones)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--gray-mid);">Sin lecciones aún.</td></tr>
          <?php else: foreach ($lecciones as $l): ?>
          <tr>
            <td><strong style="color:var(--navy);"><?= sanitizar($l['titulo']) ?></strong></td>
            <td><?= $l['archivo_audio'] ? '<span class="badge badge-success">🎧 Cargado</span>' : '<span class="badge badge-gray">—</span>' ?></td>
            <td>
              <?php if ($l['archivo_pdf']): ?>
                <span class="pdf-badge">📄 PDF</span>
                <form method="POST" enctype="multipart/form-data" style="display:inline; margin-left:0.4rem;">
                  <input type="hidden" name="leccion_id_upd" value="<?= $l['id'] ?>">
                  <input type="hidden" name="quitar_pdf" value="1">
                  <button type="submit" name="actualizar_pdf" class="btn btn-danger btn-sm"
                          onclick="return confirm('¿Quitar el PDF?')" style="padding:0.2rem 0.5rem;">✕</button>
                </form>
              <?php else: ?>
                <form method="POST" enctype="multipart/form-data" style="display:inline;">
                  <input type="hidden" name="leccion_id_upd" value="<?= $l['id'] ?>">
                  <input type="file" name="pdf_nuevo" accept="application/pdf" style="font-size:0.75rem; max-width:140px;">
                  <button type="submit" name="actualizar_pdf" class="btn btn-outline btn-sm">+ PDF</button>
                </form>
              <?php endif; ?>
            </td>
            <td><?= sanitizar($l['duracion']?:'—') ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('¿Eliminar esta lección?')">
                <input type="hidden" name="leccion_id_del" value="<?= $l['id'] ?>">
                <button type="submit" name="eliminar_leccion" class="btn btn-danger btn-sm">✕ Eliminar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <a href="cursos.php?accion=modulos&curso_id=<?= $modulo_actual['curso_id'] ?>" class="btn btn-ghost btn-sm mt-3">← Volver a módulos</a>
    <?php endif; ?>
  </main>
</div>

<!-- Modal editar curso -->
<div id="modal-editar" style="display:none;position:fixed;inset:0;background:rgba(0,10,35,0.55);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);border-radius:var(--radius-lg);padding:2rem;width:100%;max-width:560px;margin:1rem;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto;">
    <div class="d-flex justify-between align-center mb-3">
      <h3 style="margin:0;">Editar curso</h3>
      <button onclick="cerrarEditar()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="guardar_curso" value="1">
      <input type="hidden" name="curso_id_edit" id="edit-id" value="0">
      <div class="form-group">
        <label class="form-label">Título *</label>
        <input type="text" name="titulo" id="edit-titulo" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" id="edit-desc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Instructor / Pastor</label>
        <input type="text" name="instructor" id="edit-instructor" class="form-control" placeholder="Ej: Pastor Juan Pérez">
      </div>
      <div class="grid-2" style="gap:1rem;">
        <div class="form-group">
          <label class="form-label">Orden</label>
          <input type="number" name="orden" id="edit-orden" class="form-control" value="0" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Color de portada</label>
          <input type="color" name="color_portada" id="edit-color" value="#1a2744"
                 style="width:100%;height:42px;border:1.5px solid var(--border);border-radius:var(--radius);cursor:pointer;padding:2px;">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Imagen de portada (dejar vacío para no cambiar)</label>
        <div class="upload-area" onclick="document.getElementById('edit-img-input').click()">
          <p style="margin:0;font-size:0.82rem;color:var(--gray-mid);" id="edit-img-lbl">📷 Subir nueva imagen (opcional)</p>
          <input type="file" id="edit-img-input" name="imagen_portada" accept=".jpg,.jpeg,.png,.webp"
                 onchange="document.getElementById('edit-img-lbl').textContent='✓ '+this.files[0].name">
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;margin-bottom:1.25rem;">
        <input type="checkbox" name="publicado" id="edit-publicado" style="accent-color:var(--gold);width:18px;height:18px;">
        <span style="font-size:0.88rem;color:var(--text-soft);">Publicado</span>
      </label>
      <div class="form-group">
        <label class="form-label">Ícono</label>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;" id="iconos-editar">
          <?php foreach ($iconos_disponibles as $ic): ?>
          <button type="button" onclick="setIconoEdit('<?= $ic['val'] ?>')"
                  title="<?= $ic['label'] ?>"
                  style="width:42px;height:42px;background:var(--navy);border:2px solid rgba(209,147,9,0.3);border-radius:var(--radius);cursor:pointer;transition:border-color 0.15s;display:flex;align-items:center;justify-content:center;"
                  class="icono-btn-edit" data-val="<?= $ic['val'] ?>">
            <i class="ti <?= $ic['ti'] ?>" style="font-size:20px;color:var(--gold);"></i>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <input type="hidden" name="icono_portada" id="edit-icono" value="libro">
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
        <button type="button" onclick="cerrarEditar()" class="btn btn-ghost">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<footer><p style="margin:0;color:var(--gray-mid);font-size:0.85rem;">&copy; <?= date('Y') ?> Instituto Bíblico Bautista</p></footer>

<script>
function toggleForm() {
  const f = document.getElementById('form-curso');
  f.style.display = f.style.display === 'none' ? '' : 'none';
}

function abrirEditar(id, titulo, desc, instructor, orden, publicado, color, icono) {
  document.getElementById('edit-id').value        = id;
  document.getElementById('edit-titulo').value    = titulo;
  document.getElementById('edit-desc').value      = desc;
  document.getElementById('edit-instructor').value= instructor;
  document.getElementById('edit-orden').value     = orden;
  document.getElementById('edit-publicado').checked = publicado == 1;
  document.getElementById('edit-color').value     = color;
  document.getElementById('edit-icono').value     = icono;
  document.querySelectorAll('.icono-btn-edit').forEach(b => {
    b.style.borderColor = b.dataset.val === icono ? '#d19309' : 'rgba(209,147,9,0.3)';
  });
  document.getElementById('edit-img-lbl').textContent = '📷 Subir nueva imagen (opcional)';
  const m = document.getElementById('modal-editar');
  m.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function cerrarEditar() {
  document.getElementById('modal-editar').style.display = 'none';
  document.body.style.overflow = '';
}

// Cerrar modal al hacer click fuera
document.getElementById('modal-editar').addEventListener('click', function(e) {
  if (e.target === this) cerrarEditar();
});

// ── Portada preview ──
function previewPortada(input) {
  if (!input.files || !input.files[0]) return;
  document.getElementById('img-portada-lbl').textContent = '✓ ' + input.files[0].name;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('portada-img').src = e.target.result;
    document.getElementById('portada-img-wrap').style.display = 'block';
    document.getElementById('portada-icono-wrap').style.display = 'none';
    document.getElementById('portada-preview').style.background = 'transparent';
  };
  reader.readAsDataURL(input.files[0]);
}

function actualizarColor(val) {
  const p = document.getElementById('portada-preview');
  if (document.getElementById('portada-img-wrap').style.display === 'none') {
    p.style.background = val;
  }
  document.querySelectorAll('.color-swatch').forEach(s => {
    s.style.borderColor = s.style.background === val ? '#d19309' : 'transparent';
  });
}

function setColor(val) {
  document.getElementById('color-portada').value = val;
  actualizarColor(val);
}

function setIcono(val) {
  document.getElementById('icono-portada').value = val;
  // Actualizar preview con el icono seleccionado
  const btn = document.querySelector('#iconos-crear [data-val="'+val+'"]');
  if (btn) {
    const iTag = btn.querySelector('i');
    document.getElementById('portada-icono-wrap').innerHTML =
      '<i class="ti '+iTag.className.replace('ti ','').trim()+'" style="font-size:2.5rem;color:var(--gold);"></i>';
  }
  document.querySelectorAll('.icono-btn').forEach(b => {
    b.style.borderColor = b.dataset.val === val ? '#d19309' : 'rgba(209,147,9,0.3)';
  });
}

function setIconoEdit(val) {
  document.getElementById('edit-icono').value = val;
  document.querySelectorAll('.icono-btn-edit').forEach(b => {
    b.style.borderColor = b.dataset.val === val ? '#d19309' : 'rgba(209,147,9,0.3)';
  });
}

// Sincronizar título en preview
document.addEventListener('DOMContentLoaded', () => {
  const t = document.querySelector('[name="titulo"]');
  if (t) t.addEventListener('input', () => {
    document.getElementById('portada-titulo-wrap').textContent = t.value || 'Vista previa';
  });
});
</script>
</body>
</html>

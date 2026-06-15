<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requerir_admin();
require_once __DIR__ . '/../includes/admin_sidebar.php';

$conn   = conectar();
$exito  = $error = '';
$eid    = isset($_GET['examen_id']) ? (int)$_GET['examen_id'] : 0;
$accion = $_GET['accion'] ?? 'listar';

// ── Crear examen ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_examen'])) {
    $titulo    = sanitizar($_POST['titulo'] ?? '');
    $desc      = sanitizar($_POST['descripcion'] ?? '');
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $puntaje      = (int)($_POST['puntaje_minimo'] ?? 60);
    $max_intentos = (int)($_POST['max_intentos'] ?? 0);
    if ($titulo && $modulo_id) {
        $s = $conn->prepare("INSERT INTO examenes (modulo_id,titulo,descripcion,puntaje_minimo,max_intentos) VALUES (?,?,?,?,?)");
        $s->bind_param("issii", $modulo_id, $titulo, $desc, $puntaje, $max_intentos);
        $s->execute(); $s->close();
        $exito = 'Examen creado.'; $accion = 'listar';
    } else { $error = 'Completa título y módulo.'; }
}

// ── Eliminar examen ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_examen'])) {
    $conn->query("DELETE FROM examenes WHERE id=" . (int)$_POST['examen_id_del']);
    $exito = 'Examen eliminado.'; $accion = 'listar';
}

// ── Agregar pregunta (cualquier tipo) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_pregunta'])) {
    $eid_p = (int)($_POST['examen_id_preg'] ?? 0);
    $tipo  = sanitizar($_POST['tipo'] ?? 'multiple');
    $preg  = sanitizar($_POST['pregunta'] ?? '');
    $orden = (int)($_POST['orden'] ?? 0);

    // Según el tipo, construir opciones y respuesta
    switch ($tipo) {
        case 'multiple':
            $a   = sanitizar($_POST['opcion_a'] ?? '');
            $b   = sanitizar($_POST['opcion_b'] ?? '');
            $c   = sanitizar($_POST['opcion_c'] ?? '');
            $d   = sanitizar($_POST['opcion_d'] ?? '');
            $cor = sanitizar($_POST['correcta'] ?? 'a');
            if (!$preg || !$a || !$b || !$c || !$d) { $error = 'Completa la pregunta y las 4 opciones.'; break; }
            break;
        case 'verdadero_falso':
            $a   = 'Verdadero';
            $b   = 'Falso';
            $c   = '';  $d = '';
            $cor = sanitizar($_POST['correcta_vf'] ?? 'a');
            if (!$preg) { $error = 'Escribe la pregunta.'; break; }
            break;
        case 'abierta':
        case 'archivo':
            $a = $b = $c = $d = $cor = '';
            if (!$preg) { $error = 'Escribe la pregunta.'; break; }
            break;
        default:
            $error = 'Tipo de pregunta no válido.';
    }

    if (!$error && $preg && $eid_p) {
        $s = $conn->prepare("INSERT INTO preguntas (examen_id,pregunta,opcion_a,opcion_b,opcion_c,opcion_d,respuesta_correcta,tipo,orden) VALUES (?,?,?,?,?,?,?,?,?)");
        $s->bind_param("isssssssi", $eid_p, $preg, $a, $b, $c, $d, $cor, $tipo, $orden);
        $s->execute(); $s->close();
        $exito = 'Pregunta agregada.';
    }
    $eid = $eid_p; $accion = 'preguntas';
}

// ── Eliminar pregunta ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_pregunta'])) {
    $conn->query("DELETE FROM preguntas WHERE id=" . (int)$_POST['pregunta_id_del']);
    $eid = (int)$_POST['examen_id_preg']; $exito = 'Pregunta eliminada.'; $accion = 'preguntas';
}

// ── Editar pregunta ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_pregunta'])) {
    $pid  = (int)$_POST['pregunta_id_edit'];
    $preg = sanitizar($_POST['pregunta_edit'] ?? '');
    $a    = sanitizar($_POST['opcion_a_edit'] ?? '');
    $b    = sanitizar($_POST['opcion_b_edit'] ?? '');
    $c    = sanitizar($_POST['opcion_c_edit'] ?? '');
    $d    = sanitizar($_POST['opcion_d_edit'] ?? '');
    $cor  = sanitizar($_POST['respuesta_correcta_edit'] ?? '');
    if ($preg && $pid) {
        $s = $conn->prepare("UPDATE preguntas SET pregunta=?,opcion_a=?,opcion_b=?,opcion_c=?,opcion_d=?,respuesta_correcta=? WHERE id=?");
        $s->bind_param("ssssssi", $preg, $a, $b, $c, $d, $cor, $pid);
        $s->execute(); $s->close();
        $exito = 'Pregunta actualizada.';
    }
    $eid = (int)$_POST['examen_id_preg']; $accion = 'preguntas';
}

// ── Cargar datos ──
$todos_examenes = $conn->query("
    SELECT e.*, mo.titulo AS modulo, c.titulo AS curso, c.id AS curso_id,
           COUNT(p.id) AS num_preguntas
    FROM examenes e
    JOIN modulos mo ON mo.id=e.modulo_id
    JOIN cursos c ON c.id=mo.curso_id
    LEFT JOIN preguntas p ON p.examen_id=e.id
    GROUP BY e.id ORDER BY c.titulo, mo.titulo
")->fetch_all(MYSQLI_ASSOC);

$modulos_all = $conn->query("
    SELECT mo.id, mo.titulo, c.titulo AS curso FROM modulos mo
    JOIN cursos c ON c.id=mo.curso_id ORDER BY c.titulo, mo.titulo
")->fetch_all(MYSQLI_ASSOC);

$examen_actual = $preguntas = null;
if ($accion === 'preguntas' && $eid) {
    $s = $conn->prepare("SELECT e.*, mo.titulo AS modulo FROM examenes e JOIN modulos mo ON mo.id=e.modulo_id WHERE e.id=?");
    $s->bind_param("i",$eid); $s->execute();
    $examen_actual = $s->get_result()->fetch_assoc(); $s->close();
    $s = $conn->prepare("SELECT * FROM preguntas WHERE examen_id=? ORDER BY orden");
    $s->bind_param("i",$eid); $s->execute();
    $preguntas = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
}
$conn->close();

// Íconos y etiquetas por tipo
$tipo_info = [
    'multiple'       => ['icon'=>icono('lista'),       'label'=>'Selección múltiple', 'badge'=>'badge-gold'],
    'verdadero_falso'=> ['icon'=>icono('intercambio'), 'label'=>'Verdadero / Falso',  'badge'=>'badge-teal'],
    'abierta'        => ['icon'=>icono('editar'),      'label'=>'Pregunta abierta',   'badge'=>'badge-gray'],
    'archivo'        => ['icon'=>icono('adjunto'),     'label'=>'Entrega de archivo', 'badge'=>'badge-gray'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title>Exámenes — Admin RV 1865</title>
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .tipo-tabs { display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.5rem; }
    .tipo-tab  { padding:0.45rem 1rem; border-radius:var(--radius); border:1px solid var(--border); background:transparent; color:var(--gray-soft); font-family:var(--font-ui); font-size:0.78rem; cursor:pointer; transition:all 0.2s; }
    .tipo-tab.active, .tipo-tab:hover { border-color:var(--gold); color:var(--gold); background:rgba(209,147,9,0.08); }
    .campos-tipo { display:none; }
    .campos-tipo.visible { display:block; }
    .opcion-row { display:grid; grid-template-columns:36px 1fr 36px 1fr; gap:0.6rem; align-items:center; margin-bottom:0.6rem; }
    .letra-tag  { font-family:var(--font-title); font-size:1.1rem; color:var(--gold); text-align:center; }
    .pregunta-card { background:var(--navy-light); border:1px solid rgba(209,147,9,0.2); border-radius:var(--radius-lg); padding:1.25rem; margin-bottom:1rem; }
    .pregunta-card > p,
    .pregunta-card .preg-text { color: #f5f0e8; }
    .pregunta-card .badge.badge-gold { background:#d19309; color:#000a23 !important; }
    .pregunta-card .badge.badge-teal { background:var(--azul-pale); color:var(--azul-deep) !important; }
    .pregunta-card .badge.badge-gray { background:rgba(245,240,232,0.15); color:#f5f0e8 !important; border-color:rgba(245,240,232,0.2); }
    .opciones-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.4rem; margin-top:0.75rem; }
    .op-chip { font-size:0.85rem; padding:0.35rem 0.75rem; border-radius:var(--radius); background:rgba(255,255,255,0.08); color:#d4cfc7; border:1px solid rgba(255,255,255,0.1); }
    .op-chip.ok { background:rgba(40,167,69,0.2); color:#6fcf97; border:1px solid rgba(40,167,69,0.4); }
  </style>
</head>
<body>
<?php admin_navbar("examenes"); ?>

<div class="main-layout">
  <?php admin_sidebar("examenes"); ?>

  <main class="main-content">
    <?php if ($exito): ?><div class="alert alert-success"><?= $exito ?></div><?php endif; ?>
    <?php if ($error):  ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <?php if ($accion === 'preguntas' && $examen_actual): ?>
    <!-- ══ PREGUNTAS ══ -->
    <div style="font-size:0.82rem;color:var(--gray-mid);margin-bottom:1.5rem;">
      <a href="examenes.php" style="color:var(--gold);">Exámenes</a> › <?= sanitizar($examen_actual['titulo']) ?>
    </div>
    <h2 style="margin:0 0 0.25rem;"><?= sanitizar($examen_actual['titulo']) ?></h2>
    <p style="color:var(--gray-mid);font-size:0.85rem;margin-bottom:1.5rem;">
      Módulo: <?= sanitizar($examen_actual['modulo']) ?> &nbsp;·&nbsp;
      Aprobación: <strong style="color:var(--gold);"><?= $examen_actual['puntaje_minimo'] ?>%</strong>
    </p>

    <!-- Formulario agregar pregunta -->
    <div class="card mb-4">
      <h3 class="mb-3">Agregar pregunta</h3>
      <form method="POST" id="form-pregunta">
        <input type="hidden" name="examen_id_preg" value="<?= $eid ?>">
        <input type="hidden" name="tipo" id="input-tipo" value="multiple">

        <!-- Selector de tipo -->
        <p class="form-label" style="margin-bottom:0.5rem;">Tipo de pregunta</p>
        <div class="tipo-tabs">
          <button type="button" class="tipo-tab active" onclick="setTipo('multiple')"><?= icono('lista','ico') ?> Selección múltiple</button>
          <button type="button" class="tipo-tab" onclick="setTipo('verdadero_falso')"><?= icono('intercambio','ico') ?> Verdadero / Falso</button>
          <button type="button" class="tipo-tab" onclick="setTipo('abierta')"><?= icono('editar','ico') ?> Pregunta abierta</button>
        </div>

        <div class="form-group">
          <label class="form-label">Enunciado de la pregunta *</label>
          <textarea name="pregunta" class="form-control" rows="2"
                    placeholder="Escribe la pregunta..." required></textarea>
        </div>

        <!-- MÚLTIPLE -->
        <div class="campos-tipo visible" id="campos-multiple">
          <p class="form-label">Opciones de respuesta</p>
          <div class="opcion-row">
            <span class="letra-tag">A</span>
            <input type="text" name="opcion_a" class="form-control" placeholder="Opción A">
            <span class="letra-tag">B</span>
            <input type="text" name="opcion_b" class="form-control" placeholder="Opción B">
          </div>
          <div class="opcion-row">
            <span class="letra-tag">C</span>
            <input type="text" name="opcion_c" class="form-control" placeholder="Opción C">
            <span class="letra-tag">D</span>
            <input type="text" name="opcion_d" class="form-control" placeholder="Opción D">
          </div>
          <div class="form-group mt-2">
            <label class="form-label">Respuesta correcta</label>
            <select name="correcta" class="form-control" style="max-width:180px;">
              <option value="a">A</option><option value="b">B</option>
              <option value="c">C</option><option value="d">D</option>
            </select>
          </div>
        </div>

        <!-- VERDADERO / FALSO -->
        <div class="campos-tipo" id="campos-verdadero_falso">
          <div class="form-group">
            <label class="form-label">Respuesta correcta</label>
            <select name="correcta_vf" class="form-control" style="max-width:200px;">
              <option value="a">Verdadero</option>
              <option value="b">Falso</option>
            </select>
          </div>
        </div>

        <!-- ABIERTA -->
        <div class="campos-tipo" id="campos-abierta">
          <div class="alert alert-info">
            El estudiante escribirá su respuesta en un campo de texto.
            El pastor revisará y asignará la calificación manualmente desde <strong>Entregas</strong>.
          </div>
        </div>

        <!-- ARCHIVO -->
        <div class="campos-tipo" id="campos-archivo">
          <div class="alert alert-info">
            El estudiante subirá un PDF, imagen o documento como respuesta.
            El pastor descargará el archivo y asignará la calificación desde <strong>Entregas</strong>.
          </div>
        </div>

        <div class="form-group mt-2">
          <label class="form-label">Orden</label>
          <input type="number" name="orden" class="form-control"
                 style="max-width:120px;" value="<?= count($preguntas??[]) ?>">
        </div>
        <button type="submit" name="agregar_pregunta" class="btn btn-primary">+ Agregar pregunta</button>
      </form>
    </div>

    <!-- Lista preguntas -->
    <h3 class="mb-2">
      Preguntas del examen
      <span class="badge badge-gold" style="margin-left:0.5rem;"><?= count($preguntas??[]) ?></span>
    </h3>

    <?php if (empty($preguntas)): ?>
      <div class="card text-center" style="color:var(--gray-mid);"><p style="margin:0;">Sin preguntas aún.</p></div>
    <?php else: foreach ($preguntas as $i => $p):
        $ti = $tipo_info[$p['tipo']] ?? $tipo_info['multiple'];
    ?>
    <div class="pregunta-card">
      <div class="d-flex justify-between align-center mb-1">
        <span style="font-family:var(--font-ui);font-size:0.7rem;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;">
          Pregunta <?= $i+1 ?>
        </span>
        <span class="badge <?= $ti['badge'] ?>"><?= $ti['icon'] ?> <?= $ti['label'] ?></span>
      </div>
      <p style="color:#f5f0e8; margin-bottom:0.75rem; font-size:1rem;"><?= sanitizar($p['pregunta']) ?></p>

      <?php if ($p['tipo'] === 'multiple'): ?>
        <div class="opciones-grid">
          <?php foreach(['a'=>$p['opcion_a'],'b'=>$p['opcion_b'],'c'=>$p['opcion_c'],'d'=>$p['opcion_d']] as $l=>$t): ?>
          <div class="op-chip <?= $l===$p['respuesta_correcta']?'ok':'' ?>">
            <strong><?= strtoupper($l) ?>.</strong> <?= sanitizar($t) ?>
            <?= $l===$p['respuesta_correcta']?' ✓':'' ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php elseif ($p['tipo'] === 'verdadero_falso'): ?>
        <div class="opciones-grid">
          <div class="op-chip <?= $p['respuesta_correcta']==='a'?'ok':'' ?>">Verdadero <?= $p['respuesta_correcta']==='a'?' ✓':'' ?></div>
          <div class="op-chip <?= $p['respuesta_correcta']==='b'?'ok':'' ?>">Falso <?= $p['respuesta_correcta']==='b'?' ✓':'' ?></div>
        </div>
      <?php elseif ($p['tipo'] === 'abierta'): ?>
        <p style="font-size:0.85rem;color:#a89f8c;margin:0;"><?= icono('editar','ico') ?> El estudiante escribe su respuesta — calificación manual</p>
      <?php elseif ($p['tipo'] === 'archivo'): ?>
        <p style="font-size:0.85rem;color:#a89f8c;margin:0;"><?= icono('adjunto','ico') ?> El estudiante sube un archivo — calificación manual</p>
      <?php endif; ?>

      <div style="margin-top:0.85rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-start;">
        <button type="button" class="btn btn-ghost btn-sm"
                onclick="this.closest('.pregunta-card').querySelector('.edit-form').style.display='block';this.style.display='none';">
          <?= icono('editar','ico') ?> Editar
        </button>
        <form method="POST" onsubmit="return confirm('¿Eliminar pregunta?')" style="display:inline;">
          <input type="hidden" name="pregunta_id_del" value="<?= $p['id'] ?>">
          <input type="hidden" name="examen_id_preg"  value="<?= $eid ?>">
          <button type="submit" name="eliminar_pregunta" class="btn btn-danger btn-sm">✕ Eliminar</button>
        </form>
      </div>

      <!-- Formulario de edición inline -->
      <div class="edit-form" style="display:none; margin-top:1rem; background:var(--bg); border:1px solid var(--border-gold); border-radius:var(--radius); padding:1rem;">
        <form method="POST">
          <input type="hidden" name="pregunta_id_edit" value="<?= $p['id'] ?>">
          <input type="hidden" name="examen_id_preg"   value="<?= $eid ?>">
          <div class="form-group">
            <label class="form-label" style="color:var(--gold);">Enunciado</label>
            <textarea name="pregunta_edit" class="form-control" rows="2" required style="background:var(--navy-light);color:#f5f0e8;border-color:rgba(209,147,9,0.3);"><?= sanitizar($p['pregunta']) ?></textarea>
          </div>
          <?php if ($p['tipo'] === 'multiple'): ?>
          <div class="grid-2" style="gap:0.5rem;">
            <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $l=>$lab): ?>
            <div class="form-group" style="margin-bottom:0.5rem;">
              <label class="form-label" style="color:var(--gold);"><?= $lab ?> <?= $l===$p['respuesta_correcta']?'✓ (correcta)':'' ?></label>
              <input type="text" name="opcion_<?= $l ?>_edit" class="form-control" value="<?= sanitizar($p['opcion_'.$l]) ?>" style="background:var(--navy-light);color:#f5f0e8;border-color:rgba(209,147,9,0.3);">
            </div>
            <?php endforeach; ?>
          </div>
          <div class="form-group">
            <label class="form-label" style="color:var(--gold);">Respuesta correcta</label>
            <select name="respuesta_correcta_edit" class="form-control" style="background:var(--navy-light);color:#f5f0e8;border-color:rgba(209,147,9,0.3);">
              <?php foreach(['a','b','c','d'] as $l): ?>
              <option value="<?= $l ?>" <?= $p['respuesta_correcta']===$l?'selected':'' ?>><?= strtoupper($l) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php elseif ($p['tipo'] === 'verdadero_falso'): ?>
          <div class="form-group">
            <label class="form-label" style="color:var(--gold);">Respuesta correcta</label>
            <select name="respuesta_correcta_edit" class="form-control" style="background:var(--navy-light);color:#f5f0e8;border-color:rgba(209,147,9,0.3);">
              <option value="a" <?= $p['respuesta_correcta']==='a'?'selected':'' ?>>Verdadero</option>
              <option value="b" <?= $p['respuesta_correcta']==='b'?'selected':'' ?>>Falso</option>
            </select>
          </div>
          <?php endif; ?>
          <div style="display:flex;gap:0.5rem;margin-top:0.75rem;">
            <button type="submit" name="editar_pregunta" class="btn btn-primary btn-sm">✓ Guardar</button>
            <button type="button" class="btn btn-ghost btn-sm"
                    onclick="this.closest('.edit-form').style.display='none';this.closest('.pregunta-card').querySelector('[onclick*=edit-form]').style.display='inline-flex';">
              Cancelar
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; endif; ?>
    <a href="examenes.php" class="btn btn-ghost btn-sm mt-2">← Volver a exámenes</a>

    <?php else: ?>
    <!-- ══ LISTA EXÁMENES ══ -->
    <div class="d-flex justify-between align-center mb-2" style="flex-wrap:wrap;gap:1rem;">
      <h2 style="margin:0;">Gestión de Exámenes</h2>
      <button onclick="toggleFormExamen()" class="btn btn-primary">+ Nuevo Examen</button>
    </div>
    <hr class="divider-gold left" style="margin-bottom:2rem;">

    <div id="form-examen" class="card mb-4" style="display:none;">
      <h3 class="mb-3">Crear nuevo examen</h3>
      <form method="POST">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input type="text" name="titulo" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Puntaje mínimo (%)</label>
            <input type="number" name="puntaje_minimo" class="form-control" value="60" min="0" max="100">
          </div>
          <div class="form-group">
            <label class="form-label">Máximo de intentos (0 = ilimitado)</label>
            <input type="number" name="max_intentos" class="form-control" value="0" min="0" max="99"
                   style="max-width:160px;">
            <p style="font-size:0.78rem; color:var(--text-muted); margin-top:0.3rem;">0 = sin límite</p>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Asignar a módulo *</label>
          <select name="modulo_id" class="form-control" required>
            <option value="">— Selecciona un módulo —</option>
            <?php foreach ($modulos_all as $m): ?>
            <option value="<?= $m['id'] ?>"><?= sanitizar($m['curso']) ?> › <?= sanitizar($m['titulo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Instrucciones (opcional)</label>
          <textarea name="descripcion" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" name="crear_examen" class="btn btn-primary">Crear examen</button>
        <button type="button" onclick="toggleFormExamen()" class="btn btn-ghost" style="margin-left:0.5rem;">Cancelar</button>
      </form>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Examen</th><th>Módulo / Curso</th><th>Preguntas</th><th>Mín.</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php if (empty($todos_examenes)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--gray-mid);">Sin exámenes aún.</td></tr>
          <?php else: foreach ($todos_examenes as $ex): ?>
          <tr>
            <td><strong style="color:var(--text);"><?= sanitizar($ex['titulo']) ?></strong></td>
            <td style="font-size:0.85rem;"><span style="color:var(--gray-mid);"><?= sanitizar($ex['curso']) ?></span><br><?= sanitizar($ex['modulo']) ?></td>
            <td><span class="badge <?= $ex['num_preguntas']>0?'badge-success':'badge-gray' ?>"><?= $ex['num_preguntas'] ?></span></td>
            <td><span class="badge badge-gold"><?= $ex['puntaje_minimo'] ?>%</span></td>
            <td>
              <div class="d-flex gap-1">
                <a href="examenes.php?accion=preguntas&examen_id=<?= $ex['id'] ?>" class="btn btn-outline btn-sm"><?= icono('editar','ico') ?> Preguntas</a>
                <a href="entregas.php?examen_id=<?= $ex['id'] ?>" class="btn btn-ghost btn-sm"><?= icono('entregas','ico') ?> Entregas</a>
                <form method="POST" onsubmit="return confirm('¿Eliminar examen?')">
                  <input type="hidden" name="examen_id_del" value="<?= $ex['id'] ?>">
                  <button type="submit" name="eliminar_examen" class="btn btn-danger btn-sm">✕</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<footer><p style="margin:0;color:var(--gray-mid);font-size:0.85rem;">&copy; <?= date('Y') ?> Instituto Bíblico Bautista</p></footer>

<script>
function toggleFormExamen() {
  const f = document.getElementById('form-examen');
  f.style.display = f.style.display === 'none' ? '' : 'none';
}
function setTipo(tipo) {
  document.getElementById('input-tipo').value = tipo;
  document.querySelectorAll('.campos-tipo').forEach(el => el.classList.remove('visible'));
  document.querySelectorAll('.tipo-tab').forEach(el => el.classList.remove('active'));
  document.getElementById('campos-' + tipo).classList.add('visible');
  event.target.classList.add('active');
}
</script>
</body>
</html>

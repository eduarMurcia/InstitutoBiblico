<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/notificaciones.php';
require_once 'includes/portada_curso.php';
require_once 'includes/estudiante_layout.php';
requerir_login();

$conn = conectar();
$uid  = $_SESSION['usuario_id'];

$curso_id   = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$leccion_id = isset($_GET['leccion']) ? (int)$_GET['leccion'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['completar'])) {
    $lid = (int)$_POST['leccion_id'];
    $stmt = $conn->prepare("INSERT INTO progreso (usuario_id, leccion_id, completado, fecha_completado) VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE completado=1, fecha_completado=NOW()");
    $stmt->bind_param("ii", $uid, $lid);
    $stmt->execute(); $stmt->close();
    redirigir('cursos.php?id=' . $curso_id . '&leccion=' . $leccion_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentar'])) {
    $lid     = (int)$_POST['leccion_id'];
    $mensaje = sanitizar($_POST['mensaje'] ?? '');
    if ($mensaje) {
        $stmt = $conn->prepare("INSERT INTO comentarios (leccion_id, usuario_id, mensaje) VALUES (?,?,?)");
        $stmt->bind_param("iis", $lid, $uid, $mensaje);
        $stmt->execute(); $stmt->close();

        // Notificar al admin
        $nombre_est = sanitizar($_SESSION['nombre']);
        crear_notificacion(
            'comentario',
            "Nuevo comentario de {$nombre_est}",
            mb_strimwidth($mensaje, 0, 120, '…'),
            'admin/comentarios.php'
        );
    }
    redirigir('cursos.php?id=' . $curso_id . '&leccion=' . $leccion_id);
}

if ($curso_id) {
    $stmt = $conn->prepare("SELECT * FROM cursos WHERE id=? AND publicado=1");
    $stmt->bind_param("i", $curso_id); $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$curso) { redirigir('cursos.php'); }

    $modulos_q = $conn->prepare("
        SELECT m.id, m.titulo, m.descripcion,
               COUNT(DISTINCT l.id) AS total_l,
               COUNT(DISTINCT CASE WHEN p.completado=1 THEN p.leccion_id END) AS comp_l
        FROM modulos m
        LEFT JOIN lecciones l ON l.modulo_id = m.id
        LEFT JOIN progreso p ON p.leccion_id = l.id AND p.usuario_id = ?
        WHERE m.curso_id = ? GROUP BY m.id ORDER BY m.orden
    ");
    $modulos_q->bind_param("ii", $uid, $curso_id); $modulos_q->execute();
    $modulos = $modulos_q->get_result()->fetch_all(MYSQLI_ASSOC); $modulos_q->close();

    foreach ($modulos as &$mod) {
        $lec_q = $conn->prepare("SELECT l.*, COALESCE(p.completado,0) AS completado FROM lecciones l LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=? WHERE l.modulo_id=? ORDER BY l.orden");
        $lec_q->bind_param("ii", $uid, $mod['id']); $lec_q->execute();
        $mod['lecciones'] = $lec_q->get_result()->fetch_all(MYSQLI_ASSOC); $lec_q->close();
        $ex_q = $conn->prepare("SELECT id, titulo FROM examenes WHERE modulo_id=? LIMIT 1");
        $ex_q->bind_param("i", $mod['id']); $ex_q->execute();
        $mod['examen'] = $ex_q->get_result()->fetch_assoc(); $ex_q->close();
    }
    unset($mod);

    // Encontrar lección para continuar (primera no completada)
    $primera_leccion  = null;
    $leccion_continuar = null;
    $hay_progreso     = false;
    foreach ($modulos as $mod) {
        foreach ($mod['lecciones'] as $lec) {
            if (!$primera_leccion) $primera_leccion = $lec;
            if ($lec['completado']) $hay_progreso = true;
            if (!$leccion_continuar && !$lec['completado']) $leccion_continuar = $lec;
        }
    }
    // Si todo completado, la última lección
    if (!$leccion_continuar) $leccion_continuar = $primera_leccion;

    $leccion_activa = null;
    if ($leccion_id) {
        $lec_q = $conn->prepare("SELECT * FROM lecciones WHERE id=?");
        $lec_q->bind_param("i", $leccion_id); $lec_q->execute();
        $leccion_activa = $lec_q->get_result()->fetch_assoc(); $lec_q->close();
    }

    $comentarios = [];
    if ($leccion_activa) {
        $com_q = $conn->prepare("SELECT co.*, u.nombre FROM comentarios co JOIN usuarios u ON u.id=co.usuario_id WHERE co.leccion_id=? ORDER BY co.created_at DESC");
        $com_q->bind_param("i", $leccion_id); $com_q->execute();
        $comentarios = $com_q->get_result()->fetch_all(MYSQLI_ASSOC); $com_q->close();
    }

} else {
    $filtro = $_GET['filtro'] ?? '';
    $stmt = $conn->prepare("
        SELECT c.id, c.titulo, c.descripcion, c.imagen, c.color_portada, c.icono_portada, c.instructor,
               COUNT(DISTINCT l.id) AS total_lecciones,
               COUNT(DISTINCT CASE WHEN p.completado=1 THEN p.leccion_id END) AS completadas,
               COUNT(DISTINCT p.leccion_id) AS iniciadas
        FROM cursos c
        LEFT JOIN modulos m ON m.curso_id=c.id
        LEFT JOIN lecciones l ON l.modulo_id=m.id
        LEFT JOIN progreso p ON p.leccion_id=l.id AND p.usuario_id=?
        WHERE c.publicado=1 GROUP BY c.id ORDER BY c.orden, c.id
    ");
    $stmt->bind_param("i", $uid); $stmt->execute();
    $catalogo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    // "Mis cursos": solo los que el estudiante ya inició (tiene progreso registrado)
    if ($filtro === 'iniciados') {
        $catalogo = array_values(array_filter($catalogo, fn($c) => (int)$c['iniciadas'] > 0));
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,300;1,400;1,500&display=swap">
  <title><?= $curso_id ? sanitizar($curso['titulo']) . ' — ' : 'Cursos — ' ?>Instituto Bíblico Bautista</title>
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
  <?php portada_css(); ?>
  <style>
    .leccion-item { display:flex; align-items:center; gap:0.75rem; padding:0.65rem 1.25rem; text-decoration:none; color:var(--text-soft); transition:background 0.15s; border-bottom:1px solid var(--border); font-size:0.92rem; }
    .leccion-item:hover { background:rgba(209,147,9,0.06); color:var(--navy); }
    .leccion-item.active { background:var(--gold-pale); color:#7a5500; border-left:2px solid var(--gold); }
    .check { width:20px; height:20px; border-radius:50%; border:2px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:0.68rem; flex-shrink:0; }
    .check.done { background:var(--gold); border-color:var(--gold); color:var(--navy); }
    .comment-card { background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:1rem; margin-bottom:0.75rem; }
    .comment-card .meta { font-size:0.78rem; color:var(--text-muted); margin-bottom:0.35rem; }
    .catalog-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:1.5rem; transition:all 0.2s; box-shadow:var(--shadow); }
    .catalog-card:hover { border-color:var(--border-gold); box-shadow:var(--shadow-lg); }
    .pdf-viewer-wrap { background:var(--bg-card); border:1px solid rgba(10,138,163,0.3); border-radius:var(--radius-lg); padding:1.25rem; margin-bottom:1.5rem; box-shadow:var(--shadow); }
  </style>
</head>
<body>

<?php estudiante_navbar("cursos"); ?>

<?php if ($curso_id && $curso): ?>
<div class="main-layout">
  <aside class="sidebar">
    <div style="padding:0 1.25rem 1rem; border-bottom:1px solid var(--border); margin-bottom:1rem;">
      <a href="cursos.php" style="font-size:0.78rem; color:var(--text-muted);">← Todos los cursos</a>
      <h3 style="margin-top:0.5rem; font-size:0.95rem;"><?= sanitizar($curso['titulo']) ?></h3>
    </div>
    <?php foreach ($modulos as $mod): ?>
    <div style="margin-bottom:0.75rem;">
      <p class="sidebar-section-label" style="margin-top:0.5rem;"><?= sanitizar($mod['titulo']) ?></p>
      <?php foreach ($mod['lecciones'] as $lec): ?>
      <a href="cursos.php?id=<?= $curso_id ?>&leccion=<?= $lec['id'] ?>"
         class="leccion-item <?= $leccion_id === $lec['id'] ? 'active' : '' ?>">
        <span class="check <?= $lec['completado'] ? 'done' : '' ?>"><?= $lec['completado'] ? '✓' : '' ?></span>
        <span><?= sanitizar($lec['titulo']) ?></span>
      </a>
      <?php endforeach; ?>
      <?php if ($mod['examen']): ?>
      <a href="examen.php?id=<?= $mod['examen']['id'] ?>" class="leccion-item" style="color:var(--gold);">
        <span class="check" style="border-color:var(--gold); color:var(--gold);"><?= icono('editar','ico') ?></span>
        <span>Examen del módulo</span>
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </aside>

  <main class="main-content">
    <?php if ($leccion_activa): ?>

      <div style="margin-bottom:1.25rem;">
        <span style="font-family:var(--font-ui); font-size:0.72rem; color:var(--text-muted);">
          <?= sanitizar($curso['titulo']) ?>
        </span>
        <h2 style="margin:0.25rem 0;"><?= sanitizar($leccion_activa['titulo']) ?></h2>
        <hr class="divider-gold left">
      </div>

      <?php if ($leccion_activa['descripcion']): ?>
      <p style="margin-bottom:1.5rem;"><?= sanitizar($leccion_activa['descripcion']) ?></p>
      <?php endif; ?>

      <!-- Reproductor de audio -->
      <?php if ($leccion_activa['archivo_audio']): ?>
      <div class="audio-player-custom mb-3" id="audio-player-wrap">

        <!-- Waveform decorativa -->
        <div class="ap-wave" id="ap-wave">
          <?php for ($i=0; $i<40; $i++): ?>
          <div class="ap-bar" style="height:<?= rand(20,80) ?>%"></div>
          <?php endfor; ?>
        </div>

        <!-- Info de la lección -->
        <div class="ap-info">
          <div class="ap-leccion"><?= sanitizar($leccion_activa['titulo']) ?></div>
          <?php if ($leccion_activa['duracion']): ?>
          <div class="ap-duracion-label"><?= sanitizar($leccion_activa['duracion']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Barra de progreso -->
        <div class="ap-progress-wrap" id="ap-progress-wrap">
          <div class="ap-progress-buf" id="ap-buf"></div>
          <div class="ap-progress-bar" id="ap-bar"></div>
          <div class="ap-progress-thumb" id="ap-thumb"></div>
        </div>
        <div class="ap-times">
          <span id="ap-current">0:00</span>
          <span id="ap-total">0:00</span>
        </div>

        <!-- Controles -->
        <div class="ap-controls">
          <!-- Velocidad -->
          <div class="ap-speed-wrap">
            <?php foreach (['0.75','1','1.5','2'] as $sp): ?>
            <button class="ap-speed <?= $sp==='1'?'active':'' ?>"
                    onclick="setSpeed(<?= $sp ?>)"><?= $sp ?>x</button>
            <?php endforeach; ?>
          </div>

          <!-- Retroceder 15s -->
          <button class="ap-btn ap-skip" onclick="skipAudio(-15)" title="Retroceder 15s">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 .49-3.17"/>
              <text x="7" y="14" font-size="6" fill="currentColor" stroke="none" font-family="sans-serif">15</text>
            </svg>
          </button>

          <!-- Play / Pause -->
          <button class="ap-btn ap-play" id="ap-play-btn" onclick="togglePlay()">
            <svg id="ap-play-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor">
              <polygon points="5,3 19,12 5,21"/>
            </svg>
            <svg id="ap-pause-icon" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
              <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
            </svg>
          </button>

          <!-- Adelantar 15s -->
          <button class="ap-btn ap-skip" onclick="skipAudio(15)" title="Adelantar 15s">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-.49-3.17"/>
              <text x="7" y="14" font-size="6" fill="currentColor" stroke="none" font-family="sans-serif">15</text>
            </svg>
          </button>

          <!-- Volumen -->
          <div class="ap-vol-wrap">
            <button class="ap-btn ap-vol-btn" onclick="toggleMute()" id="ap-vol-btn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
              </svg>
            </button>
            <input type="range" class="ap-vol-slider" id="ap-vol" min="0" max="1" step="0.05" value="1"
                   oninput="setVolume(this.value)">
          </div>
        </div>

        <audio id="ap-audio" preload="metadata">
          <source src="api/audio.php?id=<?= $leccion_activa['id'] ?>" type="audio/mpeg">
        </audio>
      </div>

      <style>
      .audio-player-custom {
        background: var(--navy);
        border: 1px solid rgba(209,147,9,0.25);
        border-radius: var(--radius-lg);
        padding: 1.5rem 1.75rem 1.25rem;
        position: relative;
        overflow: hidden;
      }
      /* Waveform decorativa de fondo */
      .ap-wave {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 50px;
        display: flex;
        align-items: flex-end;
        gap: 2px;
        padding: 0 1.75rem;
        opacity: 0.08;
        pointer-events: none;
      }
      .ap-bar {
        flex: 1;
        background: var(--gold);
        border-radius: 2px 2px 0 0;
        transition: height 0.3s ease;
      }
      .ap-playing .ap-bar {
        animation: wave-pulse 1.2s ease-in-out infinite alternate;
      }
      .ap-bar:nth-child(odd)  { animation-delay: 0.1s; }
      .ap-bar:nth-child(3n)   { animation-delay: 0.3s; }
      .ap-bar:nth-child(5n)   { animation-delay: 0.5s; }
      @keyframes wave-pulse {
        0%   { transform: scaleY(0.6); }
        100% { transform: scaleY(1.3); }
      }
      /* Info */
      .ap-info {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: 1.1rem;
        gap: 1rem;
      }
      .ap-leccion {
        font-family: var(--font-title);
        font-size: 1.05rem;
        color: #f5f0e8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .ap-duracion-label {
        font-family: var(--font-ui);
        font-size: 0.72rem;
        color: var(--gold);
        white-space: nowrap;
        flex-shrink: 0;
      }
      /* Barra de progreso */
      .ap-progress-wrap {
        position: relative;
        height: 4px;
        background: rgba(245,240,232,0.1);
        border-radius: 999px;
        cursor: pointer;
        margin-bottom: 0.4rem;
      }
      .ap-progress-wrap:hover { height: 6px; margin-bottom: calc(0.4rem - 2px); }
      .ap-progress-buf {
        position: absolute; left: 0; top: 0; height: 100%;
        background: rgba(245,240,232,0.12);
        border-radius: 999px;
        width: 0;
        transition: width 0.5s linear;
      }
      .ap-progress-bar {
        position: absolute; left: 0; top: 0; height: 100%;
        background: linear-gradient(90deg, var(--gold), #f0b429);
        border-radius: 999px;
        width: 0;
        transition: width 0.1s linear;
      }
      .ap-progress-thumb {
        position: absolute; top: 50%; transform: translate(-50%, -50%);
        width: 12px; height: 12px;
        background: #f5f0e8;
        border-radius: 50%;
        left: 0;
        opacity: 0;
        transition: opacity 0.2s;
        box-shadow: 0 0 0 3px rgba(209,147,9,0.4);
      }
      .ap-progress-wrap:hover .ap-progress-thumb { opacity: 1; }
      .ap-times {
        display: flex;
        justify-content: space-between;
        font-family: var(--font-ui);
        font-size: 0.7rem;
        color: rgba(245,240,232,0.4);
        margin-bottom: 1.1rem;
      }
      /* Controles */
      .ap-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        position: relative;
        z-index: 1;
      }
      .ap-btn {
        background: none;
        border: none;
        color: rgba(245,240,232,0.7);
        cursor: pointer;
        padding: 0.4rem;
        border-radius: var(--radius);
        transition: color 0.15s, background 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .ap-btn:hover { color: #f5f0e8; background: rgba(255,255,255,0.08); }
      .ap-play {
        width: 52px; height: 52px;
        background: var(--gold) !important;
        color: var(--navy) !important;
        border-radius: 50% !important;
        margin: 0 0.75rem;
        box-shadow: 0 4px 16px rgba(209,147,9,0.35);
        transition: transform 0.15s, box-shadow 0.15s !important;
      }
      .ap-play:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 22px rgba(209,147,9,0.5) !important;
        background: #e8b84b !important;
      }
      .ap-skip { color: rgba(245,240,232,0.6); }
      /* Velocidad */
      .ap-speed-wrap {
        display: flex;
        gap: 0.2rem;
        margin-right: auto;
      }
      .ap-speed {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: var(--radius);
        color: rgba(245,240,232,0.5);
        font-family: var(--font-ui);
        font-size: 0.68rem;
        font-weight: 700;
        padding: 0.25rem 0.45rem;
        cursor: pointer;
        transition: all 0.15s;
      }
      .ap-speed:hover { color: #f5f0e8; background: rgba(255,255,255,0.1); }
      .ap-speed.active {
        background: rgba(209,147,9,0.2);
        border-color: var(--gold);
        color: var(--gold);
      }
      /* Volumen */
      .ap-vol-wrap {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-left: auto;
      }
      .ap-vol-slider {
        width: 64px;
        height: 3px;
        accent-color: var(--gold);
        cursor: pointer;
      }
      @media (max-width: 480px) {
        .ap-speed-wrap { display: none; }
        .ap-vol-wrap .ap-vol-slider { width: 44px; }
        .ap-leccion { font-size: 0.9rem; }
      }
      </style>

      <script>
      (function(){
        const audio    = document.getElementById('ap-audio');
        const bar      = document.getElementById('ap-bar');
        const buf      = document.getElementById('ap-buf');
        const thumb    = document.getElementById('ap-thumb');
        const wrap     = document.getElementById('ap-progress-wrap');
        const current  = document.getElementById('ap-current');
        const total    = document.getElementById('ap-total');
        const playIcon = document.getElementById('ap-play-icon');
        const pauseIcon= document.getElementById('ap-pause-icon');
        const player   = document.getElementById('audio-player-wrap');

        function fmt(s) {
          const m = Math.floor(s/60), sec = Math.floor(s%60);
          return m+':'+(sec<10?'0':'')+sec;
        }

        audio.addEventListener('loadedmetadata', () => { total.textContent = fmt(audio.duration); });
        audio.addEventListener('timeupdate', () => {
          if (!audio.duration) return;
          const pct = (audio.currentTime / audio.duration) * 100;
          bar.style.width   = pct + '%';
          thumb.style.left  = pct + '%';
          current.textContent = fmt(audio.currentTime);
          // Buffer
          if (audio.buffered.length) {
            buf.style.width = (audio.buffered.end(audio.buffered.length-1)/audio.duration*100)+'%';
          }
        });
        audio.addEventListener('ended', () => {
          playIcon.style.display=''; pauseIcon.style.display='none';
          player.classList.remove('ap-playing');
        });

        // Click en barra de progreso
        wrap.addEventListener('click', e => {
          const rect = wrap.getBoundingClientRect();
          audio.currentTime = ((e.clientX - rect.left) / rect.width) * audio.duration;
        });

        // Drag en barra
        let dragging = false;
        wrap.addEventListener('mousedown', () => dragging = true);
        document.addEventListener('mousemove', e => {
          if (!dragging) return;
          const rect = wrap.getBoundingClientRect();
          const pct = Math.max(0, Math.min(1, (e.clientX-rect.left)/rect.width));
          audio.currentTime = pct * audio.duration;
        });
        document.addEventListener('mouseup', () => dragging = false);

        window.togglePlay = function() {
          if (audio.paused) {
            audio.play();
            playIcon.style.display='none'; pauseIcon.style.display='';
            player.classList.add('ap-playing');
          } else {
            audio.pause();
            playIcon.style.display=''; pauseIcon.style.display='none';
            player.classList.remove('ap-playing');
          }
        };

        window.skipAudio = function(s) { audio.currentTime = Math.max(0, audio.currentTime+s); };

        window.setSpeed = function(s) {
          audio.playbackRate = s;
          document.querySelectorAll('.ap-speed').forEach(b => {
            b.classList.toggle('active', parseFloat(b.textContent)===s);
          });
        };

        window.setVolume = function(v) { audio.volume = v; };

        window.toggleMute = function() {
          audio.muted = !audio.muted;
          document.getElementById('ap-vol').value = audio.muted ? 0 : audio.volume;
        };
      })();
      </script>

      <?php else: ?>
      <div class="alert alert-info mb-3">El archivo de audio aún no ha sido cargado.</div>
      <?php endif; ?>

      <!-- Material PDF -->
      <?php if (!empty($leccion_activa['archivo_pdf'])): ?>
      <div class="pdf-viewer-wrap mb-3">
        <div class="d-flex justify-between align-center mb-2">
          <h4 style="margin:0; color:var(--teal);"><?= icono('documento','ico') ?> Material de la lección</h4>
          <a href="api/pdf.php?id=<?= $leccion_activa['id'] ?>"
             class="btn btn-outline btn-sm"
             style="border-color:var(--teal); color:var(--teal);" download>
            ↓ Descargar PDF
          </a>
        </div>
        <iframe src="api/pdf.php?id=<?= $leccion_activa['id'] ?>&ver=1"
                style="width:100%; height:520px; border:1px solid var(--border); border-radius:var(--radius);"
                title="Material de la lección">
          <a href="api/pdf.php?id=<?= $leccion_activa['id'] ?>">Descargar PDF</a>
        </iframe>
      </div>
      <?php endif; ?>

      <!-- Marcar completada -->
      <?php
      $ya_completado = false;
      foreach ($modulos as $mod) {
          foreach ($mod['lecciones'] as $lec) {
              if ($lec['id'] == $leccion_id && $lec['completado']) { $ya_completado = true; break 2; }
          }
      }
      ?>
      <form method="POST" style="margin-bottom:2rem;">
        <input type="hidden" name="leccion_id" value="<?= $leccion_id ?>">
        <?php if ($ya_completado): ?>
          <span class="badge badge-success" style="font-size:0.85rem; padding:0.5rem 1rem;">✓ Lección completada</span>
        <?php else: ?>
          <button type="submit" name="completar" class="btn btn-primary">✓ Marcar como completada</button>
        <?php endif; ?>
      </form>

      <!-- Foro -->
      <div class="card">
        <h3 class="mb-3"><?= icono('comentarios','ico') ?> Foro de la lección</h3>
        <form method="POST" style="margin-bottom:1.5rem;">
          <input type="hidden" name="leccion_id" value="<?= $leccion_id ?>">
          <div class="form-group">
            <label class="form-label">Comparte tu reflexión o pregunta</label>
            <textarea name="mensaje" class="form-control" rows="3" placeholder="Escribe tu comentario..." required></textarea>
          </div>
          <button type="submit" name="comentar" class="btn btn-outline btn-sm">Publicar</button>
        </form>
        <?php if (empty($comentarios)): ?>
          <p style="color:var(--text-muted); font-size:0.9rem; margin:0;">Aún no hay comentarios. Sé el primero en participar.</p>
        <?php else: ?>
          <?php foreach ($comentarios as $com): ?>
          <div class="comment-card">
            <div class="meta">
              <strong style="color:var(--navy);"><?= sanitizar($com['nombre']) ?></strong>
              — <?= date('d/m/Y H:i', strtotime($com['created_at'])) ?>
            </div>
            <p style="margin:0;"><?= sanitizar($com['mensaje']) ?></p>
            <?php if (!empty($com['respuesta_pastor'])): ?>
            <div style="margin-top:0.75rem; padding:0.65rem 1rem; background:rgba(209,147,9,0.07); border-left:3px solid var(--gold); border-radius:0 var(--radius) var(--radius) 0;">
              <div style="font-size:0.75rem; font-weight:700; color:var(--gold); margin-bottom:0.25rem;">
                ✦ Respuesta del pastor
                <span style="font-weight:400; color:var(--text-muted); margin-left:0.5rem;"><?= date('d/m/Y', strtotime($com['fecha_respuesta'])) ?></span>
              </div>
              <p style="margin:0; font-size:0.9rem;"><?= sanitizar($com['respuesta_pastor']) ?></p>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- Resumen del curso (sin lección seleccionada) -->
      <h2 style="margin-bottom:0.5rem;"><?= sanitizar($curso['titulo']) ?></h2>
      <hr class="divider-gold left">
      <p style="margin-bottom:2rem; max-width:600px;"><?= sanitizar($curso['descripcion']) ?></p>
      <?php if ($leccion_continuar): ?>
      <a href="cursos.php?id=<?= $curso_id ?>&leccion=<?= $leccion_continuar['id'] ?>"
         class="btn btn-primary">
        <?= $hay_progreso ? 'Continuar curso →' : 'Comenzar el curso →' ?>
      </a>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>

<?php else: ?>
<!-- Catálogo de cursos -->
<div style="max-width:1100px; margin:0 auto; padding:3rem 2rem;">
  <span style="font-family:var(--font-ui); font-size:0.72rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold);">Formación disponible</span>
  <h2 style="margin:0.4rem 0 0.25rem;"><?= $filtro==='iniciados' ? 'Mis cursos' : 'Cursos de formación bíblica' ?></h2>
  <hr class="divider-gold left" style="margin-bottom:1.5rem;">

  <!-- Filtro -->
  <div class="d-flex gap-2 mb-4" style="flex-wrap:wrap;">
    <a href="cursos.php" class="btn btn-sm <?= $filtro==='iniciados' ? 'btn-ghost btn-hover-azul btn-lift' : 'btn-outline' ?>">Todos los cursos</a>
    <a href="cursos.php?filtro=iniciados" class="btn btn-sm <?= $filtro==='iniciados' ? 'btn-outline' : 'btn-ghost btn-hover-azul btn-lift' ?>">Mis cursos</a>
  </div>

  <?php if (empty($catalogo)): ?>
    <div class="card text-center">
      <p style="margin:0;">
        <?= $filtro==='iniciados'
            ? 'Aún no has iniciado ningún curso. Explora el catálogo y comienza tu formación.'
            : 'Los cursos estarán disponibles próximamente.' ?>
      </p>
      <?php if ($filtro==='iniciados'): ?>
        <a href="cursos.php" class="btn btn-primary btn-sm mt-2" style="display:inline-block;">Ver todos los cursos →</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div class="cursos-portada-grid">
    <?php foreach ($catalogo as $c):
      $pct = $c['total_lecciones'] > 0 ? round(($c['completadas'] / $c['total_lecciones']) * 100) : 0;
    ?>
      <?php portada_curso($c, ['modo'=>'card', 'pct'=>$pct, 'enlace'=>'cursos.php?id='.$c['id']]); ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<footer>
  <div class="footer-brand">Instituto Bíblico Bautista</div>
  <p style="margin:0;">RV 1865 — Formación bíblica en línea</p>
</footer>
</body>
</html>

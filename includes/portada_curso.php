<?php
// includes/portada_curso.php
// Renderiza la miniatura visual de un curso.
// Uso: include y llamar portada_curso($curso, $opciones)
//
// $curso debe tener: titulo, descripcion, instructor, imagen, color_portada, icono_portada
// $opciones: ['modo' => 'card'|'hero', 'pct' => 67, 'enlace' => 'cursos.php?id=1', 'publico' => false]

function portada_curso(array $c, array $opts = []): void {
    $modo      = $opts['modo']    ?? 'card';      // 'card' = estudiante/público, 'hero' = grande
    $pct       = $opts['pct']     ?? null;         // progreso, null = no mostrar
    $enlace    = $opts['enlace']  ?? '';
    $publico   = $opts['publico'] ?? false;        // true = página pública (no requiere login)

    $color     = htmlspecialchars($c['color_portada'] ?? '#1a2744');
    $icono_val = $c['icono_portada'] ?? 'libro';
    $icono_map = [
        'libro'     => 'ti-book',
        'cruz'      => 'ti-cross',
        'paloma'    => 'ti-feather',
        'pergamino' => 'ti-file-text',
        'oracion'   => 'ti-heart',
        'iglesia'   => 'ti-building-church',
        'hoja'      => 'ti-leaf',
        'llama'     => 'ti-flame',
        'mapa'      => 'ti-map',
        'estrella'  => 'ti-star',
    ];
    // Compatibilidad con valores emoji anteriores
    $emoji_compat = [
        '📖'=>'ti-book','✝'=>'ti-cross','🕊'=>'ti-feather',
        '📜'=>'ti-file-text','🙏'=>'ti-heart','⛪'=>'ti-building-church',
        '🌿'=>'ti-leaf','🔦'=>'ti-flame','🗺'=>'ti-map','✦'=>'ti-star',
    ];
    $ti_class  = $icono_map[$icono_val] ?? $emoji_compat[$icono_val] ?? 'ti-book';
    $titulo    = htmlspecialchars($c['titulo']         ?? '');
    $desc      = htmlspecialchars(mb_strimwidth($c['descripcion'] ?? '', 0, 110, '…'));
    $instructor= htmlspecialchars($c['instructor']     ?? '');
    $img       = $c['imagen'] ?? '';
    $img_url   = $img ? 'uploads/portadas/' . htmlspecialchars($img) : '';

    $has_img   = (bool)$img_url;

    if ($modo === 'card'):
?>
<div class="curso-portada-card" style="<?= $enlace ? 'cursor:pointer;' : '' ?>"
     <?= $enlace ? "onclick=\"location.href='" . htmlspecialchars($enlace) . "'\"" : '' ?>>

  <!-- Miniatura -->
  <div class="portada-thumb" style="background:<?= $color ?>;">
    <?php if ($has_img): ?>
      <img src="<?= $img_url ?>" alt="<?= $titulo ?>" class="portada-img">
      <div class="portada-overlay"></div>
    <?php else: ?>
      <div class="portada-icono"><i class="ti <?= $ti_class ?>" style="font-size:2.5rem;color:#d19309;"></i></div>
    <?php endif; ?>
    <?php if ($pct !== null): ?>
      <div class="portada-pct-badge <?= $pct >= 100 ? 'pct-done' : '' ?>">
        <?= $pct >= 100 ? '✓' : $pct . '%' ?>
      </div>
    <?php endif; ?>
    <?php if ($publico && !$enlace): ?>
      <div class="portada-lock"><?= function_exists('icono') ? icono('candado','ico') : '' ?> Requiere cuenta</div>
    <?php endif; ?>
  </div>

  <!-- Info -->
  <div class="portada-info">
    <h3 class="portada-titulo"><?= $titulo ?></h3>
    <?php if ($instructor): ?>
      <p class="portada-instructor">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?= $instructor ?>
      </p>
    <?php endif; ?>
    <?php if ($desc): ?>
      <p class="portada-desc"><?= $desc ?></p>
    <?php endif; ?>
    <?php if ($pct !== null): ?>
      <div class="portada-progress-wrap">
        <div class="portada-progress-bar" style="--p:<?= round($pct / 100, 4) ?>;"></div>
      </div>
    <?php endif; ?>
    <?php if ($enlace): ?>
      <span class="portada-cta">
        <?= $pct === null ? 'Ver curso' : ($pct > 0 ? 'Continuar' : 'Comenzar') ?> →
      </span>
    <?php endif; ?>
  </div>
</div>
<?php
    else: // hero
?>
<div class="portada-hero" style="background:<?= $color ?>;">
  <?php if ($has_img): ?>
    <img src="<?= $img_url ?>" alt="<?= $titulo ?>" class="portada-img">
    <div class="portada-overlay"></div>
  <?php else: ?>
    <div class="portada-icono-hero"><i class="ti <?= $ti_class ?>" style="font-size:5rem;color:#d19309;opacity:0.25;"></i></div>
  <?php endif; ?>
  <div class="portada-hero-content">
    <div class="portada-eyebrow">Curso disponible</div>
    <h2 class="portada-hero-titulo"><?= $titulo ?></h2>
    <?php if ($instructor): ?><p class="portada-hero-inst">Pastor: <?= $instructor ?></p><?php endif; ?>
    <?php if ($desc): ?><p class="portada-hero-desc"><?= $desc ?></p><?php endif; ?>
    <?php if ($enlace): ?>
      <a href="<?= htmlspecialchars($enlace) ?>" class="btn btn-primary" style="margin-top:1rem;">Comenzar →</a>
    <?php endif; ?>
  </div>
</div>
<?php
    endif;
}

// CSS que debe inyectarse UNA sola vez en el <head> de cada página
function portada_css(): void {
    echo '
<style>
/* ── Curso portada card ── */
.cursos-portada-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
}
.curso-portada-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow);
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.curso-portada-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-lg);
  border-color: var(--border-gold);
}
.portada-thumb {
  position: relative;
  height: 168px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
}
.portada-img {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
}
.portada-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,5,20,0.72) 0%, rgba(0,5,20,0.15) 60%, transparent 100%);
}
.portada-icono {
  font-size: 3rem;
  z-index: 1;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,0.4));
}
.portada-icono-hero {
  font-size: 5rem;
  position: absolute;
  right: 3rem; top: 50%;
  transform: translateY(-50%);
  opacity: 0.25;
}
.portada-pct-badge {
  position: absolute;
  top: 0.75rem; right: 0.75rem;
  background: rgba(209,147,9,0.92);
  color: #000a23;
  font-family: var(--font-ui);
  font-size: 0.72rem;
  font-weight: 700;
  border-radius: 999px;
  padding: 0.2rem 0.6rem;
  z-index: 2;
}
.portada-pct-badge.pct-done {
  background: rgba(39,174,96,0.92);
  color: #fff;
}
.portada-lock {
  position: absolute;
  bottom: 0.6rem; right: 0.75rem;
  font-size: 0.72rem;
  color: rgba(245,240,232,0.7);
  z-index: 2;
}
.portada-info {
  padding: 1.1rem 1.25rem 1.25rem;
}
.portada-titulo {
  font-size: 1rem;
  color: var(--navy);
  margin: 0 0 0.3rem;
  line-height: 1.3;
}
.portada-instructor {
  font-size: 0.78rem;
  color: var(--gold);
  margin: 0 0 0.5rem;
}
.portada-desc {
  font-size: 0.84rem;
  color: var(--text-muted);
  margin: 0 0 0.75rem;
  line-height: 1.5;
}
.portada-progress-wrap {
  height: 4px;
  background: var(--border);
  border-radius: 999px;
  margin-bottom: 0.75rem;
  overflow: hidden;
}
.portada-progress-bar {
  height: 100%;
  background: var(--gold);
  border-radius: 999px;
  transition: width 0.4s;
}
.portada-cta {
  font-family: var(--font-ui);
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--gold);
  letter-spacing: 0.05em;
}

/* ── Hero ── */
.portada-hero {
  border-radius: var(--radius-lg);
  position: relative;
  overflow: hidden;
  min-height: 240px;
  display: flex;
  align-items: flex-end;
  padding: 2rem;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-gold);
}
.portada-hero .portada-img {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
}
.portada-hero .portada-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to right, rgba(0,5,20,0.88) 0%, rgba(0,5,20,0.3) 100%);
}
.portada-hero-content { position: relative; z-index: 1; max-width: 520px; }
.portada-eyebrow {
  font-family: var(--font-ui);
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 0.4rem;
}
.portada-hero-titulo { font-size: 1.6rem; color: #f5f0e8; margin: 0 0 0.35rem; }
.portada-hero-inst   { font-size: 0.85rem; color: var(--gold); margin: 0 0 0.5rem; }
.portada-hero-desc   { font-size: 0.9rem; color: rgba(245,240,232,0.75); margin: 0; line-height: 1.5; }
</style>';
}

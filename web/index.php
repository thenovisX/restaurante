<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';

if (!installed()) {
    header('Location: install.php');
    exit;
}

$s = settings();
$customer = current_customer();
$menu = menu_data(false);
$todayClosed = in_array(today(), $s['closed_dates'], true);
$todayRanges = $todayClosed ? [] : ($s['hours'][date('N')] ?? []);

// La palabra grande de la portada ocupa todo el ancho, sea cual sea el nombre
$brand = mb_strtoupper($s['name']);
// Nombres cortos: una sola línea a todo el ancho. Largos: se reparten en líneas según la palabra más larga.
$longest = max(array_map('mb_strlen', preg_split('/\s+/u', $brand)) ?: [3]);
$brandSize = mb_strlen($brand) <= 9
    ? min(27, round(128 / max(3, mb_strlen($brand)), 2))
    : min(18, round(110 / max(4, $longest), 2));

// Frases de la cinta: salen de la frase corta del restaurante
$ribbon = $s['ribbon'] !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $s['ribbon']))))
    : array_values(array_filter(array_map('trim', preg_split('/,| y /u', $s['tagline']))));
if (!$ribbon) $ribbon = [$s['name']];

// Políticas: cada línea "Título: texto"
$policyItems = [];
foreach (preg_split('/\R/', (string)$s['policies']) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    [$t, $txt] = array_pad(explode(':', $line, 2), 2, '');
    $policyItems[] = $txt === '' ? ['', trim($t)] : [trim($t), trim($txt)];
}
$description = $s['description'] ?: 'Una cocina de brasa y producto atlántico, una barra que se alarga hasta tarde y una sala pensada para quedarse. Ven a cenar; quédate a la siguiente copa.';

page_start($s['name'] . ' · ' . $s['tagline'], '', 'home');
?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org', '@type' => 'Restaurant', 'name' => $s['name'],
    'address' => trim($s['address'] . ', ' . $s['city'], ', '), 'telephone' => $s['phone'],
    'acceptsReservations' => true, 'url' => base_url(), 'image' => photo('hero', 1200),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>

<div class="loader" id="loader" aria-hidden="true"><span class="loader-n" id="loader-n">0</span><span class="loader-of">/ 100</span></div>

<?php site_header($customer, true); ?>

<main id="main">
  <section class="hero" aria-labelledby="hero-t">
    <img class="hero-img" src="<?= h(photo('hero', 1600)) ?>" srcset="<?= h(photo_srcset('hero')) ?>" sizes="100vw" alt="<?= h(PHOTOS['hero']['alt']) ?>" fetchpriority="high">
    <div class="hero-top">
      <p><?= h($s['city'] ?: 'Vigo') ?></p>
      <p class="hero-status"><span class="dot<?= $todayRanges ? ' is-open' : '' ?>" aria-hidden="true"></span><?= $todayRanges ? 'Hoy abrimos ' . h(ranges_text($todayRanges)) : 'Hoy descansamos' ?></p>
    </div>
    <div class="hero-mid">
      <p class="hero-lead"><?= h($s['tagline']) ?></p>
      <div class="hero-actions">
        <a class="btn btn-rubor" href="#reservar" data-book>Reservar mesa</a>
        <a class="btn btn-line" href="#carta">Ver la carta</a>
      </div>
    </div>
    <h1 class="hero-brand<?= mb_strlen($brand) > 9 ? ' is-long' : '' ?>" id="hero-t" style="--brand-size: <?= $brandSize ?>vw"><?= h($brand) ?></h1>
  </section>

  <div class="ribbon" aria-hidden="true">
    <div class="ribbon-track">
      <?php for ($k = 0; $k < 2; $k++): ?>
        <span><?php foreach ($ribbon as $r): ?><?= h($r) ?><i>✦</i><?php endforeach; ?><?php foreach ($ribbon as $r): ?><?= h($r) ?><i>✦</i><?php endforeach; ?></span>
      <?php endfor; ?>
    </div>
  </div>

  <section class="about" id="nosotros" aria-labelledby="about-t">
    <div class="wrap about-grid">
      <h2 id="about-t" class="sr">Nosotros</h2>
      <p class="about-text"><?= nl2br(h($description)) ?></p>
      <figure class="about-a">
        <img src="<?= h(photo('sala', 1000)) ?>" srcset="<?= h(photo_srcset('sala', [480, 800, 1200])) ?>" sizes="(max-width: 760px) 90vw, 40vw" alt="<?= h(PHOTOS['sala']['alt']) ?>" loading="lazy">
        <figcaption>La sala</figcaption>
      </figure>
      <figure class="about-b">
        <img src="<?= h(photo('cocina', 900)) ?>" srcset="<?= h(photo_srcset('cocina', [480, 800, 1200])) ?>" sizes="(max-width: 760px) 70vw, 28vw" alt="<?= h(PHOTOS['cocina']['alt']) ?>" loading="lazy">
        <figcaption>La cocina</figcaption>
      </figure>
    </div>
  </section>

  <?php include __DIR__ . '/lib/carta-libro.php'; ?>

  <section class="gallery" aria-label="Fotos">
    <?php foreach (['barra', 'plato', 'coctel'] as $g): ?>
      <figure><img src="<?= h(photo($g, 900)) ?>" srcset="<?= h(photo_srcset($g, [480, 900, 1300])) ?>" sizes="(max-width: 760px) 80vw, 33vw" alt="<?= h(PHOTOS[$g]['alt']) ?>" loading="lazy"></figure>
    <?php endforeach; ?>
  </section>

  <section class="book" id="reservar" aria-labelledby="book-t">
    <div class="wrap book-grid">
      <div>
        <h2 id="book-t" class="title">Reserva tu mesa</h2>
        <p class="book-lead">Elige día, hora y cuántos venís. <?= $s['auto_confirm'] ? 'Queda confirmada al momento.' : 'Te la confirmamos por email en cuanto la revisemos.' ?></p>
        <button class="btn btn-rubor btn-lg" type="button" data-book>Reservar mesa</button>
        <p class="book-alt">
          <?php if ($customer): ?>
            Reservas como <?= h($customer['name']) ?>. Tus reservas están en <a href="cuenta.php">tu cuenta</a>.
          <?php else: ?>
            Con una <a href="cuenta.php?registro=1">cuenta</a> reservas más rápido y puedes ver o cancelar tus reservas.
          <?php endif; ?>
          <?php if ($s['phone']): ?><br>Grupos de más de <?= (int)$s['max_people'] ?>: <a href="<?= h(tel_href($s['phone'])) ?>"><?= h($s['phone']) ?></a>.<?php endif; ?>
        </p>
      </div>
      <dl class="book-facts">
        <div><dt>Hoy</dt><dd><?= $todayRanges ? h(ranges_text($todayRanges)) : 'Cerrado' ?></dd></div>
        <div><dt>Cancelación</dt><dd>Gratis hasta <?= (int)$s['cancel_hours'] ?> h antes</dd></div>
        <div><dt>Retrasos</dt><dd>Guardamos la mesa <?= (int)$s['late_minutes'] ?> min</dd></div>
      </dl>
    </div>
  </section>

  <section class="policies" id="politicas" aria-labelledby="pol-t">
    <div class="wrap pol-grid">
      <h2 id="pol-t" class="title">Políticas</h2>
      <div class="pol-list">
        <details open>
          <summary>Reservas y cancelaciones</summary>
          <p>Puedes cancelar o cambiar tu reserva sin coste hasta <?= (int)$s['cancel_hours'] ?> horas antes. Si tienes cuenta, hazlo desde <a href="cuenta.php">Mi cuenta</a>; si no, llámanos<?= $s['phone'] ? ' al <a href="' . h(tel_href($s['phone'])) . '">' . h($s['phone']) . '</a>' : '' ?> o responde al email de confirmación.</p>
        </details>
        <details>
          <summary>Si llegas tarde</summary>
          <p>Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos. Pasado ese tiempo, si no nos has avisado, puede que la demos a otras personas.</p>
        </details>
        <?php foreach ($policyItems as [$t, $txt]): ?>
          <details>
            <summary><?= h($t ?: 'Más información') ?></summary>
            <p><?= h($txt) ?></p>
          </details>
        <?php endforeach; ?>
        <details>
          <summary>Tus datos</summary>
          <p>Usamos tus datos solo para gestionar tus reservas. No los cedemos a nadie ni te enviamos publicidad. Lee la <a href="legal.php#privacidad">política de privacidad</a>.</p>
        </details>
      </div>
    </div>
  </section>

  <section class="app" id="app" aria-labelledby="app-t">
    <div class="wrap app-grid">
      <div>
        <h2 id="app-t" class="title"><?= h($s['name']) ?> en tu móvil</h2>
        <p>Instala la web como una app: icono en tu pantalla de inicio, se abre a pantalla completa y reservas en dos toques. No ocupa espacio ni hay que pasar por ninguna tienda.</p>
        <div class="app-actions">
          <button class="btn btn-hueso" type="button" id="install-btn" hidden>Instalar la app</button>
          <p class="app-ios" id="install-ios" hidden>En iPhone: pulsa <strong>Compartir</strong> en Safari y luego <strong>Añadir a pantalla de inicio</strong>.</p>
          <p class="app-help" id="install-help">En el móvil, abre el menú del navegador y elige <strong>Instalar app</strong> o <strong>Añadir a pantalla de inicio</strong>.</p>
          <p class="app-done" id="install-done" hidden>Ya tienes la app instalada.</p>
        </div>
      </div>
      <div class="app-phone" aria-hidden="true">
        <div class="app-icon"><img src="icon.php?s=192" alt="" width="96" height="96"></div>
        <span><?= h($s['name']) ?></span>
      </div>
    </div>
  </section>
</main>

<?php site_footer(); ?>

<?php if ($s['whatsapp']): ?>
  <a class="wa-float" href="https://wa.me/<?= h(wa_number($s['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg>
  </a>
<?php endif; ?>

<?php include __DIR__ . '/lib/reserva-dialogo.php'; ?>
<?php page_end(['customer' => customer_out($customer)]); ?>

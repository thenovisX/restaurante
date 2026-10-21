<?php
declare(strict_types=1);

/* Cabecera, menú y pie comunes a todas las páginas públicas. */

require_once __DIR__ . '/tema.php';

const ASSET_V = '21';

function tel_href(string $t): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $t);
}

function maps_href(): string
{
    $s = settings();
    if ($s['maps_url'] !== '') return social_url('', $s['maps_url']);
    return 'https://www.google.com/maps/search/?api=1&query=' . urlencode(trim($s['name'] . ' ' . $s['address'] . ' ' . $s['city']));
}

function wa_number(string $t): string
{
    $n = preg_replace('/\D/', '', $t);
    return strlen($n) === 9 ? '34' . $n : $n;
}

function page_start(string $title, string $description = '', string $bodyClass = ''): void
{
    $s = settings();
    // Las páginas siempre se piden de nuevo, así los cambios del panel se ven al momento
    if (!headers_sent()) header('Cache-Control: no-cache, must-revalidate, max-age=0');
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($description ?: ($s['seo_description'] ?: trim($s['name'] . ' · ' . $s['tagline'] . ($s['city'] ? ' en ' . $s['city'] : '') . '. Reserva mesa online y consulta la carta.'))) ?>">
<link rel="canonical" href="<?= h(base_url() . (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' ? '/' : '/' . basename($_SERVER['SCRIPT_NAME'] ?? ''))) ?>">
<?php if ($s['gsc_verify'] !== ''): ?><meta name="google-site-verification" content="<?= h($s['gsc_verify']) ?>">
<?php endif; ?>
<script>
  window.SITE_TRACKING = <?= json_encode(['ga' => $s['ga_id'], 'gtm' => $s['gtm_id'], 'pixel' => $s['pixel_id']], JSON_HEX_TAG) ?>;
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500 });
</script>
<script src="assets/consent.js?v=<?= ASSET_V ?>" defer></script>
<meta property="og:title" content="<?= h($s['name']) ?>">
<meta property="og:description" content="<?= h($s['tagline']) ?>">
<meta property="og:image" content="<?= h(photo('hero', 1200)) ?>">
<meta name="theme-color" content="#000000">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= h($s['name']) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" href="icon.php?s=192" type="image/png">
<link rel="apple-touch-icon" href="icon.php?s=180">
<link rel="preconnect" href="https://images.unsplash.com">
<link rel="preload" href="assets/fonts/bodoni-moda-latin-opsz-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/web.css?v=<?= ASSET_V ?>">
<script>
  // Animaciones: se preparan antes de pintar; si el JavaScript no llegara a cargar, todo se muestra igual
  (function (d) {
    try { if (matchMedia('(prefers-reduced-motion: reduce)').matches) return; } catch (e) { return; }
    d.classList.add('js-motion');
    setTimeout(function () { if (!window.__motionOK) d.classList.remove('js-motion'); }, 2500);
  })(document.documentElement);
</script>
</head>
<body class="<?= h($bodyClass) ?>">
<a class="skip" href="#main">Saltar al contenido</a>
    <?php
}

function site_header(?array $customer, bool $overHero = false): void
{
    $s = settings();
    $first = $customer ? explode(' ', $customer['name'])[0] : '';
    ?>
<header class="hd<?= $overHero ? ' hd-over' : '' ?>" id="hd">
  <a class="hd-brand" href="./" aria-label="<?= h($s['name']) ?>, inicio"><?= h($s['name']) ?></a>
  <nav class="hd-nav" aria-label="Principal">
    <a href="./#nosotros">Nosotros</a>
    <a href="./#carta">Carta</a>
    <a href="./#app">App</a>
    <a href="./#politicas">Políticas</a>
  </nav>
  <div class="hd-end">
    <a class="hd-account" href="cuenta.php"><?= $customer ? h($first) : 'Entrar' ?></a>
    <a class="btn btn-rubor hd-book" href="./#reservar" data-book>Reservar</a>
    <button class="hd-toggle" type="button" aria-expanded="false" aria-controls="menu" aria-label="Abrir el menú" data-menu><span></span><span></span><span></span></button>
  </div>
</header>
<div class="menu" id="menu" hidden>
  <nav aria-label="Menú">
    <a href="./#nosotros">Nosotros</a>
    <a href="./#carta">Carta</a>
    <a href="./#reservar" data-book>Reservar</a>
    <a href="./#app">App</a>
    <a href="./#politicas">Políticas</a>
    <a href="cuenta.php"><?= $customer ? 'Mi cuenta' : 'Entrar o registrarse' ?></a>
  </nav>
  <p class="menu-foot"><?= h(trim($s['address'] . ' · ' . $s['city'], ' ·')) ?></p>
</div>
    <?php
}

function site_footer(): void
{
    $s = settings();
    $wa = wa_number($s['whatsapp']);
    $todayN = date('N');
    ?>
<footer class="ft">
  <div class="wrap">
    <?php if ($s['footer_title'] !== ''): ?><p class="ft-big"><?= h($s['footer_title']) ?></p><?php endif; ?>
    <div class="ft-cols">
      <div>
        <h2>Horario</h2>
        <ul class="ft-hours">
          <?php foreach (WEEKDAYS as $n => $day): ?>
            <li<?= (string)$n === $todayN ? ' class="is-today"' : '' ?>><span><?= $day ?></span><span><?= h(ranges_text($s['hours'][$n] ?? [])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h2>Contacto</h2>
        <?php if ($s['address']): ?><p><?= h($s['address']) ?><?= $s['city'] ? '<br>' . h($s['city']) : '' ?><br><a href="<?= h(maps_href()) ?>" target="_blank" rel="noopener">Cómo llegar</a></p><?php endif; ?>
        <p>
          <?php if ($s['phone']): ?><a href="<?= h(tel_href($s['phone'])) ?>"><?= h($s['phone']) ?></a><br><?php endif; ?>
          <?php if ($s['email']): ?><a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a><br><?php endif; ?>
          <?php if ($wa): ?><a href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        </p>
        <?php $nets = array_filter(['Instagram' => social_url('instagram', $s['instagram']), 'Facebook' => social_url('facebook', $s['facebook']), 'TikTok' => social_url('tiktok', $s['tiktok']), 'TripAdvisor' => social_url('tripadvisor', $s['tripadvisor'])]); ?>
        <?php if ($nets): ?>
          <p class="ft-redes"><?php foreach ($nets as $label => $url): ?><a href="<?= h($url) ?>" target="_blank" rel="noopener"><?= $label ?></a><?php endforeach; ?></p>
        <?php endif; ?>
      </div>
      <div>
        <h2>Más</h2>
        <p><a href="cuenta.php">Mi cuenta</a><br><a href="./#politicas">Políticas de reserva</a><br><a href="legal.php#aviso">Aviso legal</a><br><a href="legal.php#condiciones">Condiciones de uso</a><br><a href="legal.php#privacidad">Privacidad</a><br><a href="legal.php#cookies">Cookies</a><br><a href="legal.php#cookies" data-cookies>Configurar cookies</a></p>
      </div>
    </div>
    <p class="ft-legal">© <?= date('Y') ?> <?= h($s['name']) ?>.<?php $cr = photo_credits(); if ($cr): ?> Fotos de
      <?php $i = 0; foreach ($cr as $p): ?><?= $i++ ? ', ' : '' ?><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['by']) ?></a><?php endforeach; ?> en Unsplash.<?php endif; ?></p>
  </div>
</footer>
    <?php
}

function mobile_bar(): void
{
    $s = settings();
    $wa = wa_number($s['whatsapp']);
    ?>
<nav class="mbar" aria-label="Acciones rápidas">
  <a class="btn btn-rubor" href="./#reservar" data-book>Reservar mesa</a>
  <?php if ($s['phone']): ?><a class="mbar-ic" href="<?= h(tel_href($s['phone'])) ?>" aria-label="Llamar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></svg></a><?php endif; ?>
  <?php if ($wa): ?><a class="mbar-ic" href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg></a><?php endif; ?>
</nav>
    <?php
}

function page_end(array $data = []): void
{
    mobile_bar();
    $s = settings();
    $data += ['today' => today(), 'daysAhead' => (int)$s['days_ahead'], 'maxPeople' => (int)$s['max_people'], 'name' => $s['name'],
        'hours' => $s['hours'], 'closed' => $s['closed_dates'], 'phone' => $s['phone']];
    ?>
<script>window.RESTAURANT = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="assets/web.js?v=<?= ASSET_V ?>" defer></script>
</body>
</html>
    <?php
}

/** Icono de un alérgeno (dibujos en assets/alergenos.svg). */
function al_icon(string $key, int $size = 22, string $label = ''): string
{
    $aria = $label === '' ? 'aria-hidden="true"' : 'role="img" aria-label="' . h($label) . '"';
    return '<svg class="al-ic" width="' . $size . '" height="' . $size . '" ' . $aria . '>'
        . ($label !== '' ? '<title>' . h($label) . '</title>' : '')
        . '<use href="assets/alergenos.svg?v=' . ASSET_V . '#al-' . h($key) . '"></use></svg>';
}

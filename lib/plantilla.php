<?php
declare(strict_types=1);

/* Cabecera, menú y pie comunes a todas las páginas públicas. */

require_once __DIR__ . '/tema.php';

const ASSET_V = '30';

// Idioma de la visita (las páginas públicas cargan este archivo antes de pintar nada)
detect_lang();

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

/** Bandera circular de cada idioma (dibujada en SVG: Windows no muestra emojis de banderas y Galicia no tiene emoji). */
function flag_svg(string $l): string
{
    $f = [
        'es' => '<rect width="60" height="60" fill="#AA151B"/><rect y="15" width="60" height="30" fill="#F1BF00"/>',
        'en' => '<rect width="60" height="60" fill="#012169"/><path d="M0 0L60 60M60 0L0 60" stroke="#fff" stroke-width="12"/><path d="M0 0L60 60M60 0L0 60" stroke="#C8102E" stroke-width="4"/><path d="M30 0V60M0 30H60" stroke="#fff" stroke-width="18"/><path d="M30 0V60M0 30H60" stroke="#C8102E" stroke-width="10"/>',
        'pt' => '<rect width="60" height="60" fill="#DA291C"/><rect width="24" height="60" fill="#046A38"/><circle cx="24" cy="30" r="10" fill="none" stroke="#FFE900" stroke-width="3"/><path d="M19 24h10v8a5 5 0 0 1-10 0z" fill="#fff" stroke="#DA291C" stroke-width="1.5"/>',
        'gl' => '<rect width="60" height="60" fill="#fff"/><path d="M-6 6L54 66L66 54L6 -6z" fill="#0099CC"/>',
    ];
    return '<svg class="flag" viewBox="0 0 60 60" width="26" height="26" aria-hidden="true" focusable="false">' . ($f[$l] ?? '') . '</svg>';
}

/** Selector de idioma con las banderas. */
function lang_switcher(string $extra = ''): string
{
    $on = langs_on();
    if (count($on) < 2) return '';
    $cur = lang();
    $o = '<div class="langs ' . h($extra) . '" role="group" aria-label="' . h(t('Idioma')) . '">';
    foreach ($on as $l) {
        $o .= '<a class="lang' . ($l === $cur ? ' is-on' : '') . '" href="' . h(lang_url($l)) . '" hreflang="' . LANGS[$l]['locale'] . '" lang="' . LANGS[$l]['locale'] . '"'
            . ($l === $cur ? ' aria-current="true"' : '') . ' title="' . h(LANGS[$l]['name']) . '"><span class="flag-c">' . flag_svg($l) . '</span><span class="sr">' . h(LANGS[$l]['name']) . '</span></a>';
    }
    return $o . '</div>';
}

/** Textos del JavaScript (ventana de reserva, aviso de cookies…) en el idioma actual. */
function js_i18n(): array
{
    if (lang() === 'es') return [];
    $keys = require __DIR__ . '/lang/js-keys.php';
    $out = [];
    foreach ($keys as $k) $out[$k] = t($k);
    $out['_days'] = I18N_DAYS[lang()];
    $out['_months'] = I18N_MONTHS[lang()];
    return $out;
}

function page_start(string $title, string $description = '', string $bodyClass = ''): void
{
    $s = settings();
    // Las páginas siempre se piden de nuevo, así los cambios del panel se ven al momento
    if (!headers_sent()) header('Cache-Control: no-cache, must-revalidate, max-age=0');
    ?>
<!doctype html>
<html lang="<?= LANGS[lang()]['locale'] ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?></title>
<?php $pageUrl = base_url() . (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' ? '/' : '/' . basename($_SERVER['SCRIPT_NAME'] ?? '')); ?>
<meta name="description" content="<?= h($description ?: (lang() === 'es' && $s['seo_description'] ? $s['seo_description'] : trim($s['name'] . ' · ' . tr($s['tagline']) . '. ' . t('Reserva mesa online y consulta la carta.')))) ?>">
<link rel="canonical" href="<?= h($pageUrl . (lang() !== 'es' ? '?lang=' . lang() : '')) ?>">
<?php foreach (langs_on() as $l): ?><link rel="alternate" hreflang="<?= LANGS[$l]['locale'] ?>" href="<?= h($pageUrl . ($l !== 'es' ? '?lang=' . $l : '')) ?>">
<?php endforeach; ?><link rel="alternate" hreflang="x-default" href="<?= h($pageUrl) ?>">
<?php if ($s['gsc_verify'] !== ''): ?><meta name="google-site-verification" content="<?= h($s['gsc_verify']) ?>">
<?php endif; ?>
<script>
  window.I18N = <?= json_encode(js_i18n(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  window.SITE_TRACKING = <?= json_encode(['ga' => $s['ga_id'], 'gtm' => $s['gtm_id'], 'pixel' => $s['pixel_id']], JSON_HEX_TAG) ?>;
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500 });
</script>
<script src="assets/consent.js?v=<?= ASSET_V ?>" defer></script>
<meta property="og:title" content="<?= h($s['name']) ?>">
<meta property="og:description" content="<?= h(tr($s['tagline'])) ?>">
<meta property="og:locale" content="<?= str_replace('-', '_', LANGS[lang()]['locale']) ?>">
<meta property="og:image" content="<?= h((preg_match('#^https?://#', photo('hero', 1200)) ? '' : base_url() . '/') . photo('hero', 1200)) ?>">
<meta name="theme-color" content="#000000">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= h($s['name']) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" href="icon.php?s=192" type="image/png">
<link rel="apple-touch-icon" href="icon.php?s=180">
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
<a class="skip" href="#main"><?= t('Saltar al contenido') ?></a>
    <?php
}

function site_header(?array $customer, bool $overHero = false): void
{
    $s = settings();
    $first = $customer ? explode(' ', $customer['name'])[0] : '';
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<header class="hd<?= $overHero ? ' hd-over' : '' ?>" id="hd">
  <a class="hd-brand" href="./" aria-label="<?= h($s['name']) ?>, <?= t('inicio') ?>"><?= h($s['name']) ?></a>
  <nav class="hd-nav" aria-label="<?= t('Principal') ?>">
    <a href="./#nosotros"><?= t('Nosotros') ?></a>
    <a href="carta.php"<?= $page === 'carta.php' ? ' aria-current="page"' : '' ?>><?= t('Carta') ?></a>
    <a href="reservar.php"<?= $page === 'reservar.php' ? ' aria-current="page"' : '' ?>><?= t('Reservas') ?></a>
    <a href="./#app"><?= t('App') ?></a>
  </nav>
  <div class="hd-end">
    <?= lang_switcher('langs-hd') ?>
    <a class="hd-account" href="cuenta.php"><?= $customer ? h($first) : t('Entrar') ?></a>
    <button class="hd-toggle" type="button" aria-expanded="false" aria-controls="menu" aria-label="<?= t('Abrir el menú') ?>" data-menu><span></span><span></span><span></span></button>
  </div>
</header>
<div class="menu" id="menu" hidden>
  <?= lang_switcher('langs-menu') ?>
  <nav aria-label="<?= t('Menú') ?>">
    <a href="./#nosotros"><?= t('Nosotros') ?></a>
    <a href="carta.php"><?= t('Carta') ?></a>
    <a href="reservar.php"><?= t('Reservas y políticas') ?></a>
    <a href="reservar.php" data-book><?= t('Reservar mesa') ?></a>
    <a href="./#app"><?= t('App') ?></a>
    <a href="cuenta.php"><?= $customer ? t('Mi cuenta') : t('Entrar o registrarse') ?></a>
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
    <?php if ($s['footer_title'] !== ''): ?><p class="ft-big"><?= h(tr($s['footer_title'])) ?></p><?php endif; ?>
    <div class="ft-cols">
      <div>
        <h2><?= t('Horario') ?></h2>
        <ul class="ft-hours">
          <?php foreach (WEEKDAYS as $n => $day): $day = day_name((int)$n, true); ?>
            <?php $kit = $s['kitchen_on'] && ($s['hours'][$n] ?? []) && ($s['kitchen_hours'][$n] ?? []) != ($s['hours'][$n] ?? []) ? ranges_text($s['kitchen_hours'][$n] ?? []) : ''; ?>
            <li<?= (string)$n === $todayN ? ' class="is-today"' : '' ?>><span><?= $day ?></span><span><?= h(ranges_text($s['hours'][$n] ?? [])) ?><?php if ($kit !== ''): ?><small class="ft-cocina"><?= t('Cocina') ?>: <?= h($kit === t('Cerrado') ? t('cerrada') : $kit) ?></small><?php endif; ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h2><?= t('Contacto') ?></h2>
        <?php if ($s['address']): ?><p><?= h($s['address']) ?><?= $s['city'] ? '<br>' . h($s['city']) : '' ?><br><a href="<?= h(maps_href()) ?>" target="_blank" rel="noopener"><?= t('Cómo llegar') ?></a></p><?php endif; ?>
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
        <h2><?= t('Más') ?></h2>
        <p><a href="cuenta.php"><?= t('Mi cuenta') ?></a><br><a href="carta.php"><?= t('La carta') ?></a><br><a href="reservar.php#politicas"><?= t('Políticas de reserva') ?></a><br><a href="legal.php#aviso"><?= t('Aviso legal') ?></a><br><a href="legal.php#condiciones"><?= t('Condiciones de uso') ?></a><br><a href="legal.php#privacidad"><?= t('Privacidad') ?></a><br><a href="legal.php#cookies"><?= t('Cookies') ?></a><br><a href="legal.php#cookies" data-cookies><?= t('Configurar cookies') ?></a></p>
      </div>
    </div>
    <p class="ft-legal">© <?= date('Y') ?> <?= h($s['name']) ?>.<?php $cr = photo_credits(); if ($cr): ?> <?= t('Fotos de') ?>
      <?php $i = 0; foreach ($cr as $p): ?><?= $i++ ? ', ' : '' ?><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['by']) ?></a><?php endforeach; ?> <?= t('en Unsplash') ?>.<?php endif; ?></p>
  </div>
</footer>
    <?php
}

function mobile_bar(): void
{
    $s = settings();
    $wa = wa_number($s['whatsapp']);
    ?>
<nav class="mbar" aria-label="<?= t('Acciones rápidas') ?>">
  <a class="btn btn-rubor" href="reservar.php" data-book><?= t('Reservar mesa') ?></a>
  <?php if ($s['phone']): ?><a class="mbar-ic" href="<?= h(tel_href($s['phone'])) ?>" aria-label="<?= t('Llamar') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></svg></a><?php endif; ?>
  <?php if ($wa): ?><a class="mbar-ic" href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg></a><?php endif; ?>
</nav>
    <?php
}

function page_end(array $data = []): void
{
    mobile_bar();
    $s = settings();
    $data += ['today' => today(), 'daysAhead' => (int)$s['days_ahead'], 'maxPeople' => (int)$s['max_people'], 'name' => $s['name'],
        'hours' => $s['hours'], 'closed' => $s['closed_dates'], 'phone' => $s['phone'], 'now' => date('Y-m-d H:i')];
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

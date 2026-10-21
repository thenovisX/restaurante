<?php
declare(strict_types=1);

/* Cabecera, menú y pie comunes a todas las páginas públicas. */

require_once __DIR__ . '/tema.php';

const ASSET_V = '10';

function tel_href(string $t): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $t);
}

function wa_number(string $t): string
{
    $n = preg_replace('/\D/', '', $t);
    return strlen($n) === 9 ? '34' . $n : $n;
}

function page_start(string $title, string $description = '', string $bodyClass = ''): void
{
    $s = settings();
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($description ?: $s['name'] . ' · ' . $s['tagline']) ?>">
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
<script>try{if(sessionStorage.getItem('visto')||matchMedia('(prefers-reduced-motion: reduce)').matches)document.documentElement.classList.add('no-loader')}catch(e){}</script>
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
    <a href="./#politicas">Políticas</a>
    <a href="./#app">App</a>
  </nav>
  <div class="hd-end">
    <a class="hd-account" href="cuenta.php"><?= $customer ? h($first) : 'Entrar' ?></a>
    <a class="btn btn-rubor hd-book" href="./#reservar" data-book>Reservar</a>
    <button class="hd-toggle" type="button" aria-expanded="false" aria-controls="menu" data-menu>Menú</button>
  </div>
</header>
<div class="menu" id="menu" hidden>
  <nav aria-label="Menú">
    <a href="./#nosotros">Nosotros</a>
    <a href="./#carta">Carta</a>
    <a href="./#reservar" data-book>Reservar</a>
    <a href="./#politicas">Políticas</a>
    <a href="./#app">App</a>
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
    <p class="ft-big">¿Cenamos?</p>
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
        <?php if ($s['address']): ?><p><?= h($s['address']) ?><?= $s['city'] ? '<br>' . h($s['city']) : '' ?></p><?php endif; ?>
        <p>
          <?php if ($s['phone']): ?><a href="<?= h(tel_href($s['phone'])) ?>"><?= h($s['phone']) ?></a><br><?php endif; ?>
          <?php if ($s['email']): ?><a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a><br><?php endif; ?>
          <?php if ($wa): ?><a href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><br><?php endif; ?>
          <?php if ($s['instagram']): ?><a href="https://instagram.com/<?= h(ltrim($s['instagram'], '@')) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        </p>
      </div>
      <div>
        <h2>Más</h2>
        <p><a href="cuenta.php">Mi cuenta</a><br><a href="./#politicas">Políticas de reserva</a><br><a href="legal.php#aviso">Aviso legal</a><br><a href="legal.php#privacidad">Privacidad</a><br><a href="legal.php#cookies">Cookies</a></p>
      </div>
    </div>
    <p class="ft-legal">© <?= date('Y') ?> <?= h($s['name']) ?>. Fotos de
      <?php $i = 0; foreach (PHOTOS as $p): if (!isset($p['by'])) continue; ?><?= $i++ ? ', ' : '' ?><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['by']) ?></a><?php endforeach; ?> en Unsplash.</p>
  </div>
</footer>
    <?php
}

function page_end(array $data = []): void
{
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

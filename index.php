<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

if (!installed()) {
    header('Location: install.php');
    exit;
}

$s = settings();
$menu = menu_data(false);
$todayN = date('N');
$todayClosed = in_array(today(), $s['closed_dates'], true);
$todayRanges = $todayClosed ? [] : ($s['hours'][$todayN] ?? []);
$allergens = [];
foreach ($menu['allergens'] as $a) $allergens[$a['key']] = $a;
$usedAllergens = [];
foreach ($menu['categories'] as $c) foreach ($c['dishes'] as $d) foreach ($d['allergens'] as $k) $usedAllergens[$k] = true;

$price = fn(float $p) => number_format($p, 2, ',', '.') . ' €';
$telHref = fn(string $t) => 'tel:' . preg_replace('/[^\d+]/', '', $t);
$waNumber = preg_replace('/\D/', '', $s['whatsapp']);
if ($waNumber !== '' && strlen($waNumber) === 9) $waNumber = '34' . $waNumber;
$mapQuery = trim($s['name'] . ' ' . $s['address'] . ' ' . $s['city']);
$v = '3';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($s['name']) ?><?= $s['tagline'] ? ' · ' . h($s['tagline']) : '' ?></title>
<meta name="description" content="<?= h($s['description'] ?: $s['name'] . '. Consulta la carta y reserva mesa online.') ?>">
<meta property="og:title" content="<?= h($s['name']) ?>">
<meta property="og:description" content="<?= h($s['tagline']) ?>. Reserva mesa online.">
<meta name="theme-color" content="#14313F">
<link rel="preload" href="assets/fonts/young-serif-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/web.css?v=<?= $v ?>">
<link rel="icon" href="panel/icons/icon-192.png">
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org', '@type' => 'Restaurant', 'name' => $s['name'],
    'address' => trim($s['address'] . ', ' . $s['city'], ', '), 'telephone' => $s['phone'],
    'acceptsReservations' => true, 'url' => base_url(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>

<header class="top">
  <a class="top-name" href="#inicio"><?= h($s['name']) ?></a>
  <nav aria-label="Secciones">
    <a href="#carta">Carta</a>
    <a href="#reservar">Reservar</a>
    <a href="#horario">Horario</a>
  </nav>
</header>

<main>
  <section class="hero" id="inicio">
    <div class="hero-inner">
      <h1><?= h($s['name']) ?></h1>
      <?php if ($s['tagline']): ?><p class="hero-tag"><?= h($s['tagline']) ?></p><?php endif; ?>
      <p class="hero-today">
        <span class="dot <?= $todayRanges ? 'is-open' : '' ?>" aria-hidden="true"></span>
        <?= $todayRanges ? 'Hoy abrimos de ' . h(ranges_text($todayRanges)) : 'Hoy estamos cerrados' ?>
      </p>
      <div class="hero-actions">
        <a class="btn btn-main" href="#reservar">Reservar mesa</a>
        <a class="btn btn-ghost" href="#carta">Ver la carta</a>
      </div>
      <?php if ($s['description']): ?><p class="hero-desc"><?= nl2br(h($s['description'])) ?></p><?php endif; ?>
    </div>
  </section>

  <section class="carta" id="carta" aria-labelledby="carta-t">
    <div class="wrap">
      <h2 id="carta-t">La carta</h2>
      <?php if (!$menu['categories']): ?>
        <p class="muted">Estamos preparando la carta. Pregúntanos por los platos del día.</p>
      <?php else: ?>
        <nav class="cats" aria-label="Secciones de la carta">
          <?php foreach ($menu['categories'] as $c): ?>
            <a href="#cat-<?= $c['id'] ?>"><?= h($c['name']) ?></a>
          <?php endforeach; ?>
        </nav>
        <?php foreach ($menu['categories'] as $c): ?>
          <div class="cat" id="cat-<?= $c['id'] ?>">
            <h3><?= h($c['name']) ?></h3>
            <ul class="dishes">
              <?php foreach ($c['dishes'] as $d): ?>
                <li class="dish<?= $d['photo'] ? ' has-photo' : '' ?>">
                  <?php if ($d['photo']): ?>
                    <img src="<?= h($d['photo']) ?>" alt="" loading="lazy" width="72" height="72">
                  <?php endif; ?>
                  <div class="dish-body">
                    <p class="dish-line">
                      <span class="dish-name"><?= h($d['name']) ?></span>
                      <span class="leader" aria-hidden="true"></span>
                      <span class="dish-price"><?= $price($d['price']) ?></span>
                    </p>
                    <?php if ($d['featured']): ?><p class="house">Recomendado de la casa</p><?php endif; ?>
                    <?php if ($d['description']): ?><p class="dish-desc"><?= h($d['description']) ?></p><?php endif; ?>
                    <?php if ($d['allergens']): ?>
                      <p class="alg" aria-label="Alérgenos: <?= h(implode(', ', array_map(fn($k) => $allergens[$k]['name'] ?? $k, $d['allergens']))) ?>">
                        <?php foreach ($d['allergens'] as $k): if (!isset($allergens[$k])) continue; ?>
                          <abbr title="<?= h($allergens[$k]['name']) ?>"><?= h($allergens[$k]['abbr']) ?></abbr>
                        <?php endforeach; ?>
                      </p>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>

        <?php if ($usedAllergens): ?>
          <details class="legend">
            <summary>Qué significan las letras de los alérgenos</summary>
            <ul>
              <?php foreach ($menu['allergens'] as $a): ?>
                <li><abbr><?= h($a['abbr']) ?></abbr> <?= h($a['name']) ?></li>
              <?php endforeach; ?>
            </ul>
            <p>Si tienes alguna alergia o intolerancia, avísanos antes de pedir.</p>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="reservar" id="reservar" aria-labelledby="res-t">
    <div class="wrap res-grid">
      <div class="res-intro">
        <h2 id="res-t">Reservar mesa</h2>
        <p>Elige día, hora y cuántos sois. <?= $s['auto_confirm'] ? 'La reserva queda confirmada al momento.' : 'Te confirmamos la reserva lo antes posible.' ?></p>
        <?php if ($s['phone']): ?>
          <p>Para grupos de más de <?= (int)$s['max_people'] ?> personas, o si prefieres hablar con nosotros, llama al
            <a href="<?= h($telHref($s['phone'])) ?>"><?= h($s['phone']) ?></a>.</p>
        <?php endif; ?>
      </div>

      <form class="res-form" id="res-form" novalidate>
        <div class="row3">
          <label>Día
            <input type="date" name="date" id="f-date" required>
          </label>
          <label>Personas
            <select name="people" id="f-people" required>
              <?php for ($i = 1; $i <= (int)$s['max_people']; $i++): ?>
                <option value="<?= $i ?>" <?= $i === 2 ? 'selected' : '' ?>><?= $i ?></option>
              <?php endfor; ?>
            </select>
          </label>
          <label>Hora
            <select name="time" id="f-time" required disabled>
              <option value="">Elige un día</option>
            </select>
          </label>
        </div>
        <p class="slot-msg" id="slot-msg" role="status"></p>
        <div class="row2">
          <label>Nombre
            <input name="name" required maxlength="80" autocomplete="name">
          </label>
          <label>Teléfono
            <input name="phone" type="tel" required maxlength="30" autocomplete="tel">
          </label>
        </div>
        <label>Email <span class="opt">(para recibir la confirmación)</span>
          <input name="email" type="email" maxlength="120" autocomplete="email">
        </label>
        <label>Algo que debamos saber <span class="opt">(opcional)</span>
          <textarea name="notes" rows="2" maxlength="500" placeholder="Alergias, trona para bebé, terraza…"></textarea>
        </label>
        <label class="hp" aria-hidden="true">No rellenar <input name="website" tabindex="-1" autocomplete="off"></label>
        <label class="check">
          <input type="checkbox" name="privacy" value="1" required>
          <span>He leído la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>. Usaremos tus datos solo para gestionar la reserva.</span>
        </label>
        <button class="btn btn-main" type="submit" id="f-submit">Reservar mesa</button>
        <p class="form-msg" id="form-msg" role="alert"></p>
      </form>
    </div>
  </section>

  <section class="horario" id="horario" aria-labelledby="hor-t">
    <div class="wrap hor-grid">
      <div>
        <h2 id="hor-t">Horario</h2>
        <table class="hours">
          <?php foreach (WEEKDAYS as $n => $day): ?>
            <tr<?= (string)$n === $todayN ? ' class="today"' : '' ?>>
              <th scope="row"><?= $day ?></th>
              <td><?= h(ranges_text($s['hours'][$n] ?? [])) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div>
        <h2>Dónde estamos</h2>
        <?php if ($s['address']): ?>
          <p class="addr"><?= h($s['address']) ?><?= $s['city'] ? '<br>' . h($s['city']) : '' ?></p>
        <?php endif; ?>
        <div class="contact">
          <?php if ($s['address']): ?><a class="btn btn-line" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($mapQuery) ?>" target="_blank" rel="noopener">Cómo llegar</a><?php endif; ?>
          <?php if ($s['phone']): ?><a class="btn btn-line" href="<?= h($telHref($s['phone'])) ?>">Llamar</a><?php endif; ?>
          <?php if ($waNumber): ?><a class="btn btn-line" href="https://wa.me/<?= h($waNumber) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
          <?php if ($s['instagram']): ?><a class="btn btn-line" href="https://instagram.com/<?= h(ltrim($s['instagram'], '@')) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        </div>
        <?php if ($s['address']): ?>
          <div class="map" id="map" data-q="<?= h($mapQuery) ?>">
            <button type="button" class="btn btn-line" id="map-btn">Mostrar el mapa</button>
            <p class="muted small">El mapa lo carga Google Maps, que puede usar cookies.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<footer class="foot">
  <div class="wrap">
    <p><?= h($s['name']) ?><?= $s['address'] ? ' · ' . h($s['address']) : '' ?></p>
    <p><a href="legal.php#aviso">Aviso legal</a> <a href="legal.php#privacidad">Privacidad</a> <a href="legal.php#cookies">Cookies</a></p>
  </div>
</footer>

<?php if ($waNumber): ?>
  <a class="wa-float" href="https://wa.me/<?= h($waNumber) ?>" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
    <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg>
  </a>
<?php endif; ?>

<script>window.RESTAURANT = <?= json_encode(['today' => today(), 'daysAhead' => (int)$s['days_ahead']]) ?>;</script>
<script src="assets/web.js?v=<?= $v ?>" defer></script>
</body>
</html>

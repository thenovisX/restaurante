<?php
/*
 * La carta como un librito de papel: portada, una página por sección y los alérgenos al final.
 * Sin JavaScript se ven todas las páginas una debajo de otra; assets/web.js las convierte en un libro que se hojea.
 * Variables que llegan de index.php: $s (ajustes) y $menu (carta).
 */
// Textos del restaurante traducidos automáticamente; los alérgenos, con el diccionario fijo
$cats = array_map(function ($c) {
    $c['name'] = tr($c['name']);
    $c['dishes'] = array_map(fn($d) => ['name' => tr($d['name']), 'description' => tr($d['description'])] + $d, $c['dishes']);
    return $c;
}, $menu['categories']);
$menu['allergens'] = array_map(fn($a) => ['name' => t($a['name']), 'desc' => t($a['desc'])] + $a, $menu['allergens']);
$alg = [];
foreach ($menu['allergens'] as $a) $alg[$a['key']] = $a;
$euros = function (float $p): string {
    return fmod($p, 1.0) == 0.0 ? number_format($p, 0, ',', '.') : number_format($p, 2, ',', '.');
};
$m = (int)date('n');
$season = t($m === 12 || $m <= 2 ? 'Invierno' : ($m <= 5 ? 'Primavera' : ($m <= 8 ? 'Verano' : 'Otoño'))) . ' ' . date('Y');
$used = [];
foreach ($cats as $c) foreach ($c['dishes'] as $d) foreach ($d['allergens'] as $k) $used[$k] = true;
$total = count($cats) + 1; // páginas después de la portada
?>
<section class="carta" id="carta" aria-labelledby="carta-t">
  <div class="wrap carta-head">
    <h2 id="carta-t" class="title"><?= t('La carta') ?></h2>
    <p class="carta-intro"><?= t('Pásala como si estuvieras en la mesa: con las flechas, deslizando el dedo o tocando la esquina de la página. Los iconos de cada plato son sus alérgenos; en la última página tienes qué significa cada uno.') ?></p>
  </div>

  <?php if (!$cats): ?>
    <p class="wrap muted"><?= t('Estamos preparando la carta. Pregúntanos por los platos del día.') ?></p>
  <?php else: ?>
  <div class="libro" id="libro">
    <nav class="marcas" aria-label="<?= t('Secciones de la carta') ?>">
      <a href="#indice" data-goto="1"><?= t('Índice') ?></a>
      <?php foreach ($cats as $i => $c): ?>
        <a href="#cat-<?= $c['id'] ?>" data-goto="<?= $i + 2 ?>"><?= h($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="#pag-alergenos" data-goto="<?= $total + 1 ?>"><?= t('Alérgenos') ?></a>
    </nav>

    <div class="libro-stage">
      <button class="libro-flecha" type="button" data-dir="-1" aria-label="<?= t('Página anterior') ?>">‹</button>
      <div class="hojas" tabindex="0" aria-roledescription="<?= t('carta') ?>" aria-label="<?= t('Carta. Usa las flechas del teclado para pasar las páginas.') ?>">

        <article class="hoja hoja-portada is-on" data-label="<?= t('Portada') ?>">
          <div class="portada-marco">
            <p class="portada-orn" aria-hidden="true">✦</p>
            <p class="portada-name" style="--n: <?= max(4, max(array_map('mb_strlen', preg_split('/\s+/u', $s['name'])) ?: [4])) ?>"><?= h(mb_strtoupper($s['name'])) ?></p>
            <p class="portada-sub"><?= t('La carta') ?></p>
            <p class="portada-season"><?= h($season) ?></p>
            <button class="portada-abrir" type="button" data-dir="1"><?= t('Abrir la carta') ?></button>
          </div>
        </article>

        <article class="hoja hoja-indice" id="indice" data-label="<?= t('Índice') ?>" aria-label="<?= t('Índice de la carta') ?>">
          <p class="hoja-marca" aria-hidden="true"><?= h(mb_strtoupper($s['name'])) ?></p>
          <h3 class="hoja-titulo"><?= t('Índice') ?></h3>
          <p class="hoja-orn" aria-hidden="true">— ✦ —</p>
          <ol class="indice">
            <?php foreach ($cats as $i => $c): $n = count($c['dishes']); ?>
              <li>
                <a href="#cat-<?= $c['id'] ?>" data-goto="<?= $i + 2 ?>">
                  <span class="indice-nombre"><?= h($c['name']) ?><small><?= $n ?> <?= $n === 1 ? t('plato') : t('platos') ?></small></span>
                  <span class="puntos" aria-hidden="true"></span>
                  <span class="indice-pag"><?= $i + 1 ?></span>
                </a>
              </li>
            <?php endforeach; ?>
            <li class="indice-alg">
              <a href="#pag-alergenos" data-goto="<?= $total + 1 ?>">
                <span class="indice-nombre"><?= t('Alérgenos') ?><small><?= t('qué lleva cada plato') ?></small></span>
                <span class="puntos" aria-hidden="true"></span>
                <span class="indice-pag"><?= $total ?></span>
              </a>
            </li>
          </ol>
          <p class="hoja-nota"><?= t('Toca una sección para ir a su página.') ?></p>
          <button class="esquina" type="button" data-dir="1" aria-label="<?= t('Pasar página') ?>"></button>
        </article>

        <?php foreach ($cats as $i => $c): ?>
          <article class="hoja" data-num="<?= $i + 1 ?>" id="cat-<?= $c['id'] ?>" data-label="<?= h($c['name']) ?>" aria-label="<?= h($c['name']) ?>">
            <p class="hoja-marca" aria-hidden="true"><?= h(mb_strtoupper($s['name'])) ?></p>
            <h3 class="hoja-titulo"><?= h($c['name']) ?></h3>
            <p class="hoja-orn" aria-hidden="true">— ✦ —</p>
            <ul class="platos">
              <?php foreach ($c['dishes'] as $d): ?>
                <li class="plato<?= $d['photo'] ? ' con-foto' : '' ?>">
                  <?php if ($d['photo']): ?><img src="<?= h($d['photo']) ?>" alt="" loading="lazy" width="72" height="72"><?php endif; ?>
                  <div class="plato-txt">
                    <p class="plato-linea">
                      <span class="plato-nombre"><?= h($d['name']) ?></span>
                      <span class="puntos" aria-hidden="true"></span>
                      <span class="plato-precio"><?= $euros($d['price']) ?><small> €</small></span>
                    </p>
                    <?php if ($d['featured']): ?><p class="plato-casa">✦ <?= t('Recomendado de la casa') ?></p><?php endif; ?>
                    <?php if ($d['description']): ?><p class="plato-desc"><?= h($d['description']) ?></p><?php endif; ?>
                    <?php if ($d['allergens']): ?>
                      <p class="plato-alg">
                        <?php foreach ($d['allergens'] as $k): if (isset($alg[$k])) echo al_icon($k, 22, $alg[$k]['name']); endforeach; ?>
                      </p>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
            <footer class="hoja-pie">
              <span><?= $i + 1 ?></span>
              <small><?= t('IVA incluido · Avísanos de cualquier alergia') ?></small>
            </footer>
            <button class="esquina" type="button" data-dir="1" aria-label="<?= t('Pasar página') ?>"></button>
          </article>
        <?php endforeach; ?>

        <article class="hoja hoja-alergenos" data-num="<?= $total ?>" id="pag-alergenos" data-label="<?= t('Alérgenos') ?>" aria-label="<?= t('Alérgenos') ?>">
          <p class="hoja-marca" aria-hidden="true"><?= h(mb_strtoupper($s['name'])) ?></p>
          <h3 class="hoja-titulo"><?= t('Alérgenos') ?></h3>
          <p class="hoja-orn" aria-hidden="true">— ✦ —</p>
          <ul class="mini-alg">
            <?php foreach ($menu['allergens'] as $a): ?>
              <li class="<?= isset($used[$a['key']]) ? '' : 'sin-uso' ?>"><?= al_icon($a['key'], 34) ?><div><strong><?= h($a['name']) ?></strong><small><?= h($a['desc']) ?></small></div></li>
            <?php endforeach; ?>
          </ul>
          <p class="hoja-nota"><?= t('Si tienes alguna alergia o intolerancia, dínoslo antes de pedir: te diremos qué platos puedes tomar o cómo adaptarlos.') ?></p>
          <footer class="hoja-pie"><span><?= $total ?></span><small><?= t('Información según el Reglamento UE 1169/2011') ?></small></footer>
        </article>
      </div>
      <button class="libro-flecha" type="button" data-dir="1" aria-label="<?= t('Página siguiente') ?>">›</button>
    </div>
    <p class="libro-pagina" id="libro-pagina" aria-live="polite"><?= t('Portada') ?></p>
  </div>
  <?php endif; ?>

</section>

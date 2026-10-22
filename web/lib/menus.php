<?php
/*
 * Los menús de precio cerrado (menú del día, degustación…), cada uno como una hoja de papel, delante de la carta.
 * Variables que llegan: $s (ajustes) y $menus = menus_public() (ya traducidos; solo se incluye si hay alguno).
 */
?>
<section class="mdia" id="menus" aria-label="<?= t('Menús') ?>">
  <div class="wrap mdia-in<?= count($menus) > 1 ? ' is-varios' : '' ?>">
    <?php if (count($menus) > 1): ?>
      <nav class="mdia-nav" aria-label="<?= t('Menús') ?>">
        <?php foreach ($menus as $m): ?><a href="#menu-<?= h($m['id']) ?>"><?= h($m['title']) ?></a><?php endforeach; ?>
        <?php if (carta_on()): ?><a href="#carta"><?= t('La carta') ?></a><?php endif; ?>
      </nav>
    <?php endif; ?>
    <?php foreach ($menus as $m): ?>
    <article class="mdia-hoja" id="menu-<?= h($m['id']) ?>" aria-labelledby="mdia-t-<?= h($m['id']) ?>">
      <p class="hoja-marca" aria-hidden="true"><?= h(mb_strtoupper($s['name'])) ?></p>
      <h2 class="mdia-t" id="mdia-t-<?= h($m['id']) ?>"><?= h($m['title']) ?></h2>
      <p class="mdia-precio"><?= euros($m['price']) ?><small> €</small></p>
      <p class="mdia-dias">
        <?= h(mb_strtoupper(mb_substr($m['days_text'], 0, 1)) . mb_substr($m['days_text'], 1)) ?>
        <?php if (!$m['every_day']): ?><span class="mdia-hoy<?= $m['today'] ? ' is-on' : '' ?>"><?= $m['today'] ? t('Hoy sí') : t('Hoy no') ?></span><?php endif; ?>
      </p>
      <p class="hoja-orn" aria-hidden="true">— ✦ —</p>
      <?php foreach ($m['courses'] as $c): ?>
        <div class="mdia-curso">
          <h3><?= h($c['name']) ?></h3>
          <?php if (count($c['dishes']) > 1): ?><p class="mdia-elige"><?= t('a elegir') ?></p><?php endif; ?>
          <ul>
            <?php foreach ($c['dishes'] as $d): ?>
              <li>
                <span class="mdia-plato"><?= h($d['name']) ?><?php if ($d['extra'] > 0): ?> <span class="mdia-sup" title="<?= t('Suplemento') ?>">+<?= euros($d['extra']) ?> €</span><?php endif; ?></span>
                <?php if ($d['desc'] !== ''): ?><span class="mdia-desc"><?= h($d['desc']) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
      <?php if ($m['note'] !== ''): ?><p class="mdia-nota"><?= nl2br(h($m['note'])) ?></p><?php endif; ?>
      <p class="mdia-alg"><?= t('Pregúntanos por los alérgenos de los platos del menú.') ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</section>

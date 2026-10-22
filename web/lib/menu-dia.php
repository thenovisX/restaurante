<?php
/*
 * El menú del día como una hoja de papel aparte, delante de la carta.
 * Variable que llega: $dm = daily_menu_public() (ya traducido). Si es null, no se incluye.
 */
?>
<section class="mdia" id="menu-dia" aria-labelledby="mdia-t">
  <div class="wrap mdia-in">
    <article class="mdia-hoja">
      <p class="hoja-marca" aria-hidden="true"><?= h(mb_strtoupper($s['name'])) ?></p>
      <h2 class="mdia-t" id="mdia-t"><?= h($dm['title']) ?></h2>
      <p class="mdia-precio"><?= euros($dm['price']) ?><small> €</small></p>
      <p class="mdia-dias">
        <?= h(mb_strtoupper(mb_substr($dm['days_text'], 0, 1)) . mb_substr($dm['days_text'], 1)) ?>
        <span class="mdia-hoy<?= $dm['today'] ? ' is-on' : '' ?>"><?= $dm['today'] ? t('Hoy hay menú') : t('Hoy no hay menú') ?></span>
      </p>
      <p class="hoja-orn" aria-hidden="true">— ✦ —</p>
      <?php foreach ($dm['courses'] as $c): ?>
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
      <?php if ($dm['note'] !== ''): ?><p class="mdia-nota"><?= nl2br(h($dm['note'])) ?></p><?php endif; ?>
      <p class="mdia-alg"><?= t('Pregúntanos por los alérgenos de los platos del menú.') ?></p>
    </article>
  </div>
</section>

<?php
/* Ventana de reserva en 3 pasos. La controla assets/web.js. */
$s = settings();
$c = current_customer();
?>
<dialog class="bk" id="bk" aria-labelledby="bk-title">
  <form class="bk-in" id="bk-form" method="dialog" novalidate>
    <header class="bk-head">
      <div>
        <p class="bk-step" id="bk-step">Paso 1 de 3</p>
        <h2 id="bk-title">Reservar mesa</h2>
        <p class="bk-sum" id="bk-sum" aria-live="polite"></p>
      </div>
      <button class="bk-close" type="button" data-close aria-label="Cerrar">×</button>
    </header>

    <section class="bk-pane" data-pane="1">
      <fieldset>
        <legend>¿Cuántos sois?</legend>
        <div class="chips" id="bk-people">
          <?php for ($i = 1; $i <= min(8, (int)$s['max_people']); $i++): ?>
            <label><input type="radio" name="people" value="<?= $i ?>" <?= $i === 2 ? 'checked' : '' ?>><span><?= $i ?></span></label>
          <?php endfor; ?>
          <?php if ((int)$s['max_people'] > 8): ?>
            <label class="chip-more"><select name="people_more" aria-label="Más personas"><option value="">Más</option><?php for ($i = 9; $i <= (int)$s['max_people']; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
          <?php endif; ?>
        </div>
      </fieldset>
      <fieldset>
        <legend>¿Qué día?</legend>
        <div class="days" id="bk-days" role="radiogroup" aria-label="Día"></div>
        <label class="bk-other">Otra fecha <input type="date" id="bk-date"></label>
      </fieldset>
      <div class="bk-nav"><button class="btn btn-rubor" type="button" data-next="2">Ver horas libres</button></div>
    </section>

    <section class="bk-pane" data-pane="2" hidden>
      <div id="bk-slots" class="slots" aria-live="polite"></div>
      <div class="bk-nav"><button class="btn btn-ghost" type="button" data-next="1">Atrás</button><button class="btn btn-rubor" type="button" data-next="3" id="bk-to3" disabled>Continuar</button></div>
    </section>

    <section class="bk-pane" data-pane="3" hidden>
      <?php if ($c): ?>
        <p class="bk-who">Reservas como <strong><?= h($c['name']) ?></strong> · <?= h($c['phone'] ?: $c['email']) ?>. <button type="button" class="link" id="bk-edit-who">Usar otros datos</button></p>
      <?php else: ?>
        <p class="bk-who">¿Tienes cuenta? <a href="cuenta.php">Entra</a> y no tendrás que escribir tus datos.</p>
      <?php endif; ?>
      <div class="bk-fields" id="bk-fields" <?= $c ? 'hidden' : '' ?>>
        <label>Nombre <input name="name" maxlength="80" autocomplete="name" value="<?= h($c['name'] ?? '') ?>"></label>
        <label>Teléfono <input name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= h($c['phone'] ?? '') ?>"></label>
        <label>Email <span>para recibir la confirmación</span><input name="email" type="email" maxlength="120" autocomplete="email" value="<?= h($c['email'] ?? '') ?>"></label>
      </div>
      <label>Algo que debamos saber <span>opcional</span><textarea name="notes" rows="2" maxlength="500" placeholder="Alergias, celebración, trona…"></textarea></label>
      <label class="hp" aria-hidden="true">No rellenar <input name="website" tabindex="-1" autocomplete="off"></label>
      <div class="bk-policy">
        <p>Cancelación gratis hasta <?= (int)$s['cancel_hours'] ?> h antes. Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos.</p>
        <label class="check"><input type="checkbox" name="privacy" value="1"><span>Acepto las <a href="./#politicas" target="_blank">políticas de reserva</a> y la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>.</span></label>
      </div>
      <p class="bk-err" id="bk-err" role="alert"></p>
      <div class="bk-nav"><button class="btn btn-ghost" type="button" data-next="2">Atrás</button><button class="btn btn-rubor" type="button" id="bk-send">Confirmar reserva</button></div>
    </section>

    <section class="bk-pane bk-done" data-pane="4" hidden>
      <p class="bk-done-mark" aria-hidden="true">✦</p>
      <h3 id="bk-done-t"></h3>
      <p id="bk-done-msg"></p>
      <p class="bk-done-more" id="bk-done-more"></p>
      <div class="bk-nav"><button class="btn btn-rubor" type="button" data-close>Hecho</button></div>
    </section>
  </form>
</dialog>

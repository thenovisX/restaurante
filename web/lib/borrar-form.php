<?php /* Formulario «Borrar mis datos» (lo controla assets/web.js). */ ?>
<form class="erase" data-erase novalidate>
  <label for="erase-c"><?= t('Tu email o tu teléfono') ?></label>
  <div class="erase-row">
    <input id="erase-c" name="contact" type="text" inputmode="email" autocomplete="email" maxlength="120" placeholder="<?= t('ejemplo@correo.com o 600 123 456') ?>" required>
    <button class="btn btn-rubor" type="submit"><?= t('Borrar mis datos') ?></button>
  </div>
  <label class="hp" aria-hidden="true"><?= t('No rellenar') ?> <input name="website" tabindex="-1" autocomplete="off"></label>
  <p class="erase-help"><?= t('Borraremos tu cuenta, tus reservas y el registro de actividad. Las reservas que aún no hayan pasado se cancelarán. Para que nadie pueda borrar los datos de otra persona, te enviaremos un enlace para confirmarlo: por email si escribes tu email, o por WhatsApp si escribes tu teléfono.') ?></p>
  <p class="erase-msg" role="status" aria-live="polite"></p>
</form>

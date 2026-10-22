<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }

$s = settings();
// Enlace de activación del email (doble opt-in)
if (isset($_GET['activar'], $_GET['c'])) {
    $ok = customer_activate((int)$_GET['c'], (string)$_GET['activar']);
    header('Location: cuenta.php?' . ($ok ? 'activada=1' : 'enlace=caducado'));
    exit;
}
$c = current_customer();
header('Cache-Control: no-store');
page_start(($c ? t('Mi cuenta') : t('Entrar')) . ' · ' . $s['name'], '', 'page-account');
site_header($c);
?>
<main id="main" class="acc">
  <div class="wrap">
  <?php if (!$c): $reg = isset($_GET['registro']); ?>
    <div class="acc-auth">
      <div>
        <h1 class="title"><?= t('Tu cuenta') ?></h1>
        <?php if (isset($_GET['enlace'])): ?><p class="acc-aviso"><?= t('El enlace de activación no es válido o ha caducado. Vuelve a crear la cuenta o pide otro enlace.') ?></p><?php endif; ?>
        <p class="acc-lead"><?= t('Reserva sin escribir tus datos cada vez, mira tus próximas reservas, confírmalas y cancélalas tú mismo cuando quieras, sin coste.') ?></p>
      </div>
      <div class="acc-box">
        <div class="tabs" role="tablist">
          <button type="button" role="tab" id="t-login" aria-controls="f-login" aria-selected="<?= $reg ? 'false' : 'true' ?>"><?= t('Entrar') ?></button>
          <button type="button" role="tab" id="t-reg" aria-controls="f-reg" aria-selected="<?= $reg ? 'true' : 'false' ?>"><?= t('Crear cuenta') ?></button>
        </div>
        <form class="form" id="f-login" role="tabpanel" aria-labelledby="t-login" data-api="c_login" <?= $reg ? 'hidden' : '' ?> novalidate>
          <label>Email <input name="email" type="email" autocomplete="email" required></label>
          <label><?= t('Contraseña') ?> <input name="password" type="password" autocomplete="current-password" required></label>
          <p class="form-err" role="alert"></p>
          <button class="btn btn-rubor" type="submit"><?= t('Entrar') ?></button>
          <p class="form-help"><?= t('¿Has olvidado la contraseña? Escríbenos desde el email de tu cuenta y te ayudamos.') ?><?= $s['email'] ? ' <a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '' ?></p>
        </form>
        <form class="form" id="f-reg" role="tabpanel" aria-labelledby="t-reg" data-api="c_register" <?= $reg ? '' : 'hidden' ?> novalidate>
          <label><?= t('Nombre') ?> <input name="name" autocomplete="name" maxlength="80" required></label>
          <label>Email <input name="email" type="email" autocomplete="email" maxlength="120" required></label>
          <label><?= t('Teléfono') ?> <span><?= t('para avisarte si hay algún cambio') ?></span><input name="phone" type="tel" autocomplete="tel" maxlength="30"></label>
          <label><?= t('Contraseña') ?> <span><?= t('mínimo 8 caracteres') ?></span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
          <label class="hp" aria-hidden="true"><?= t('No rellenar') ?> <input name="website" tabindex="-1" autocomplete="off"></label>
          <label class="check"><input type="checkbox" name="privacy" value="1"><span><?= t('Acepto las <a href="legal.php#condiciones" target="_blank">condiciones de uso</a> y he leído la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>.') ?></span></label>
          <p class="form-err" role="alert"></p>
          <button class="btn btn-rubor" type="submit"><?= t('Crear cuenta') ?></button>
          <p class="form-help"><?= t('Te enviaremos un email para activar la cuenta. Las reservas que hayas hecho con este mismo email aparecerán en tu cuenta al activarla; las que hiciste sin email o con otro no se pueden añadir. No te enviaremos publicidad.') ?></p>
          <p class="form-help small"><?= t('<strong>Protección de datos.</strong> Responsable: %s. Finalidad: gestionar tu cuenta y tus reservas. No cedemos tus datos a nadie. Puedes descargarlos o borrar la cuenta desde aquí mismo, y ejercer el resto de derechos escribiendo a %s.', h($s['owner_name'] ?: $s['name']), h($s['email'] ?: t('nuestro contacto'))) ?> <a href="legal.php#privacidad" target="_blank"><?= t('Más información') ?></a>.</p>
        </form>
        <div class="acc-pendiente" id="acc-pendiente" hidden>
          <h2><?= t('Revisa tu email') ?></h2>
          <p><?= t('Te hemos enviado un enlace a %s para activar tu cuenta. Caduca en 48 horas. Si no lo ves, mira en la carpeta de spam.', '<strong id="acc-pend-email"></strong>') ?></p>
          <button class="btn btn-ghost btn-sm" type="button" id="acc-reenviar"><?= t('Enviar el email otra vez') ?></button>
        </div>
      </div>
    </div>
  <?php else: $res = customer_reservations($c); ?>
    <div class="acc-head">
      <h1 class="title"><?= h(t('Hola, %s', explode(' ', $c['name'])[0])) ?></h1>
      <?php if (isset($_GET['activada'])): ?><p class="acc-aviso acc-ok"><?= t('Cuenta activada. Ya puedes reservar sin escribir tus datos.') ?></p><?php endif; ?>
      <button class="btn btn-rubor" type="button" data-book><?= t('Nueva reserva') ?></button>
    </div>

    <section class="acc-sec" aria-labelledby="up-t">
      <h2 id="up-t"><?= t('Próximas reservas') ?></h2>
      <?php if (!$res['upcoming']): ?>
        <p class="muted"><?= t('No tienes reservas pendientes.') ?> <button type="button" class="link" data-book><?= t('Reserva una mesa') ?></button>.</p>
      <?php endif; ?>
      <ul class="acc-list">
        <?php foreach ($res['upcoming'] as $r): ?>
          <li class="acc-res">
            <div>
              <p class="acc-when"><?= h(ucfirst($r['when'])) ?></p>
              <p class="acc-meta"><?= $r['people'] ?> <?= $r['people'] === 1 ? t('persona') : t('personas') ?> · <span class="st st-<?= $r['status'] ?>"><?= t(['pendiente' => 'Petición enviada: esperando al restaurante', 'esperando' => 'Falta que la confirmes', 'confirmada' => 'Confirmada'][$r['status']] ?? $r['status']) ?></span></p>
              <?php if ($r['notes']): ?><p class="acc-notes"><?= h($r['notes']) ?></p><?php endif; ?>
            </div>
            <div class="acc-res-btns">
              <?php if ($r['status'] === 'esperando'): ?>
                <button class="btn btn-rubor btn-sm" type="button" data-confirm-res="<?= $r['id'] ?>"><?= t('Confirmar') ?></button>
              <?php endif; ?>
              <?php if ($r['can_cancel']): ?>
                <button class="btn btn-ghost btn-sm" type="button" data-cancel="<?= $r['id'] ?>" data-when="<?= h($r['when']) ?>"><?= t('Cancelar') ?></button>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="form-help"><?= t('Aquí salen las reservas hechas con tu cuenta o con el email %s. Las que hiciste sin email o con otro email no aparecen.', h($c['email'])) ?></p>
    </section>

    <?php if ($res['past']): ?>
      <section class="acc-sec" aria-labelledby="past-t">
        <h2 id="past-t"><?= t('Historial') ?></h2>
        <ul class="acc-list acc-past">
          <?php foreach (array_slice($res['past'], 0, 20) as $r): ?>
            <li class="acc-res"><div><p class="acc-when"><?= h(ucfirst($r['when'])) ?></p><p class="acc-meta"><?= $r['people'] ?> <?= t('pers.') ?> · <?= t(['pendiente' => 'Pasada', 'esperando' => 'Pasada', 'confirmada' => 'Pasada', 'rechazada' => 'No pudimos atenderla', 'cancelada' => 'Cancelada'][$r['status']] ?? 'Pasada') ?></p></div></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="acc-sec" aria-labelledby="me-t">
      <h2 id="me-t"><?= t('Mis datos') ?></h2>
      <form class="form acc-form" data-api="c_profile" novalidate>
        <div class="row2">
          <label><?= t('Nombre') ?> <input name="name" value="<?= h($c['name']) ?>" autocomplete="name" required></label>
          <label><?= t('Teléfono') ?> <input name="phone" type="tel" value="<?= h($c['phone']) ?>" autocomplete="tel"></label>
        </div>
        <label>Email <input name="email" type="email" value="<?= h($c['email']) ?>" autocomplete="email" required></label>
        <p class="form-help"><?= t('Para cambiar el email, escribe también tu contraseña actual en «Cambiar contraseña».') ?></p>
        <details class="acc-pass">
          <summary><?= t('Cambiar contraseña') ?></summary>
          <div class="row2">
            <label><?= t('Contraseña actual') ?> <input name="password" type="password" autocomplete="current-password"></label>
            <label><?= t('Nueva contraseña') ?> <input name="new_password" type="password" autocomplete="new-password" minlength="8"></label>
          </div>
        </details>
        <p class="form-err" role="alert"></p>
        <button class="btn btn-hueso" type="submit"><?= t('Guardar cambios') ?></button>
      </form>
    </section>

    <section class="acc-sec acc-danger" aria-labelledby="out-t">
      <h2 id="out-t"><?= t('Tus datos y tu sesión') ?></h2>
      <div class="acc-actions">
        <a class="btn btn-ghost" href="api/?r=c_export" download="mis-datos.json"><?= t('Descargar mis datos') ?></a>
        <button class="btn btn-ghost" type="button" id="logout"><?= t('Cerrar sesión') ?></button>
        <details>
          <summary><?= t('Borrar mi cuenta') ?></summary>
          <form class="form" data-api="c_delete" data-confirm="<?= h(t('¿Seguro que quieres borrar tu cuenta? No se puede deshacer.')) ?>" novalidate>
            <p class="form-help"><?= t('Borramos tus datos de acceso. Tus reservas pendientes siguen en pie: si quieres anularlas, cancélalas antes.') ?></p>
            <label><?= t('Escribe tu contraseña para confirmar') ?> <input name="password" type="password" autocomplete="current-password"></label>
            <p class="form-err" role="alert"></p>
            <button class="btn btn-danger" type="submit"><?= t('Borrar mi cuenta') ?></button>
          </form>
        </details>
      </div>
    </section>
  <?php endif; ?>
  </div>
</main>
<?php
site_footer();
include __DIR__ . '/lib/reserva-dialogo.php';
page_end(['customer' => customer_out($c)]);

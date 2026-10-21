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
page_start(($c ? 'Mi cuenta' : 'Entrar') . ' · ' . $s['name'], '', 'page-account');
site_header($c);
?>
<main id="main" class="acc">
  <div class="wrap">
  <?php if (!$c): $reg = isset($_GET['registro']); ?>
    <div class="acc-auth">
      <div>
        <h1 class="title">Tu cuenta</h1>
        <?php if (isset($_GET['enlace'])): ?><p class="acc-aviso">El enlace de activación no es válido o ha caducado. Vuelve a crear la cuenta o pide otro enlace.</p><?php endif; ?>
        <p class="acc-lead">Reserva sin escribir tus datos cada vez, mira tus próximas reservas y cancélalas tú mismo hasta <?= (int)$s['cancel_hours'] ?> horas antes.</p>
      </div>
      <div class="acc-box">
        <div class="tabs" role="tablist">
          <button type="button" role="tab" id="t-login" aria-controls="f-login" aria-selected="<?= $reg ? 'false' : 'true' ?>">Entrar</button>
          <button type="button" role="tab" id="t-reg" aria-controls="f-reg" aria-selected="<?= $reg ? 'true' : 'false' ?>">Crear cuenta</button>
        </div>
        <form class="form" id="f-login" role="tabpanel" aria-labelledby="t-login" data-api="c_login" <?= $reg ? 'hidden' : '' ?> novalidate>
          <label>Email <input name="email" type="email" autocomplete="email" required></label>
          <label>Contraseña <input name="password" type="password" autocomplete="current-password" required></label>
          <p class="form-err" role="alert"></p>
          <button class="btn btn-rubor" type="submit">Entrar</button>
          <p class="form-help">¿Has olvidado la contraseña? Escríbenos<?= $s['email'] ? ' a <a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '' ?> desde el email de tu cuenta y te ayudamos.</p>
        </form>
        <form class="form" id="f-reg" role="tabpanel" aria-labelledby="t-reg" data-api="c_register" <?= $reg ? '' : 'hidden' ?> novalidate>
          <label>Nombre <input name="name" autocomplete="name" maxlength="80" required></label>
          <label>Email <input name="email" type="email" autocomplete="email" maxlength="120" required></label>
          <label>Teléfono <span>para avisarte si hay algún cambio</span><input name="phone" type="tel" autocomplete="tel" maxlength="30"></label>
          <label>Contraseña <span>mínimo 8 caracteres</span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
          <label class="hp" aria-hidden="true">No rellenar <input name="website" tabindex="-1" autocomplete="off"></label>
          <label class="check"><input type="checkbox" name="privacy" value="1"><span>He leído y acepto las <a href="legal.php#condiciones" target="_blank">condiciones de uso</a> y la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>.</span></label>
          <p class="form-err" role="alert"></p>
          <button class="btn btn-rubor" type="submit">Crear cuenta</button>
          <p class="form-help">Te enviaremos un email para activar la cuenta. Si ya habías reservado con este email, esas reservas aparecerán en tu cuenta al activarla. No te enviaremos publicidad.</p>
        </form>
        <div class="acc-pendiente" id="acc-pendiente" hidden>
          <h2>Revisa tu email</h2>
          <p>Te hemos enviado un enlace a <strong id="acc-pend-email"></strong> para activar tu cuenta. Caduca en 48 horas. Si no lo ves, mira en la carpeta de spam.</p>
          <button class="btn btn-ghost btn-sm" type="button" id="acc-reenviar">Enviar el email otra vez</button>
        </div>
      </div>
    </div>
  <?php else: $res = customer_reservations($c); ?>
    <div class="acc-head">
      <h1 class="title">Hola, <?= h(explode(' ', $c['name'])[0]) ?></h1>
      <?php if (isset($_GET['activada'])): ?><p class="acc-aviso acc-ok">Cuenta activada. Ya puedes reservar sin escribir tus datos.</p><?php endif; ?>
      <button class="btn btn-rubor" type="button" data-book>Nueva reserva</button>
    </div>

    <section class="acc-sec" aria-labelledby="up-t">
      <h2 id="up-t">Próximas reservas</h2>
      <?php if (!$res['upcoming']): ?>
        <p class="muted">No tienes reservas pendientes. <button type="button" class="link" data-book>Reserva una mesa</button>.</p>
      <?php endif; ?>
      <ul class="acc-list">
        <?php foreach ($res['upcoming'] as $r): ?>
          <li class="acc-res">
            <div>
              <p class="acc-when"><?= h(ucfirst($r['when'])) ?></p>
              <p class="acc-meta"><?= $r['people'] ?> <?= $r['people'] === 1 ? 'persona' : 'personas' ?> · <span class="st st-<?= $r['status'] ?>"><?= $r['status'] === 'pendiente' ? 'Pendiente de confirmar' : 'Confirmada' ?></span></p>
              <?php if ($r['notes']): ?><p class="acc-notes"><?= h($r['notes']) ?></p><?php endif; ?>
            </div>
            <?php if ($r['can_cancel']): ?>
              <button class="btn btn-ghost btn-sm" type="button" data-cancel="<?= $r['id'] ?>" data-when="<?= h($r['when']) ?>">Cancelar</button>
            <?php else: ?>
              <p class="acc-nocancel">Faltan menos de <?= $res['cancel_hours'] ?> h: para cancelar, llámanos<?= $s['phone'] ? ' al <a href="' . h(tel_href($s['phone'])) . '">' . h($s['phone']) . '</a>' : '' ?>.</p>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if ($res['past']): ?>
      <section class="acc-sec" aria-labelledby="past-t">
        <h2 id="past-t">Historial</h2>
        <ul class="acc-list acc-past">
          <?php foreach (array_slice($res['past'], 0, 20) as $r): ?>
            <li class="acc-res"><div><p class="acc-when"><?= h(ucfirst($r['when'])) ?></p><p class="acc-meta"><?= $r['people'] ?> pers. · <?= ['pendiente' => 'Pasada', 'confirmada' => 'Pasada', 'rechazada' => 'No pudimos atenderla', 'cancelada' => 'Cancelada'][$r['status']] ?></p></div></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="acc-sec" aria-labelledby="me-t">
      <h2 id="me-t">Mis datos</h2>
      <form class="form acc-form" data-api="c_profile" novalidate>
        <div class="row2">
          <label>Nombre <input name="name" value="<?= h($c['name']) ?>" autocomplete="name" required></label>
          <label>Teléfono <input name="phone" type="tel" value="<?= h($c['phone']) ?>" autocomplete="tel"></label>
        </div>
        <label>Email <input name="email" type="email" value="<?= h($c['email']) ?>" autocomplete="email" required></label>
        <p class="form-help">Para cambiar el email, escribe también tu contraseña actual en «Cambiar contraseña».</p>
        <details class="acc-pass">
          <summary>Cambiar contraseña</summary>
          <div class="row2">
            <label>Contraseña actual <input name="password" type="password" autocomplete="current-password"></label>
            <label>Nueva contraseña <input name="new_password" type="password" autocomplete="new-password" minlength="8"></label>
          </div>
        </details>
        <p class="form-err" role="alert"></p>
        <button class="btn btn-hueso" type="submit">Guardar cambios</button>
      </form>
    </section>

    <section class="acc-sec acc-danger" aria-labelledby="out-t">
      <h2 id="out-t">Tus datos y tu sesión</h2>
      <div class="acc-actions">
        <a class="btn btn-ghost" href="api/?r=c_export" download="mis-datos.json">Descargar mis datos</a>
        <button class="btn btn-ghost" type="button" id="logout">Cerrar sesión</button>
        <details>
          <summary>Borrar mi cuenta</summary>
          <form class="form" data-api="c_delete" data-confirm="¿Seguro que quieres borrar tu cuenta? No se puede deshacer." novalidate>
            <p class="form-help">Borramos tus datos de acceso. Tus reservas pendientes siguen en pie: si quieres anularlas, cancélalas antes.</p>
            <label>Escribe tu contraseña para confirmar <input name="password" type="password" autocomplete="current-password"></label>
            <p class="form-err" role="alert"></p>
            <button class="btn btn-danger" type="submit">Borrar mi cuenta</button>
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

<?php
declare(strict_types=1);
/*
 * Borrar todos los datos de un cliente. Sin enlace: el formulario para pedirlo. Con enlace (?t=…): lo que se va a
 * borrar y el botón para confirmarlo. Abrir el enlace no borra nada (WhatsApp y el correo abren los enlaces solos
 * para la vista previa): solo se borra al pulsar el botón.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$s = settings();
$token = (string)($_POST['t'] ?? $_GET['t'] ?? '');
$done = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    same_origin_only();
    $done = erase_confirm($token);
    if ($done === null) $token = 'x'; // enlace ya usado o caducado
}
$req = $done === null && $token !== '' ? erase_by_token($token) : null;
$sum = $req ? erase_summary($req) : null;

page_start(t('Borrar mis datos') . ' · ' . $s['name'], '', 'page-confirmar');
site_header(current_customer());
?>
<main id="main" class="confirmar">
  <div class="wrap">
  <?php if ($done !== null): ?>
    <p class="cf-mark" aria-hidden="true">✦</p>
    <h1 class="cf-title"><?= t('Datos borrados') ?></h1>
    <p class="cf-lead"><?= t('Hemos borrado todo lo que guardábamos con ese contacto.') ?><?= $done['cancelled'] ? ' ' . t('Las reservas que tenías pendientes se han cancelado.') : '' ?></p>
    <div class="cf-btns"><a class="btn btn-ghost" href="./"><?= t('Volver al inicio') ?></a></div>
  <?php elseif ($req): ?>
    <p class="cf-mark" aria-hidden="true">✦</p>
    <h1 class="cf-title"><?= t('¿Borrar todos tus datos?') ?></h1>
    <p class="cf-lead"><?= h(t('Con %s guardamos:', $req['contact'])) ?></p>
    <ul class="erase-sum">
      <li><?= $sum['accounts'] ? t('Tu cuenta de cliente') : t('Ninguna cuenta de cliente') ?></li>
      <li><?= $sum['reservations'] === 1 ? t('1 reserva') : t('%d reservas', $sum['reservations']) ?><?= $sum['future'] ? ' (' . ($sum['future'] === 1 ? t('1 aún no ha pasado y se cancelará') : t('%d aún no han pasado y se cancelarán', $sum['future'])) . ')' : '' ?></li>
    </ul>
    <p class="cf-help"><?= t('No se puede deshacer. De las reservas solo quedarán el día, la hora y el número de personas, sin nada que te identifique.') ?></p>
    <form class="cf-btns" method="post" action="borrar-datos.php">
      <input type="hidden" name="t" value="<?= h($token) ?>">
      <button class="btn btn-rubor" type="submit"><?= t('Borrar mis datos') ?></button>
      <a class="btn btn-ghost" href="./"><?= t('No, volver') ?></a>
    </form>
  <?php else: ?>
    <?php if ($token !== ''): ?>
      <p class="cf-mark is-off" aria-hidden="true">—</p>
      <h1 class="cf-title"><?= t('Este enlace ya no funciona') ?></h1>
      <p class="cf-lead"><?= t('Puede que ya lo hayas usado o que haya caducado. Puedes pedir otro aquí abajo.') ?></p>
    <?php else: ?>
      <h1 class="cf-title"><?= t('Borrar mis datos') ?></h1>
      <p class="cf-lead"><?= t('Escribe el email o el teléfono con el que reservaste o te registraste.') ?></p>
    <?php endif; ?>
    <?php include __DIR__ . '/lib/borrar-form.php'; ?>
  <?php endif; ?>
  </div>
</main>
<?php
site_footer();
include __DIR__ . '/lib/reserva-dialogo.php';
page_end();

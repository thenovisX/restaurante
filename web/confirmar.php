<?php
declare(strict_types=1);
/* Página a la que llega el cliente desde el enlace del WhatsApp para confirmar su reserva. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }

$s = settings();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
[$state, $r] = confirm_by_customer((int)($_GET['r'] ?? 0), (string)($_GET['t'] ?? ''));
$when = $r ? ucfirst(fmt_date_es($r['date'])) . ' a las ' . $r['time'] : '';
$tel = $s['phone'] ? ' Si necesitas algo, llámanos al <a href="' . h(tel_href($s['phone'])) . '">' . h($s['phone']) . '</a>.' : '';

$views = [
    'confirmada' => ['¡Reserva confirmada!', 'Te esperamos el ' . h(lcfirst($when)) . ' (' . ($r['people'] ?? 0) . ' ' . (($r['people'] ?? 0) === 1 ? 'persona' : 'personas') . ').' . $tel, true],
    'ya'         => ['Tu reserva ya estaba confirmada', 'Te esperamos el ' . h(lcfirst($when)) . '.' . $tel, true],
    'cancelada'  => ['Esta reserva está cancelada', 'Si quieres venir, haz una reserva nueva.', false],
    'rechazada'  => ['No pudimos atender esta reserva', 'Prueba con otro día u otra hora.' . $tel, false],
    'pasada'     => ['Este enlace ha caducado', 'La fecha de la reserva ya ha pasado.', false],
    'invalido'   => ['Este enlace no es válido', 'Puede que esté incompleto. Ábrelo de nuevo desde el mensaje de WhatsApp o haz una reserva nueva.' . $tel, false],
];
[$title, $text, $ok] = $views[$state];

page_start($title . ' · ' . $s['name'], '', 'page-legal');
site_header(current_customer());
?>
<main id="main" class="confirmar">
  <div class="wrap">
    <p class="confirmar-mark<?= $ok ? ' is-ok' : '' ?>" aria-hidden="true"><?= $ok ? '✦' : '—' ?></p>
    <h1 class="title"><?= h($title) ?></h1>
    <p class="confirmar-txt"><?= $text ?></p>
    <?php if ($ok && $s['address']): ?>
      <p class="confirmar-txt"><?= h($s['address']) ?><?= $s['city'] ? ', ' . h($s['city']) : '' ?> · <a href="<?= h(maps_href()) ?>" target="_blank" rel="noopener">Cómo llegar</a></p>
    <?php endif; ?>
    <div class="confirmar-btns">
      <?php if ($ok): ?>
        <a class="btn btn-rubor" href="./#carta">Ver la carta</a>
        <a class="btn btn-ghost" href="cuenta.php">Mis reservas</a>
      <?php else: ?>
        <a class="btn btn-rubor" href="./#reservar" data-book>Hacer una reserva</a>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php
site_footer();
include __DIR__ . '/lib/reserva-dialogo.php';
page_end();

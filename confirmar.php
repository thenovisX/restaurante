<?php
declare(strict_types=1);
/* Página a la que llega el cliente desde el enlace del WhatsApp para confirmar su reserva. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }

$s = settings();
$id = (int)($_GET['r'] ?? 0);
$token = (string)($_GET['t'] ?? '');

// Archivo para añadir la reserva al calendario del móvil (.ics)
if (isset($_GET['ics'])) {
    $row = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
    if (!$row || !preg_match('/^[a-f0-9]{32}$/', $token) || !hash_equals(dec($row['verify_token']), $token) || $row['status'] !== 'confirmada') {
        http_response_code(404);
        exit(t('Reserva no encontrada'));
    }
    $r = res_out($row);
    $start = strtotime($r['date'] . ' ' . $r['time']);
    $fmt = fn(int $t) => gmdate('Ymd\THis\Z', $t);
    $esc = fn(string $v) => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $v);
    $loc = trim($s['address'] . ', ' . $s['city'], ', ');
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="reserva-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($s['name'])) . '.ics"');
    echo implode("\r\n", [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $esc($s['name']) . '//Reservas//ES', 'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:reserva-' . $r['id'] . '@' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'web'),
        'DTSTAMP:' . $fmt(time()),
        'DTSTART:' . $fmt($start),
        'DTEND:' . $fmt($start + 2 * 3600),
        'SUMMARY:' . $esc(t('Reserva en %s', $s['name']) . ' (' . $r['people'] . ' ' . t('pers.') . ')'),
        'LOCATION:' . $esc($loc),
        'DESCRIPTION:' . $esc(t('Mesa para %d a nombre de %s.', $r['people'], $r['name']) . ($s['phone'] ? ' ' . t('Teléfono del restaurante:') . ' ' . $s['phone'] : '')),
        'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc(t('Reserva en %s', $s['name'])), 'END:VALARM',
        'END:VEVENT', 'END:VCALENDAR', '',
    ]);
    exit;
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
// Solo se confirma al pulsar el botón (POST). Abrir el enlace no cambia nada: WhatsApp lo abre solo para la vista previa.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id = (int)($_POST['r'] ?? 0);
    $token = (string)($_POST['t'] ?? '');
    if (($_POST['accion'] ?? '') === 'cancelar') cancel_by_link($id, $token);
    else confirm_by_customer($id, $token);
    header('Location: confirmar.php?r=' . $id . '&t=' . rawurlencode($token) . (($_POST['accion'] ?? '') === 'cancelar' ? '' : '&hecho=1'), true, 303);
    exit;
}
[$state, $r] = peek_by_customer($id, $token);
if ($state === 'ya' && isset($_GET['hecho'])) $state = 'confirmada';
$ok = in_array($state, ['confirmada', 'ya'], true);
$pend = $state === 'pendiente';

$errors = [
    'cancelada' => ($r['cancel_by'] ?? '') === 'restaurante'
        ? [t('Reserva cancelada'), t('El restaurante ha cancelado esta reserva.') . ($s['phone'] ? ' ' . t('Si tienes dudas, llámanos al %s.', $s['phone']) : '')]
        : [t('Reserva cancelada'), t('Tu reserva está cancelada. Si quieres venir otro día, haz una reserva nueva: tardas un minuto.')],
    'rechazada' => [t('No pudimos atender esta reserva'), t('Prueba con otro día u otra hora.')],
    'pasada'    => [t('Este enlace ha caducado'), t('La fecha de la reserva ya ha pasado.')],
    'invalido'  => [t('Este enlace no funciona'), t('Puede que se haya copiado incompleto. Ábrelo otra vez desde el WhatsApp o haz una reserva nueva.')],
];

page_start(($ok ? t('Reserva confirmada') : ($pend ? t('Confirma tu reserva') : t('Reserva'))) . ' · ' . $s['name'], '', 'page-confirmar');
site_header(current_customer());
?>
<main id="main" class="confirmar">
  <div class="wrap">
  <?php if ($ok || $pend):
      $ts = strtotime($r['date'] . ' ' . $r['time']);
      $first = explode(' ', trim($r['name']))[0];
  ?>
    <p class="cf-mark" aria-hidden="true">✦</p>
    <h1 class="cf-title"><?= $pend ? t('Confirma tu reserva') : ($state === 'ya' ? t('Ya está confirmada') : t('¡Reserva confirmada!')) ?></h1>
    <p class="cf-lead"><?= $pend ? h(t('Hola, %s. Revisa los datos y pulsa el botón para confirmarla.', $first)) : h(t('Te esperamos, %s.', $first)) ?></p>

    <section class="ticket" aria-label="<?= t('Datos de la reserva') ?>">
      <p class="ticket-brand"><?= h(mb_strtoupper($s['name'])) ?></p>
      <div class="ticket-fecha">
        <span class="ticket-dia"><?= h(day_name((int)date('N', $ts), true)) ?></span>
        <span class="ticket-num"><?= (int)date('j', $ts) ?></span>
        <span class="ticket-mes"><?= h(month_name((int)date('n', $ts))) ?></span>
      </div>
      <dl class="ticket-datos">
        <div><dt><?= t('Hora') ?></dt><dd><?= h($r['time']) ?></dd></div>
        <div><dt><?= t('Personas') ?></dt><dd><?= (int)$r['people'] ?></dd></div>
        <div><dt><?= t('A nombre de') ?></dt><dd><?= h($r['name']) ?></dd></div>
      </dl>
      <?php if ($s['address']): ?>
        <p class="ticket-dir"><?= h($s['address']) ?><?= $s['city'] ? ', ' . h($s['city']) : '' ?></p>
      <?php endif; ?>
      <p class="ticket-nota"><?= t('Guardamos la mesa %d minutos. Si no puedes venir, cancelarla es gratis.', (int)$s['late_minutes']) ?></p>
    </section>
    <?php if ($pend): ?>
    <form class="cf-btns" method="post" action="confirmar.php">
      <input type="hidden" name="r" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="t" value="<?= h($token) ?>">
      <button class="btn btn-rubor cf-confirm" type="submit"><?= t('Confirmar mi reserva') ?></button>
    </form>
    <p class="cf-help"><?= t('¿Algún dato está mal? No la confirmes: <a href="reservar.php" data-book>haz una reserva nueva</a> con los datos correctos.') ?></p>
    <?php else: ?>

    <div class="cf-btns">
      <a class="btn btn-rubor" href="confirmar.php?ics=1&amp;r=<?= (int)$r['id'] ?>&amp;t=<?= h($token) ?>"><?= t('Añadir al calendario') ?></a>
      <?php if ($s['address']): ?><a class="btn btn-ghost" href="<?= h(maps_href()) ?>" target="_blank" rel="noopener"><?= t('Cómo llegar') ?></a><?php endif; ?>
      <a class="btn btn-ghost" href="carta.php"><?= t('Ver la carta') ?></a>
    </div>
    <p class="cf-help">
      <?php if ($s['phone']): ?><?= t('¿Algún cambio? Llámanos al %s', '<a href="' . h(tel_href($s['phone'])) . '">' . h($s['phone']) . '</a>') ?><?= $s['whatsapp'] ? ' ' . t('o escríbenos por %s', '<a href="https://wa.me/' . h(wa_number($s['whatsapp'])) . '" target="_blank" rel="noopener">WhatsApp</a>') : '' ?>.<?php endif; ?>
      <?php if (current_customer()): ?><br><?= t('También la tienes en <a href="cuenta.php">tus reservas</a>.') ?><?php endif; ?>
    </p>
    <?php endif; ?>
    <form class="cf-cancel" method="post" action="confirmar.php" onsubmit="return confirm(<?= h(json_encode(t('¿Seguro que quieres cancelar la reserva?'), JSON_UNESCAPED_UNICODE)) ?>)">
      <input type="hidden" name="r" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="t" value="<?= h($token) ?>">
      <input type="hidden" name="accion" value="cancelar">
      <span><?= t('¿No puedes venir?') ?></span> <button type="submit"><?= t('Cancelar la reserva') ?></button>
    </form>
  <?php else: [$title, $text] = $errors[$state]; ?>
    <p class="cf-mark is-off" aria-hidden="true">—</p>
    <h1 class="cf-title"><?= h($title) ?></h1>
    <p class="cf-lead"><?= h($text) ?></p>
    <div class="cf-btns">
      <a class="btn btn-rubor" href="reservar.php" data-book><?= t('Hacer una reserva') ?></a>
      <?php if ($s['phone']): ?><a class="btn btn-ghost" href="<?= h(tel_href($s['phone'])) ?>"><?= t('Llamar') ?></a><?php endif; ?>
    </div>
  <?php endif; ?>
  </div>
</main>
<?php
site_footer();
include __DIR__ . '/lib/reserva-dialogo.php';
page_end();

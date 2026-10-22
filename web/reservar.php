<?php
declare(strict_types=1);
/* Reservas: cómo funcionan y políticas. El botón abre la ventana de reserva en 3 pasos. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }

$s = settings();
$customer = current_customer();
$todayClosed = in_array(today(), $s['closed_dates'], true);
$todayRanges = $todayClosed ? [] : ($s['hours'][date('N')] ?? []);
// Políticas: cada línea "Título: texto"
$policyItems = [];
foreach (preg_split('/\R/', (string)$s['policies']) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    [$t, $txt] = array_pad(explode(':', $line, 2), 2, '');
    $policyItems[] = $txt === '' ? ['', tr(trim($t))] : [tr(trim($t)), tr(trim($txt))];
}
page_start(t('Reservar mesa') . ' · ' . $s['name'], t('Reserva mesa en %s en un minuto. Confirmación por WhatsApp y cancelación gratis.', $s['name']), 'page-sub page-reservar');
site_header($customer);
?>
<main id="main">
  <section class="book" id="reservar" aria-labelledby="book-t">
    <div class="wrap book-grid">
      <div>
        <h2 id="book-t" class="title"><?= t('Reserva tu mesa') ?></h2>
        <p class="book-lead"><?= t('Elige día, hora y cuántos venís. Te enviaremos un WhatsApp con un enlace para confirmar la reserva.') ?></p>
        <button class="btn btn-rubor btn-lg" type="button" data-book><?= t('Reservar mesa') ?></button>
        <p class="book-alt">
          <?php if ($customer): ?>
            <?= t('Reservas como %s. Tus reservas están en <a href="cuenta.php">tu cuenta</a>.', h($customer['name'])) ?>
          <?php else: ?>
            <?= t('Con una <a href="cuenta.php?registro=1">cuenta</a> reservas más rápido y puedes ver o cancelar tus reservas.') ?>
          <?php endif; ?>
          <?php if ($s['phone']): ?><br><?= t('Grupos de más de %d:', (int)$s['max_people']) ?> <a href="<?= h(tel_href($s['phone'])) ?>"><?= h($s['phone']) ?></a>.<?php endif; ?>
        </p>
      </div>
      <dl class="book-facts">
        <div><dt><?= t('Hoy') ?></dt><dd><?= $todayRanges ? h(ranges_text($todayRanges)) : t('Cerrado') ?></dd></div>
        <div><dt><?= t('Cancelación') ?></dt><dd><?= t('Siempre gratis') ?></dd></div>
        <div><dt><?= t('Retrasos') ?></dt><dd><?= t('Guardamos la mesa %d min', (int)$s['late_minutes']) ?></dd></div>
      </dl>
    </div>
  </section>

  <section class="policies" id="politicas" aria-labelledby="pol-t">
    <div class="wrap pol-grid">
      <h2 id="pol-t" class="title"><?= t('Políticas') ?></h2>
      <div class="pol-list">
        <details open>
          <summary><?= t('Reservas y cancelaciones') ?></summary>
          <p><?= t('Al reservar envías una petición. El restaurante la revisa y te manda un WhatsApp con un enlace: ábrelo y pulsa «Confirmar mi reserva». Cancelar es siempre gratis, desde ese mismo enlace o desde <a href="cuenta.php">Mi cuenta</a>.') ?><?= $s['phone'] ? ' ' . t('También puedes llamarnos al %s.', '<a href="' . h(tel_href($s['phone'])) . '">' . h($s['phone']) . '</a>') : '' ?></p>
        </details>
        <details>
          <summary><?= t('Si llegas tarde') ?></summary>
          <p><?= t('Guardamos la mesa %d minutos. Pasado ese tiempo, si no nos has avisado, puede que la demos a otras personas.', (int)$s['late_minutes']) ?></p>
        </details>
        <?php foreach ($policyItems as [$t, $txt]): ?>
          <details>
            <summary><?= h($t ?: t('Más información')) ?></summary>
            <p><?= h($txt) ?></p>
          </details>
        <?php endforeach; ?>
        <details>
          <summary><?= t('Tus datos') ?></summary>
          <p><?= t('Usamos tus datos solo para gestionar tus reservas y se guardan cifrados. El nombre, el teléfono y el email de cada reserva se borran solos 24 horas después. No los cedemos a nadie ni te enviamos publicidad. Lee la <a href="legal.php#privacidad">política de privacidad</a>.') ?></p>
        </details>
      </div>
    </div>
  </section>
</main>
<?php site_footer(); ?>
<?php if ($s['whatsapp']): ?>
  <a class="wa-float" href="https://wa.me/<?= h(wa_number($s['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="<?= t('Escríbenos por WhatsApp') ?>">
    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg>
  </a>
<?php endif; ?>
<?php include __DIR__ . '/lib/reserva-dialogo.php'; ?>
<?php page_end(['customer' => customer_out($customer)]); ?>

<?php
declare(strict_types=1);
/* La carta, en su propia página (el librito que se hojea). */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }

$s = settings();
$customer = current_customer();
$menu = menu_data(false);
page_start('La carta · ' . $s['name'], 'Carta de ' . $s['name'] . ($s['city'] ? ' en ' . $s['city'] : '') . ': platos, precios y alérgenos.', 'page-sub page-carta');
site_header($customer);
?>
<main id="main">
  <?php include __DIR__ . '/lib/carta-libro.php'; ?>
  <section class="sub-cta">
    <div class="wrap sub-cta-in">
      <p>¿Te apetece? Reserva mesa en un minuto.</p>
      <a class="btn btn-rubor" href="reservar.php" data-book>Reservar mesa</a>
    </div>
  </section>
</main>
<?php site_footer(); ?>
<?php if ($s['whatsapp']): ?>
  <a class="wa-float" href="https://wa.me/<?= h(wa_number($s['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.4-.3Z"/></svg>
  </a>
<?php endif; ?>
<?php include __DIR__ . '/lib/reserva-dialogo.php'; ?>
<?php page_end(['customer' => customer_out($customer)]); ?>

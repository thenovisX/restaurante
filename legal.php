<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }
$s = settings();
$titular = $s['owner_name'] ?: $s['name'];
$contacto = $s['email'] ?: $s['phone'];
$google_mail = mail_via_google();
$analytics = $s['ga_id'] !== '' || $s['gtm_id'] !== '';
/** Normas de reserva del restaurante (cada línea del panel), traducidas. */
function legal_policies(): array
{
    $out = [];
    foreach (preg_split('/\R/', (string)settings()['policies']) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        [$t, $txt] = array_pad(explode(':', $line, 2), 2, '');
        $out[] = $txt === '' ? tr($t) : tr(trim($t)) . ': ' . tr(trim($txt));
    }
    return $out;
}
page_start(t('Información legal') . ' · ' . $s['name'], '', 'page-legal');
site_header(current_customer());
?>
<main id="main" class="legal">
  <div class="wrap">
  <h1 class="title"><?= t('Información legal') ?></h1>

  <?php if (lang() !== 'es'): ?><p class="muted small"><?= t('Traducción de los textos legales originales en español. En caso de duda, prevalece la versión en español.') ?></p><?php endif; ?>
  <?php require __DIR__ . '/lib/legal/' . lang() . '.php'; ?>
  <p class="muted small"><?= t('Última actualización: %s.', h(date('j/n/Y', strtotime(LEGAL_VERSION)))) ?></p>
  </div>
</main>
<?php site_footer(); page_end(); ?>

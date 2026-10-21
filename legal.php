<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/plantilla.php';
if (!installed()) { header('Location: install.php'); exit; }
$s = settings();
$titular = $s['owner_name'] ?: $s['name'];
$contacto = $s['email'] ?: $s['phone'];
page_start('Información legal · ' . $s['name'], '', 'page-legal');
site_header(current_customer());
?>
<main id="main" class="legal">
  <div class="wrap">
  <h1 class="title">Información legal</h1>

  <h2 id="aviso">Aviso legal</h2>
  <p>Esta web pertenece a <strong><?= h($titular) ?></strong><?= $s['owner_nif'] ? ', con NIF ' . h($s['owner_nif']) : '' ?>,
    con domicilio en <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?: '—' ?>. Contacto: <?= h($contacto ?: '—') ?>.</p>
  <p>Los textos, fotos y precios son orientativos y pueden cambiar sin previo aviso. La carta del local es la que vale.</p>

  <h2 id="politicas">Políticas de reserva</h2>
  <p>Las reservas se pueden cancelar sin coste hasta <?= (int)$s['cancel_hours'] ?> horas antes. Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos desde la hora reservada.
    <?= $s['auto_confirm'] ? 'Las reservas online quedan confirmadas al momento.' : 'Las reservas online quedan pendientes hasta que el restaurante las confirma por email.' ?></p>
  <?php foreach (preg_split('/\R/', (string)$s['policies']) as $line): if (trim($line) === '') continue; ?><p><?= h(trim($line)) ?></p><?php endforeach; ?>

  <h2 id="privacidad">Política de privacidad</h2>
  <p><strong>Responsable:</strong> <?= h($titular) ?>. <strong>Contacto:</strong> <?= h($contacto ?: '—') ?>.</p>
  <p><strong>Qué datos recogemos:</strong> al reservar, tu nombre, teléfono, email (si lo das), el día, la hora, el número de personas y las notas que escribas. Si creas una cuenta, además guardamos tu contraseña cifrada y el historial de tus reservas.</p>
  <p><strong>Para qué:</strong> solo para gestionar tus reservas y avisarte si hay algún cambio. No enviamos publicidad ni cedemos tus datos a nadie,
    salvo al proveedor que aloja la web (Hostinger), que los guarda por nosotros.</p>
  <p><strong>Base legal:</strong> tu consentimiento y la gestión de lo que nos pides.</p>
  <p><strong>Cuánto tiempo:</strong> el nombre, el teléfono, el email y las notas de cada reserva se borran solos 24 horas después de la hora reservada. Los datos de tu cuenta se guardan hasta que la borres; puedes hacerlo tú mismo desde <a href="cuenta.php">Mi cuenta</a>.</p>
  <p><strong>Seguridad:</strong> la web funciona con conexión cifrada (https). Tu nombre, teléfono, email y notas se guardan cifrados, y tu contraseña nunca se guarda: solo una huella cifrada que no se puede revertir.</p>
  <p><strong>Tus derechos:</strong> puedes ver, corregir o borrar tus datos, u oponerte a su uso, escribiendo a <?= h($contacto ?: 'nuestro contacto') ?>.
    Si crees que no los tratamos bien, puedes reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>

  <h2 id="cookies">Cookies</h2>
  <p>Las cookies son pequeños archivos que la web guarda en tu navegador. Las necesarias se usan siempre; las demás solo si las aceptas en el aviso de cookies. Puedes cambiar tu elección cuando quieras: <a href="#cookies" data-cookies>configurar cookies</a>.</p>
  <table class="ck-tabla">
    <thead><tr><th>Cookie</th><th>Para qué</th><th>De quién</th><th>Duración</th></tr></thead>
    <tbody>
      <tr><td>cookie_consent</td><td>Recordar tu elección sobre las cookies (necesaria)</td><td>Esta web</td><td>13 meses</td></tr>
      <tr><td>cliente_sesion</td><td>Mantener abierta tu cuenta si entras (necesaria)</td><td>Esta web</td><td>60 días</td></tr>
      <?php if ($s['ga_id'] !== '' || $s['gtm_id'] !== ''): ?>
      <tr><td>_ga, _ga_*</td><td>Estadísticas anónimas de visitas (análisis, solo si lo aceptas)</td><td>Google Analytics</td><td>2 años</td></tr>
      <?php endif; ?>
      <?php if ($s['gtm_id'] !== ''): ?>
      <tr><td>_gcl_*</td><td>Medir anuncios de Google (publicidad, solo si lo aceptas)</td><td>Google</td><td>3 meses</td></tr>
      <?php endif; ?>
      <?php if ($s['pixel_id'] !== ''): ?>
      <tr><td>_fbp, _fbc</td><td>Medir anuncios en Facebook e Instagram (publicidad, solo si lo aceptas)</td><td>Meta</td><td>3 meses</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p>Si pulsas «Mostrar el mapa», Google Maps puede usar sus propias cookies. Las fotos de la web se cargan desde Unsplash.</p>
  <p>También puedes borrar o bloquear las cookies desde la configuración de tu navegador.</p>

  <p class="muted small">Texto de referencia. Revísalo con un asesor para adaptarlo a tu negocio.</p>
  </div>
</main>
<?php site_footer(); page_end(); ?>

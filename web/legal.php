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
  <p>Solo usamos una cookie técnica, <code>cliente_sesion</code>, cuando entras en tu cuenta, para mantener la sesión abierta. Es imprescindible para que la cuenta funcione y no se usa para nada más. No usamos cookies de publicidad ni de analítica.</p>
  <p>Las fotos de la web se cargan desde Unsplash, que puede registrar tu visita como cualquier servidor de imágenes.</p>

  <p class="muted small">Texto de referencia. Revísalo con un asesor para adaptarlo a tu negocio.</p>
  </div>
</main>
<?php site_footer(); page_end(); ?>

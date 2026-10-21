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

  <h2 id="condiciones">Condiciones de uso</h2>
  <p>Estas condiciones regulan el uso de esta web y de su servicio de reservas. Al reservar o crear una cuenta las aceptas. Versión del <?= h(date('j/n/Y', strtotime(LEGAL_VERSION))) ?>.</p>
  <p><strong>El servicio:</strong> la web permite consultar la carta y solicitar reservas de mesa en <?= h($s['name']) ?>. Reservar es gratis y no se cobra nada a través de la web.</p>
  <p><strong>Reservas:</strong> <?= $s['auto_confirm'] ? 'la reserva queda confirmada al enviarla.' : 'la reserva es una solicitud hasta que el restaurante la confirma por email.' ?>
    Puedes cancelarla sin coste hasta <?= (int)$s['cancel_hours'] ?> horas antes. Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos desde la hora reservada.
    El restaurante puede rechazar o cancelar una reserva si no hay disponibilidad o por causas ajenas a su voluntad, y te avisará lo antes posible.</p>
  <p><strong>Tu cuenta:</strong> es personal. Mantén tu contraseña en secreto. Podemos suspender una cuenta que se use para hacer reservas falsas o de forma abusiva. Puedes borrarla cuando quieras desde «Mi cuenta».</p>
  <p><strong>Carta y precios:</strong> son orientativos y pueden cambiar. Los alérgenos se indican con la mayor exactitud posible, pero avísanos siempre de cualquier alergia antes de pedir.</p>
  <p><strong>Responsabilidad:</strong> hacemos lo posible para que la web funcione bien y sin interrupciones, pero no respondemos de fallos técnicos ajenos a nosotros. Nada en estas condiciones limita los derechos que te reconoce la ley como consumidor.</p>
  <p><strong>Ley y tribunales:</strong> se aplica la ley española. Si hubiera un conflicto, serán competentes los juzgados de tu domicilio como consumidor.</p>

  <h2 id="privacidad">Política de privacidad</h2>
  <p><strong>Responsable:</strong> <?= h($titular) ?><?= $s['owner_nif'] ? ', NIF ' . h($s['owner_nif']) : '' ?>. <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?>. <strong>Contacto:</strong> <?= h($contacto ?: '—') ?>.</p>
  <p><strong>Qué datos tratamos y para qué:</strong></p>
  <ul>
    <li><strong>Reservas:</strong> nombre, teléfono, email (opcional), día, hora, personas y notas. Sirven para gestionar la reserva y avisarte de cambios. Base legal: la gestión de lo que nos pides (art. 6.1.b RGPD). El teléfono solo se usa para contactarte sobre tu reserva: no enviamos SMS ni hacemos llamadas comerciales.</li>
    <li><strong>Cuenta (opcional):</strong> nombre, email, teléfono (opcional) y una huella de tu contraseña que no se puede revertir. Base legal: tu consentimiento y el servicio que pides.</li>
    <li><strong>Estadísticas y publicidad:</strong> solo si aceptas esas cookies. Base legal: tu consentimiento, que puedes retirar en cualquier momento.</li>
    <li><strong>Registro de seguridad:</strong> cuándo aceptas estas condiciones, cambias tu contraseña o tus datos, con la IP (cifrada) y el navegador. Base legal: nuestro interés legítimo en poder demostrar tu consentimiento y proteger tu cuenta.</li>
  </ul>
  <p><strong>Publicidad:</strong> no te enviamos emails promocionales ni compartimos tus datos para que otros lo hagan. Los emails que recibes son solo sobre tu reserva o tu cuenta.</p>
  <p><strong>Quién más trata tus datos (encargados):</strong> Hostinger, que aloja la web en servidores de la Unión Europea<?php if ($s['ga_id'] !== '' || $s['gtm_id'] !== ''): ?>; Google (Analytics), solo si aceptas las cookies de análisis<?php endif; ?><?php if ($s['pixel_id'] !== ''): ?>; Meta (Facebook e Instagram), solo si aceptas las cookies de publicidad<?php endif; ?>.
    <?php if ($s['ga_id'] !== '' || $s['gtm_id'] !== '' || $s['pixel_id'] !== ''): ?>Google y Meta pueden transferir datos a Estados Unidos, amparados por el Marco de Privacidad de Datos UE-EE. UU. y cláusulas contractuales tipo. Si no aceptas esas cookies, no se les envía nada.<?php endif; ?></p>
  <p><strong>Cuánto tiempo:</strong> el nombre, el teléfono, el email y las notas de cada reserva se borran solos 24 horas después de la hora reservada. Los datos de tu cuenta se guardan hasta que la borres. El registro de seguridad se conserva mientras pueda hacer falta para demostrar tu consentimiento.</p>
  <p><strong>Seguridad:</strong> la conexión va siempre cifrada (https). Tus datos personales se guardan cifrados en la base de datos y tu contraseña no se guarda nunca, solo una huella que no se puede revertir. Si hubiera una brecha de seguridad que afectara a tus datos, avisaríamos a la Agencia Española de Protección de Datos en menos de 72 horas y a ti sin demora.</p>
  <p><strong>Tus derechos:</strong> puedes acceder a tus datos, corregirlos, borrarlos, oponerte a su uso, limitarlo y llevártelos a otro servicio (portabilidad). Desde «Mi cuenta» puedes descargar todos tus datos y borrar la cuenta tú mismo; para todo lo demás escribe a <?= h($contacto ?: 'nuestro contacto') ?>. Si crees que no tratamos bien tus datos, puedes reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>
  <p><strong>Menores:</strong> la web no está dirigida a menores de 14 años. Si tienes menos de 14, pide a tus padres o tutores que hagan la reserva.</p>

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

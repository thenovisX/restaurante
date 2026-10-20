<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
if (!installed()) { header('Location: install.php'); exit; }
$s = settings();
$titular = $s['owner_name'] ?: $s['name'];
$contacto = $s['email'] ?: $s['phone'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Aviso legal y privacidad · <?= h($s['name']) ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="assets/web.css?v=3">
</head>
<body>
<header class="top">
  <a class="top-name" href="./"><?= h($s['name']) ?></a>
  <nav><a href="./#reservar">Reservar</a></nav>
</header>
<main class="legal">
  <h1>Información legal</h1>

  <h2 id="aviso">Aviso legal</h2>
  <p>Esta web pertenece a <strong><?= h($titular) ?></strong><?= $s['owner_nif'] ? ', con NIF ' . h($s['owner_nif']) : '' ?>,
    con domicilio en <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?: '—' ?>.
    Contacto: <?= h($contacto ?: '—') ?>.</p>
  <p>Los textos, fotos y precios son orientativos y pueden cambiar sin previo aviso. La carta del local es la que vale.</p>

  <h2 id="privacidad">Política de privacidad</h2>
  <p><strong>Responsable:</strong> <?= h($titular) ?>. <strong>Contacto:</strong> <?= h($contacto ?: '—') ?>.</p>
  <p><strong>Qué datos recogemos:</strong> cuando reservas, tu nombre, teléfono, email (si lo das), el día, la hora, el número de personas y las notas que escribas.</p>
  <p><strong>Para qué:</strong> solo para gestionar tu reserva y avisarte si hay algún cambio. No enviamos publicidad ni cedemos tus datos a nadie,
    salvo al proveedor que aloja la web (Hostinger), que los guarda por nosotros.</p>
  <p><strong>Base legal:</strong> tu consentimiento al enviar la reserva y la gestión de lo que nos pides.</p>
  <p><strong>Cuánto tiempo:</strong> el necesario para la reserva y, como máximo, un año después.</p>
  <p><strong>Tus derechos:</strong> puedes pedirnos ver, corregir o borrar tus datos, u oponerte a su uso, escribiendo a <?= h($contacto ?: 'nuestro contacto') ?>.
    Si crees que no los tratamos bien, puedes reclamar ante la Agencia Española de Protección de Datos (aepd.es).</p>

  <h2 id="cookies">Cookies</h2>
  <p>Esta web no usa cookies propias ni de analítica. Solo si pulsas «Mostrar el mapa» se carga Google Maps, que puede instalar sus propias cookies.</p>

  <p class="muted small" style="margin-top:48px">Texto de referencia. Revísalo con un asesor para adaptarlo a tu negocio.</p>
</main>
</body>
</html>

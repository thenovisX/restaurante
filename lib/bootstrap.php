<?php
declare(strict_types=1);

require __DIR__ . '/i18n.php';
require __DIR__ . '/core.php';
require __DIR__ . '/cifrado.php';
require __DIR__ . '/reservas.php';
require __DIR__ . '/carta.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/clientes.php';
require __DIR__ . '/borrado.php';
require __DIR__ . '/traductor.php';

date_default_timezone_set(cfg()['timezone'] ?? 'Europe/Madrid');
ini_set('display_errors', !empty(cfg()['debug']) ? '1' : '0');

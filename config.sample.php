<?php
// install.php crea config.php automáticamente. Esto es solo de referencia.
return [
    'db_driver' => 'mysql',            // 'mysql' en Hostinger, 'sqlite' para pruebas
    'db_host'   => 'localhost',
    'db_name'   => 'u123456789_restaurante',
    'db_user'   => 'u123456789_admin',
    'db_pass'   => 'CAMBIA_ESTO',
    'db_path'   => __DIR__ . '/data/restaurante.sqlite',
    'timezone'  => 'Europe/Madrid',
    'secret'    => 'cadena-aleatoria-larga',
    'debug'     => false,
];

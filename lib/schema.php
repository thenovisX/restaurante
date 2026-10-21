<?php
declare(strict_types=1);

function schema_sql(bool $sqlite): array
{
    $ai = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT PRIMARY KEY AUTO_INCREMENT';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) PRIMARY KEY, v TEXT NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS users (id $ai, username VARCHAR(60) NOT NULL UNIQUE, pass_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS tokens (token CHAR(64) PRIMARY KEY, user_id INT NOT NULL, created_at DATETIME NOT NULL, last_used DATETIME NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS login_attempts (id $ai, ip VARCHAR(64) NOT NULL, at DATETIME NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS categories (id $ai, name VARCHAR(80) NOT NULL, position INT NOT NULL DEFAULT 0)$tail",
        "CREATE TABLE IF NOT EXISTS dishes (id $ai, category_id INT NOT NULL, name VARCHAR(120) NOT NULL, description TEXT NOT NULL,
            price DECIMAL(8,2) NOT NULL DEFAULT 0, allergens VARCHAR(255) NOT NULL DEFAULT '', photo VARCHAR(255) NOT NULL DEFAULT '',
            available TINYINT NOT NULL DEFAULT 1, featured TINYINT NOT NULL DEFAULT 0, position INT NOT NULL DEFAULT 0)$tail",
        "CREATE TABLE IF NOT EXISTS reservations (id $ai, name VARCHAR(80) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(120) NOT NULL DEFAULT '',
            rdate DATE NOT NULL, rtime CHAR(5) NOT NULL, people INT NOT NULL, notes TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            source VARCHAR(10) NOT NULL DEFAULT 'web', ip VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            customer_id INT NOT NULL DEFAULT 0)$tail",
        "CREATE TABLE IF NOT EXISTS customers (id $ai, name VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE, phone VARCHAR(30) NOT NULL DEFAULT '',
            pass_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_login DATETIME NULL)$tail",
        'CREATE INDEX idx_res_date ON reservations (rdate)',
        'CREATE INDEX idx_dish_cat ON dishes (category_id)',
        'CREATE INDEX idx_res_customer ON reservations (customer_id)',
    ];
}

/** Carta de ejemplo para que la web no salga vacía. Se edita desde la app. */
function sample_menu(): array
{
    return [
        'Para empezar' => [
            ['Ostra de Arcade', 'Al natural, con granizado de albariño y lima.', 4.5, 'moluscos,sulfitos', 0],
            ['Tartar de atún rojo', 'Ají amarillo, aguacate y crujiente de maíz.', 18.0, 'pescado,soja,sesamo', 1],
            ['Croqueta de chipirón en su tinta', 'Unidad.', 3.0, 'gluten,lacteos,huevos,moluscos', 0],
            ['Pimientos de Padrón al carbón', 'Sal de escamas y aceite ahumado.', 8.0, '', 0],
        ],
        'Del fuego' => [
            ['Pulpo a la brasa', 'Puré de patata ahumada, pimentón de la Vera y ajada.', 24.0, 'moluscos,lacteos', 1],
            ['Rodaballo salvaje', 'A la parrilla, con beurre blanc de cítricos.', 32.0, 'pescado,lacteos', 0],
            ['Lomo alto de vaca gallega', '500 g, madurado 45 días. Para compartir.', 48.0, '', 0],
            ['Arroz negro de choco', 'Con alioli de ajo asado.', 22.0, 'moluscos,huevos,crustaceos', 0],
        ],
        'Dulces' => [
            ['Tarta de queso de Arzúa', 'Cremosa, con frutos rojos.', 8.0, 'lacteos,huevos,gluten', 1],
            ['Chocolate, café y avellana', '', 9.0, 'lacteos,frutos,huevos', 0],
        ],
        'Coctelería' => [
            ['Novia', 'Gin, licor de flor de saúco, pomelo rosado y espuma de cava.', 12.0, 'sulfitos', 1],
            ['Atlántico', 'Mezcal, licor de café y sal marina.', 12.0, '', 0],
            ['Albariño, copa', 'D.O. Rías Baixas.', 4.5, 'sulfitos', 0],
        ],
    ];
}

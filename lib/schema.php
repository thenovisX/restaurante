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
            source VARCHAR(10) NOT NULL DEFAULT 'web', ip VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)$tail",
        'CREATE INDEX idx_res_date ON reservations (rdate)',
        'CREATE INDEX idx_dish_cat ON dishes (category_id)',
    ];
}

/** Carta de ejemplo para que la web no salga vacía. Se edita desde la app. */
function sample_menu(): array
{
    return [
        'Para compartir' => [
            ['Pulpo á feira', 'Con aceite de oliva, pimentón de la Vera y sal en escamas, sobre cachelos.', 16.5, 'moluscos', 1],
            ['Pimientos de Padrón', 'Unos pican y otros no.', 7.0, '', 0],
            ['Zamburiñas a la plancha', 'Media docena, con ajada suave.', 14.0, 'moluscos', 0],
            ['Croquetas caseras de lacón', 'Seis unidades.', 9.0, 'gluten,lacteos,huevos', 0],
            ['Empanada de zamburiñas', 'Ración. Masa de maíz hecha en casa.', 6.5, 'gluten,moluscos', 0],
        ],
        'Del mar' => [
            ['Merluza a la gallega', 'Al vapor, con patata, cebolla y ajada.', 19.0, 'pescado', 1],
            ['Chipirones encebollados', 'Con su tinta y arroz blanco.', 16.0, 'moluscos', 0],
        ],
        'De la tierra' => [
            ['Churrasco de ternera gallega', 'Con patatas fritas y pimientos.', 15.0, '', 0],
            ['Caldo gallego', 'Con grelos, alubias y unto.', 7.5, 'apio', 0],
        ],
        'Postres' => [
            ['Tarta de Santiago', 'Almendra, azúcar y huevo. Receta de la casa.', 5.5, 'frutos,huevos', 1],
            ['Filloas con crema', 'Dos unidades.', 5.0, 'gluten,huevos,lacteos', 0],
            ['Queso de tetilla con membrillo', '', 6.0, 'lacteos', 0],
        ],
        'Bebidas' => [
            ['Albariño, copa', 'D.O. Rías Baixas.', 3.5, 'sulfitos', 0],
            ['Ribeiro, taza', '', 2.5, 'sulfitos', 0],
            ['Agua mineral', '50 cl.', 2.0, '', 0],
        ],
    ];
}

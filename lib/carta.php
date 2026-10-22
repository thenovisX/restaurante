<?php
declare(strict_types=1);

/** Los 14 alérgenos de declaración obligatoria (Reglamento UE 1169/2011). */
const ALLERGENS = [
    'gluten'      => ['Gluten', 'Gl', 'Trigo (también espelta y kamut), centeno, cebada, avena y todo lo hecho con ellos: pan, pasta, rebozados, salsas espesadas.'],
    'crustaceos'  => ['Crustáceos', 'Cr', 'Gambas, langostinos, cigalas, bogavante, nécoras, cangrejos y caldos o salsas que los lleven.'],
    'huevos'      => ['Huevos', 'Hu', 'Huevo de cualquier ave y lo elaborado con él: mayonesa, alioli, masas, rebozados, postres.'],
    'pescado'     => ['Pescado', 'Pe', 'Cualquier pescado y sus derivados, como fumet, caldos o salsas de pescado.'],
    'cacahuetes'  => ['Cacahuetes', 'Ca', 'Cacahuetes y productos que los contienen, como aceite o crema de cacahuete.'],
    'soja'        => ['Soja', 'So', 'Soja y derivados: salsa de soja, tofu, edamame, bebida de soja, lecitina de soja.'],
    'lacteos'     => ['Lácteos', 'La', 'Leche de cualquier animal y derivados, incluida la lactosa: nata, mantequilla, queso, yogur.'],
    'frutos'      => ['Frutos de cáscara', 'Fc', 'Almendras, avellanas, nueces, anacardos, pacanas, pistachos, macadamias y nueces de Brasil.'],
    'apio'        => ['Apio', 'Ap', 'Apio en rama, en raíz o como especia, y caldos, sopas o sofritos que lo lleven.'],
    'mostaza'     => ['Mostaza', 'Mo', 'Mostaza en grano, en polvo o en salsa, y aliños o vinagretas que la contengan.'],
    'sesamo'      => ['Sésamo', 'Se', 'Granos y aceite de sésamo, tahini, y panes o salsas que lo lleven.'],
    'sulfitos'    => ['Sulfitos', 'Su', 'Dióxido de azufre y sulfitos (más de 10 mg/kg). Suelen estar en el vino, la cerveza, la sidra y algunos encurtidos.'],
    'altramuces'  => ['Altramuces', 'Al', 'Altramuces y harinas o productos elaborados con ellos.'],
    'moluscos'    => ['Moluscos', 'Ml', 'Mejillones, almejas, ostras, vieiras, zamburiñas, pulpo, calamar, sepia y caracoles.'],
];

function allergen_list(): array
{
    $o = [];
    foreach (ALLERGENS as $k => [$name, $abbr, $desc]) $o[] = ['key' => $k, 'name' => $name, 'abbr' => $abbr, 'desc' => $desc];
    return $o;
}

function dish_out(array $d): array
{
    return [
        'id' => (int)$d['id'], 'category_id' => (int)$d['category_id'], 'name' => $d['name'],
        'description' => $d['description'], 'price' => (float)$d['price'],
        'allergens' => $d['allergens'] === '' ? [] : explode(',', $d['allergens']),
        'photo' => $d['photo'] === '' ? '' : (preg_match('#^https?://#', $d['photo']) ? $d['photo'] : base_url() . '/' . $d['photo']),
        'available' => (bool)$d['available'], 'featured' => (bool)$d['featured'], 'position' => (int)$d['position'],
    ];
}

/** Carta completa agrupada por categorías. $all = incluir platos agotados (para el panel). */
function menu_data(bool $all): array
{
    $cats = q('SELECT * FROM categories ORDER BY position, id')->fetchAll();
    $dishes = q('SELECT * FROM dishes ' . ($all ? '' : 'WHERE available = 1 ') . 'ORDER BY position, id')->fetchAll();
    $by = [];
    foreach ($dishes as $d) $by[(int)$d['category_id']][] = dish_out($d);
    $out = [];
    foreach ($cats as $c) {
        $items = $by[(int)$c['id']] ?? [];
        if (!$all && !$items) continue;
        $out[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'position' => (int)$c['position'], 'dishes' => $items];
    }
    return ['categories' => $out, 'allergens' => allergen_list()];
}

function save_category(array $in): array
{
    $name = str_in($in, 'name', 80, true, 'nombre de la sección');
    $id = (int)($in['id'] ?? 0);
    if ($id) {
        q('UPDATE categories SET name = ? WHERE id = ?', [$name, $id]);
    } else {
        $pos = (int)q('SELECT COALESCE(MAX(position), 0) + 1 FROM categories')->fetchColumn();
        q('INSERT INTO categories (name, position) VALUES (?, ?)', [$name, $pos]);
        $id = (int)db()->lastInsertId();
    }
    return ['id' => $id, 'name' => $name];
}

function delete_category(int $id): void
{
    $n = (int)q('SELECT COUNT(*) FROM dishes WHERE category_id = ?', [$id])->fetchColumn();
    if ($n) throw new ValidationError("Esta sección tiene $n platos. Muévelos o bórralos antes.");
    q('DELETE FROM categories WHERE id = ?', [$id]);
}

function get_dish(int $id): array
{
    $d = q('SELECT * FROM dishes WHERE id = ?', [$id])->fetch();
    if (!$d) throw new ValidationError('El plato no existe');
    return $d;
}

function save_dish(array $in, ?array $file): array
{
    $id = (int)($in['id'] ?? 0);
    $cat = (int)($in['category_id'] ?? 0);
    if (!q('SELECT id FROM categories WHERE id = ?', [$cat])->fetch()) throw new ValidationError('Elige una sección');
    $name = str_in($in, 'name', 120, true, 'nombre del plato');
    $desc = mb_substr(trim((string)($in['description'] ?? '')), 0, 400);
    $price = round((float)str_replace(',', '.', (string)($in['price'] ?? '0')), 2);
    if ($price < 0 || $price > 9999) throw new ValidationError('Precio no válido');
    $al = $in['allergens'] ?? [];
    if (is_string($al)) $al = $al === '' ? [] : explode(',', $al);
    $al = implode(',', array_values(array_intersect(array_keys(ALLERGENS), (array)$al)));
    $available = filter_var($in['available'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $featured = filter_var($in['featured'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    if ($id) {
        $old = get_dish($id);
        q('UPDATE dishes SET category_id=?, name=?, description=?, price=?, allergens=?, available=?, featured=? WHERE id=?',
            [$cat, $name, $desc, $price, $al, $available, $featured, $id]);
    } else {
        $pos = (int)q('SELECT COALESCE(MAX(position), 0) + 1 FROM dishes WHERE category_id = ?', [$cat])->fetchColumn();
        q('INSERT INTO dishes (category_id, name, description, price, allergens, photo, available, featured, position) VALUES (?,?,?,?,?,?,?,?,?)',
            [$cat, $name, $desc, $price, $al, '', $available, $featured, $pos]);
        $id = (int)db()->lastInsertId();
        $old = get_dish($id);
    }

    if (filter_var($in['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN) && $old['photo']) {
        remove_upload($old['photo']);
        q("UPDATE dishes SET photo = '' WHERE id = ?", [$id]);
    }
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $path = store_photo($file, $id);
        if ($old['photo']) remove_upload($old['photo']);
        q('UPDATE dishes SET photo = ? WHERE id = ?', [$path, $id]);
    }
    return dish_out(get_dish($id));
}

function toggle_dish(int $id, bool $available): array
{
    get_dish($id);
    q('UPDATE dishes SET available = ? WHERE id = ?', [$available ? 1 : 0, $id]);
    return dish_out(get_dish($id));
}

function delete_dish(int $id): void
{
    $d = get_dish($id);
    if ($d['photo']) remove_upload($d['photo']);
    q('DELETE FROM dishes WHERE id = ?', [$id]);
}

/** Sube o baja un plato (dir = -1 / 1) dentro de su sección. */
function move_dish(int $id, int $dir): void
{
    $d = get_dish($id);
    $list = q('SELECT id FROM dishes WHERE category_id = ? ORDER BY position, id', [$d['category_id']])->fetchAll(PDO::FETCH_COLUMN);
    $i = array_search($id, array_map('intval', $list), true);
    $j = $i + ($dir < 0 ? -1 : 1);
    if ($i === false || $j < 0 || $j >= count($list)) return;
    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
    foreach ($list as $pos => $did) q('UPDATE dishes SET position = ? WHERE id = ?', [$pos + 1, (int)$did]);
}

function move_category(int $id, int $dir): void
{
    $list = array_map('intval', q('SELECT id FROM categories ORDER BY position, id')->fetchAll(PDO::FETCH_COLUMN));
    $i = array_search($id, $list, true);
    $j = $i + ($dir < 0 ? -1 : 1);
    if ($i === false || $j < 0 || $j >= count($list)) return;
    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
    foreach ($list as $pos => $cid) q('UPDATE categories SET position = ? WHERE id = ?', [$pos + 1, $cid]);
}

/* ---------- Fotos ---------- */

function uploads_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function remove_upload(string $rel): void
{
    $f = dirname(__DIR__) . '/' . $rel;
    if (str_starts_with($rel, 'uploads/') && is_file($f)) @unlink($f);
}

function store_photo(array $file, int $dishId): string
{
    return store_image($file, 'plato-' . $dishId, 1200);
}

function store_image(array $file, string $prefix, int $max): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new ValidationError($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'La foto pesa demasiado' : 'No se pudo subir la foto');
    }
    if ($file['size'] > 12 * 1024 * 1024) throw new ValidationError('La foto pesa demasiado (máx. 12 MB)');
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new ValidationError('La foto tiene que ser JPG, PNG o WEBP');
    }
    $name = 'uploads/' . $prefix . '-' . bin2hex(random_bytes(4)) . '.jpg';
    $dest = dirname(__DIR__) . '/' . $name;
    uploads_dir();

    if (!function_exists('imagecreatetruecolor')) {
        if ($info[2] !== IMAGETYPE_JPEG) throw new ValidationError('Sube la foto en JPG');
        move_uploaded_file($file['tmp_name'], $dest);
        return $name;
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
    };
    if (!$src) throw new ValidationError('No se pudo leer la foto');

    // Corrige el giro de las fotos hechas con el móvil
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }
    $w = imagesx($src);
    $hgt = imagesy($src);
    $scale = min(1, $max / max($w, $hgt));
    $nw = (int)round($w * $scale);
    $nh = (int)round($hgt * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    imagejpeg($dst, $dest, 82);
    return $name;
}

/* ---------- Fotos de la web (portada, nosotros, galería) ---------- */

const WEB_PHOTO_SLOTS = [
    'hero'   => 'Portada',
    'sala'   => 'Nosotros: foto grande',
    'cocina' => 'Nosotros: foto pequeña',
    'barra'  => 'Galería 1',
    'plato'  => 'Galería 2',
    'coctel' => 'Galería 3',
];

function web_photo_set(string $key, ?array $file): array
{
    if (!isset(WEB_PHOTO_SLOTS[$key])) throw new ValidationError('Foto no válida');
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new ValidationError('Elige una foto');
    $photos = settings()['photos'];
    $path = store_image($file, 'web-' . $key, $key === 'hero' ? 2200 : 1600);
    if (!empty($photos[$key])) remove_upload($photos[$key]);
    $photos[$key] = $path;
    web_photos_save($photos);
    return web_photos_info();
}

function web_photo_reset(string $key): array
{
    $photos = settings()['photos'];
    if (!empty($photos[$key])) remove_upload($photos[$key]);
    unset($photos[$key]);
    web_photos_save($photos);
    return web_photos_info();
}

function web_photos_save(array $photos): void
{
    q("DELETE FROM settings WHERE k = 'photos'");
    q("INSERT INTO settings (k, v) VALUES ('photos', ?)", [json_encode($photos, JSON_UNESCAPED_SLASHES)]);
    settings(true);
}

/** Para el panel: cada hueco con su nombre, la foto actual y si es propia. */
function web_photos_info(): array
{
    require_once __DIR__ . '/tema.php';
    $own = settings()['photos'];
    $o = [];
    foreach (WEB_PHOTO_SLOTS as $k => $label) {
        $url = photo($k, 600);
        if (!preg_match('#^https?://#', $url)) $url = base_url() . '/' . $url;
        $o[] = ['key' => $k, 'label' => $label, 'url' => $url, 'custom' => !empty($own[$k])];
    }
    return $o;
}

/* ---------- Menú del día ---------- */

/**
 * El menú del día: un precio cerrado con varios apartados (primero, segundo, postre, café…) y en cada uno
 * los platos a elegir. Un plato puede llevar suplemento («+5 €»). Se activa y desactiva desde la app.
 */
function daily_menu(): array
{
    $m = (array)(settings()['daily_menu'] ?? []);
    return [
        'on'      => (bool)($m['on'] ?? false),
        'title'   => (string)($m['title'] ?? 'Menú del día') ?: 'Menú del día',
        'price'   => (float)($m['price'] ?? 0),
        'days'    => array_values(array_map('intval', (array)($m['days'] ?? [1, 2, 3, 4, 5]))),
        'note'    => (string)($m['note'] ?? ''),
        'courses' => array_values(array_map(fn($c) => [
            'name'   => (string)($c['name'] ?? ''),
            'dishes' => array_values(array_map(fn($d) => [
                'name'  => (string)($d['name'] ?? ''),
                'desc'  => (string)($d['desc'] ?? ''),
                'extra' => (float)($d['extra'] ?? 0),
            ], (array)($c['dishes'] ?? []))),
        ], (array)($m['courses'] ?? [
            ['name' => 'Primero', 'dishes' => []],
            ['name' => 'Segundo', 'dishes' => []],
            ['name' => 'Postre', 'dishes' => []],
        ]))),
    ];
}

function daily_menu_save(array $in): array
{
    $money = function ($v, string $what): float {
        $v = round((float)str_replace(',', '.', (string)$v), 2);
        if ($v < 0 || $v > 9999) throw new ValidationError('Precio no válido: ' . $what);
        return $v;
    };
    $txt = fn($v, int $max) => mb_substr(trim((string)$v), 0, $max);
    $m = [
        'on'    => filter_var($in['on'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'title' => $txt($in['title'] ?? '', 80) ?: 'Menú del día',
        'price' => $money($in['price'] ?? 0, 'menú'),
        'days'  => array_values(array_unique(array_filter(array_map('intval', (array)($in['days'] ?? [])), fn($d) => $d >= 1 && $d <= 7))),
        'note'  => $txt($in['note'] ?? '', 300),
        'courses' => [],
    ];
    sort($m['days']);
    foreach (array_slice((array)($in['courses'] ?? []), 0, 12) as $c) {
        $name = $txt($c['name'] ?? '', 60);
        $dishes = [];
        foreach (array_slice((array)($c['dishes'] ?? []), 0, 30) as $d) {
            $dn = $txt($d['name'] ?? '', 120);
            if ($dn === '') continue;
            $dishes[] = ['name' => $dn, 'desc' => $txt($d['desc'] ?? '', 200), 'extra' => $money($d['extra'] ?? 0, $dn)];
        }
        if ($name === '' && !$dishes) continue;
        if ($name === '') throw new ValidationError('Ponle nombre a cada apartado del menú (Primero, Segundo…)');
        $m['courses'][] = ['name' => $name, 'dishes' => $dishes];
    }
    if ($m['on']) {
        if ($m['price'] <= 0) throw new ValidationError('Pon el precio del menú para activarlo');
        if (!array_filter($m['courses'], fn($c) => $c['dishes'])) throw new ValidationError('Añade al menos un plato para activar el menú');
        if (!$m['days']) throw new ValidationError('Elige qué días se sirve el menú');
    }
    q("DELETE FROM settings WHERE k = 'daily_menu'");
    q("INSERT INTO settings (k, v) VALUES ('daily_menu', ?)", [json_encode($m, JSON_UNESCAPED_UNICODE)]);
    settings(true);
    return daily_menu();
}

/** Solo activar o desactivar (el interruptor de la app). */
function daily_menu_toggle(bool $on): array
{
    return daily_menu_save(['on' => $on] + daily_menu());
}

/**
 * El menú para la web, traducido, o null si está desactivado.
 * 'today' dice si hoy se sirve y 'days_text' los días («de lunes a viernes»).
 */
function daily_menu_public(): ?array
{
    $m = daily_menu();
    if (!$m['on']) return null;
    $m['courses'] = array_values(array_filter($m['courses'], fn($c) => $c['dishes']));
    if (!$m['courses']) return null;
    $m['title'] = tr($m['title']);
    $m['note'] = tr($m['note']);
    foreach ($m['courses'] as &$c) {
        $c['name'] = tr($c['name']);
        foreach ($c['dishes'] as &$d) { $d['name'] = tr($d['name']); $d['desc'] = tr($d['desc']); }
        unset($d);
    }
    unset($c);
    $m['today'] = in_array((int)date('N'), $m['days'], true) && !in_array(today(), settings()['closed_dates'], true);
    $m['days_text'] = days_text($m['days']);
    return $m;
}

/** «de lunes a viernes», «todos los días», «martes, jueves y sábado». */
function days_text(array $days): string
{
    sort($days);
    if (count($days) === 7) return t('todos los días');
    if (!$days) return '';
    $consec = count($days) >= 3 && $days === range($days[0], end($days));
    if ($consec) return t('de %s a %s', day_name($days[0]), day_name(end($days)));
    $names = array_map('day_name', $days);
    $last = array_pop($names);
    return $names ? implode(', ', $names) . ' ' . t('y') . ' ' . $last : $last;
}

/** 14,50 € → «14,50»; 15 → «15». */
function euros(float $p): string
{
    return fmod($p, 1.0) == 0.0 ? number_format($p, 0, ',', '.') : number_format($p, 2, ',', '.');
}

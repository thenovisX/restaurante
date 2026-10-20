<?php
declare(strict_types=1);

/** Los 14 alérgenos de declaración obligatoria (Reglamento UE 1169/2011). */
const ALLERGENS = [
    'gluten'      => ['Gluten', 'Gl'],
    'crustaceos'  => ['Crustáceos', 'Cr'],
    'huevos'      => ['Huevos', 'Hu'],
    'pescado'     => ['Pescado', 'Pe'],
    'cacahuetes'  => ['Cacahuetes', 'Ca'],
    'soja'        => ['Soja', 'So'],
    'lacteos'     => ['Lácteos', 'La'],
    'frutos'      => ['Frutos de cáscara', 'Fc'],
    'apio'        => ['Apio', 'Ap'],
    'mostaza'     => ['Mostaza', 'Mo'],
    'sesamo'      => ['Sésamo', 'Se'],
    'sulfitos'    => ['Sulfitos', 'Su'],
    'altramuces'  => ['Altramuces', 'Al'],
    'moluscos'    => ['Moluscos', 'Ml'],
];

function allergen_list(): array
{
    $o = [];
    foreach (ALLERGENS as $k => [$name, $abbr]) $o[] = ['key' => $k, 'name' => $name, 'abbr' => $abbr];
    return $o;
}

function dish_out(array $d): array
{
    return [
        'id' => (int)$d['id'], 'category_id' => (int)$d['category_id'], 'name' => $d['name'],
        'description' => $d['description'], 'price' => (float)$d['price'],
        'allergens' => $d['allergens'] === '' ? [] : explode(',', $d['allergens']),
        'photo' => $d['photo'] ? base_url() . '/' . $d['photo'] : '',
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
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new ValidationError($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'La foto pesa demasiado' : 'No se pudo subir la foto');
    }
    if ($file['size'] > 12 * 1024 * 1024) throw new ValidationError('La foto pesa demasiado (máx. 12 MB)');
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new ValidationError('La foto tiene que ser JPG, PNG o WEBP');
    }
    $name = 'uploads/plato-' . $dishId . '-' . bin2hex(random_bytes(4)) . '.jpg';
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
    $scale = min(1, 1200 / max($w, $hgt));
    $nw = (int)round($w * $scale);
    $nh = (int)round($hgt * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    imagejpeg($dst, $dest, 82);
    return $name;
}

<?php
declare(strict_types=1);

/* ---------- Configuración y base de datos ---------- */

class ValidationError extends RuntimeException {}

function cfg(): array
{
    static $c = null;
    if ($c === null) {
        $f = dirname(__DIR__) . '/config.php';
        $c = is_file($f) ? (require $f) : [];
    }
    return $c;
}

function installed(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!cfg()) return $ok = false;
    try {
        q('SELECT 1 FROM settings LIMIT 1');
    } catch (Throwable $e) {
        return $ok = false;
    }
    ensure_schema();
    purge_old_reservations();
    return $ok = true;
}

/**
 * Borra los datos personales de las reservas 24 horas después de la hora reservada.
 * Se queda el día, la hora, las personas y el estado (para el calendario), pero sin nombre,
 * teléfono, email ni notas. Se ejecuta sola como mucho una vez cada 15 minutos.
 */
const PURGE_HOURS = 24;
function purge_old_reservations(bool $force = false): int
{
    $last = (int)(q("SELECT v FROM settings WHERE k = '_purge'")->fetchColumn() ?: 0);
    if (!$force && $last > time() - 900) return 0;
    q("DELETE FROM settings WHERE k = '_purge'");
    q("INSERT INTO settings (k, v) VALUES ('_purge', ?)", [(string)time()]);
    $cut = time() - PURGE_HOURS * 3600;
    $d = date('Y-m-d', $cut);
    $t = date('H:i', $cut);
    // Cuentas que nunca se activaron (el enlace caduca a las 48 h): fuera a los 7 días
    q('DELETE FROM customers WHERE verified_at IS NULL AND created_at < ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
    // Registro de consentimientos: 3 años (plazo de prescripción de las infracciones de la LOPDGDD)
    q('DELETE FROM audit_log WHERE at < ?', [date('Y-m-d H:i:s', time() - 3 * 365 * 86400)]);
    // Reservas borradas desde el panel: se guardaban solo para que el enlace dijera «cancelada»; fuera al pasar
    q('DELETE FROM reservations WHERE deleted = 1 AND (rdate < ? OR (rdate = ? AND rtime <= ?))', [$d, $d, $t]);
    return q("UPDATE reservations SET name = 'Reserva pasada', phone = '', email = '', email_idx = '', notes = '', ip = ''
              WHERE (rdate < ? OR (rdate = ? AND rtime <= ?)) AND (phone <> '' OR email <> '' OR notes <> '')", [$d, $d, $t])->rowCount();
}

/** Actualiza la base de datos de instalaciones anteriores sin perder datos. */
const SCHEMA_VERSION = 7;
/** Versión de las condiciones y la privacidad: se guarda con cada aceptación. Súbela si cambias los textos. */
const LEGAL_VERSION = '2026-10-22';
function ensure_schema(): void
{
    $v = (int)(q("SELECT v FROM settings WHERE k = '_schema'")->fetchColumn() ?: 1);
    if ($v >= SCHEMA_VERSION) return;
    require_once __DIR__ . '/schema.php';
    $sqlite = (cfg()['db_driver'] ?? 'mysql') === 'sqlite';
    foreach (schema_sql($sqlite) as $sql) {
        try { db()->exec($sql); } catch (Throwable $e) { /* ya existe */ }
    }
    try { db()->exec('ALTER TABLE reservations ADD COLUMN customer_id INT NOT NULL DEFAULT 0'); } catch (Throwable $e) { /* ya existe */ }
    if ($v < 3) {
        // v3: datos personales cifrados + huella del email para buscar
        require_once __DIR__ . '/cifrado.php';
        foreach (['reservations', 'customers'] as $t) {
            try { db()->exec("ALTER TABLE $t ADD COLUMN email_idx VARCHAR(64) NOT NULL DEFAULT ''"); } catch (Throwable $e) { /* ya existe */ }
        }
        if (!$sqlite) {
            // Los textos cifrados ocupan más: se agrandan las columnas
            foreach ([
                "ALTER TABLE reservations MODIFY name VARCHAR(512) NOT NULL, MODIFY phone VARCHAR(255) NOT NULL, MODIFY email VARCHAR(512) NOT NULL DEFAULT ''",
                "ALTER TABLE customers MODIFY name VARCHAR(512) NOT NULL, MODIFY phone VARCHAR(255) NOT NULL DEFAULT ''",
                "ALTER TABLE customers DROP INDEX email",
                "ALTER TABLE customers MODIFY email VARCHAR(512) NOT NULL",
            ] as $sql) {
                try { db()->exec($sql); } catch (Throwable $e) { /* ya aplicado */ }
            }
        }
        foreach (['CREATE INDEX idx_res_email ON reservations (email_idx)', 'CREATE INDEX idx_cus_email ON customers (email_idx)'] as $sql) {
            try { db()->exec($sql); } catch (Throwable $e) { /* ya existe */ }
        }
        if (crypto_ready()) {
            foreach (q('SELECT id, name, phone, email, notes FROM reservations')->fetchAll() as $r) {
                q('UPDATE reservations SET name = ?, phone = ?, email = ?, notes = ?, email_idx = ? WHERE id = ?',
                    [enc($r['name']), enc($r['phone']), enc($r['email']), enc($r['notes']), email_idx(dec($r['email'])), $r['id']]);
            }
            foreach (q('SELECT id, name, phone, email FROM customers')->fetchAll() as $c) {
                q('UPDATE customers SET name = ?, phone = ?, email = ?, email_idx = ? WHERE id = ?',
                    [enc($c['name']), enc($c['phone']), enc($c['email']), email_idx(dec($c['email'])), $c['id']]);
            }
        }
    }
    if ($v < 4) {
        // v4: activación de cuentas por email (doble opt-in) y registro de auditoría
        foreach ([
            'ALTER TABLE customers ADD COLUMN verified_at DATETIME NULL',
            "ALTER TABLE customers ADD COLUMN verify_hash VARCHAR(64) NOT NULL DEFAULT ''",
            'ALTER TABLE customers ADD COLUMN verify_expires DATETIME NULL',
        ] as $sql) {
            try { db()->exec($sql); } catch (Throwable $e) { /* ya existe */ }
        }
        // Las cuentas creadas antes de este cambio se dan por activadas
        q('UPDATE customers SET verified_at = created_at WHERE verified_at IS NULL');
    }
    if ($v < 5) {
        // v5: enlace de confirmación por WhatsApp y contador de novedades
        foreach ([
            "ALTER TABLE reservations ADD COLUMN verify_token VARCHAR(255) NOT NULL DEFAULT ''",
            'ALTER TABLE reservations ADD COLUMN seq INT NOT NULL DEFAULT 0',
        ] as $sql) {
            try { db()->exec($sql); } catch (Throwable $e) { /* ya existe */ }
        }
        q('UPDATE reservations SET seq = id WHERE seq = 0');
    }
    if ($v < 6) {
        // v6: usuarios con permisos, quién cancela una reserva y reservas borradas desde el panel
        foreach ([
            "ALTER TABLE users ADD COLUMN name VARCHAR(80) NOT NULL DEFAULT ''",
            "ALTER TABLE users ADD COLUMN role VARCHAR(10) NOT NULL DEFAULT 'admin'",
            "ALTER TABLE users ADD COLUMN perms VARCHAR(255) NOT NULL DEFAULT ''",
            'ALTER TABLE users ADD COLUMN active TINYINT NOT NULL DEFAULT 1',
            "ALTER TABLE reservations ADD COLUMN cancel_by VARCHAR(12) NOT NULL DEFAULT ''",
            'ALTER TABLE reservations ADD COLUMN deleted TINYINT NOT NULL DEFAULT 0',
        ] as $sql) {
            try { db()->exec($sql); } catch (Throwable $e) { /* ya existe */ }
        }
    }
    if ($v < 7) {
        // v7: idiomas (la tabla translations la crea schema_sql) e idioma en el que reservó cada cliente
        try { db()->exec("ALTER TABLE reservations ADD COLUMN lang CHAR(2) NOT NULL DEFAULT 'es'"); } catch (Throwable $e) { /* ya existe */ }
    }
    q("DELETE FROM settings WHERE k = '_schema'");
    q("INSERT INTO settings (k, v) VALUES ('_schema', ?)", [(string)SCHEMA_VERSION]);
}

/**
 * Registro de auditoría: quién aceptó qué y cuándo, cambios de contraseña y de datos.
 * Solo se añaden filas, nunca se modifican. La IP se guarda cifrada.
 */
function audit(string $event, int $customerId = 0, string $detail = ''): void
{
    try {
        q('INSERT INTO audit_log (at, customer_id, event, detail, ip, ua) VALUES (?, ?, ?, ?, ?, ?)', [
            now(), $customerId, $event, mb_substr($detail, 0, 255),
            function_exists('enc') ? enc((string)($_SERVER['REMOTE_ADDR'] ?? '')) : '',
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('[restaurante] auditoría: ' . $e->getMessage());
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = make_pdo(cfg());
    return $pdo;
}

function make_pdo(array $c): PDO
{
    if (!$c) throw new RuntimeException('Falta config.php');
    if (($c['db_driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['db_path']);
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['db_host'], $c['db_name']);
        $pdo = new PDO($dsn, $c['db_user'], $c['db_pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

/* ---------- Ajustes del restaurante ---------- */

const SETTINGS_DEFAULTS = [
    'name'          => 'Novia',
    'tagline'       => 'Cocina de fuego, cócteles y noches largas',
    'description'   => '',
    'address'       => '',
    'city'          => '',
    'phone'         => '',
    'whatsapp'      => '',
    'email'         => '',
    'instagram'     => '',
    'facebook'      => '',
    'tiktok'        => '',
    'tripadvisor'   => '',
    'maps_url'      => '',     // enlace de Google Maps (si no, se busca la dirección)
    'ribbon'        => '',     // frases de la cinta, separadas por comas (si no, sale la frase corta)
    'footer_title'  => '¿Cenamos?',
    'photos'        => [],     // fotos propias de la web: clave => uploads/…
    'daily_menu'    => [],     // antiguo menú del día (ahora va en menus)
    'menus'         => [],     // menús con precio cerrado (ver menus_all() en carta.php); se guardan con su propia ruta
    'carta_on'      => true,   // ¿sale la carta principal en la web? (se cambia desde la pestaña Carta)
    // SEO y estadísticas (solo se cargan si el visitante acepta las cookies)
    'seo_description' => '',   // texto que sale en Google bajo el nombre
    'ga_id'         => '',     // Google Analytics 4: G-XXXXXXX
    'gtm_id'        => '',     // Google Tag Manager: GTM-XXXXXX
    'pixel_id'      => '',     // Píxel de Meta (Facebook/Instagram): solo números
    'gsc_verify'    => '',     // código de verificación de Google Search Console
    'notify_email'  => '',
    'owner_name'    => '',
    'owner_nif'     => '',
    'owner_registry'=> '',     // datos del Registro Mercantil si el titular es una sociedad (LSSI art. 10)
    // Envío de emails con una cuenta propia (Gmail u otro proveedor) en vez del correo del hosting
    // Idiomas de la web y traducción automática de la carta y los textos del restaurante
    'langs'         => ['es', 'en', 'pt', 'gl'],
    'tr_provider'   => 'mymemory',  // mymemory (gratis, sin cuenta) o google (clave de API)
    'tr_google_key' => '',          // guardada cifrada; nunca sale hacia la app
    'smtp_on'       => false,
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 465,
    'smtp_user'     => '',     // dirección de Gmail
    'smtp_pass'     => '',     // contraseña de aplicación, guardada cifrada; nunca sale hacia la app
    // Horario: día ISO (1 = lunes … 7 = domingo) => lista de franjas [inicio, fin]
    'hours'         => [
        '1' => [],
        '2' => [['13:00', '16:00'], ['20:30', '23:30']],
        '3' => [['13:00', '16:00'], ['20:30', '23:30']],
        '4' => [['13:00', '16:00'], ['20:30', '23:30']],
        '5' => [['13:00', '16:00'], ['20:30', '23:59']],
        '6' => [['13:00', '16:30'], ['20:30', '23:59']],
        '7' => [['13:00', '16:30']],
    ],
    // Horario de cocina (opcional): si está activado, las reservas se ofrecen dentro de este horario
    'kitchen_on'    => false,
    'kitchen_hours' => ['1' => [], '2' => [], '3' => [], '4' => [], '5' => [], '6' => [], '7' => []],
    'closed_dates'  => [],     // ['2026-12-25', …]
    'slot_minutes'  => 30,     // cada cuánto se puede reservar
    'capacity'      => 20,     // comensales máximos sentados a la vez
    'table_minutes' => 90,     // lo que dura una comida: una reserva ocupa sitio desde su hora hasta pasado este tiempo
    'max_people'    => 10,     // máximo por reserva online
    'min_notice'    => 60,     // minutos de antelación mínima
    'last_before'   => 60,     // última reserva X minutos antes del cierre
    'days_ahead'    => 60,     // hasta cuántos días se puede reservar
    'auto_confirm'  => false,  // ya no se usa: las reservas se confirman siempre por WhatsApp
    // Políticas de reserva (se muestran en la web y hay que aceptarlas al reservar)
    'cancel_hours'  => 0,      // ya no se usa: cancelar es gratis siempre
    'late_minutes'  => 15,     // minutos que se guarda la mesa si llegan tarde
    'policies'      => "Grupos: para más de 10 personas, escríbenos o llámanos y te preparamos un menú cerrado.\nAlergias: dínoslo al reservar; la cocina adapta los platos siempre que sea posible.\nNiños: son bienvenidos. Tenemos tronas, pídela en las notas de la reserva.\nMascotas: perros pequeños en la terraza.",
];

function settings(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    $s = SETTINGS_DEFAULTS;
    foreach (q('SELECT k, v FROM settings')->fetchAll() as $row) {
        if (!array_key_exists($row['k'], SETTINGS_DEFAULTS)) continue;
        $s[$row['k']] = json_decode($row['v'], true);
    }
    return $cache = $s;
}

function settings_save(array $values): array
{
    $clean = [];
    foreach ($values as $k => $v) {
        if (!array_key_exists($k, SETTINGS_DEFAULTS)) continue;
        $def = SETTINGS_DEFAULTS[$k];
        if (in_array($k, ['photos', 'daily_menu', 'menus', 'carta_on'], true)) continue; // van por su propia ruta
        if ($k === 'langs') {
            $clean[$k] = array_values(array_unique(array_merge(['es'], array_intersect(array_keys(LANGS), (array)$v))));
            continue;
        }
        if ($k === 'tr_provider') {
            $clean[$k] = $v === 'google' ? 'google' : 'mymemory';
            continue;
        }
        if ($k === 'tr_google_key') {
            $v = trim((string)$v);
            if ($v === '') continue;
            $clean[$k] = enc(mb_substr($v, 0, 200));
            continue;
        }
        if ($k === 'smtp_pass') {
            // Vacío = dejar la que había. Google la muestra con espacios: se quitan.
            $v = preg_replace('/\s+/', '', (string)$v);
            if ($v === '') continue;
            $clean[$k] = enc(mb_substr($v, 0, 200));
            continue;
        }
        if ($k === 'smtp_user') {
            $v = mb_strtolower(trim((string)$v));
            if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) throw new ValidationError('La dirección de Gmail no parece correcta');
            $clean[$k] = $v;
            continue;
        }
        if ($k === 'smtp_host') {
            $v = strtolower(trim((string)$v));
            if ($v !== '' && !preg_match('/^[a-z0-9.-]+$/', $v)) throw new ValidationError('El servidor de correo no es válido');
            $clean[$k] = $v ?: 'smtp.gmail.com';
            continue;
        }
        if ($k === 'hours' || $k === 'kitchen_hours') {
            $v = clean_hours($v);
        } elseif ($k === 'closed_dates') {
            $v = array_values(array_unique(array_filter((array)$v, 'is_date')));
            sort($v);
        } elseif (in_array($k, ['ga_id', 'gtm_id', 'pixel_id', 'gsc_verify'], true)) {
            // Solo letras, números y guiones: así nadie puede colar código en la web
            $v = trim((string)$v);
            if (preg_match('/content=["\']([^"\']+)/', $v, $m)) $v = $m[1]; // si pegan la etiqueta meta entera
            $v = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $v), 0, 80);
            if ($k === 'ga_id' && $v !== '' && !preg_match('/^G-[A-Z0-9]{4,}$/i', $v)) throw new ValidationError('El ID de Google Analytics empieza por G- (por ejemplo G-AB12CD34EF)');
            if ($k === 'gtm_id' && $v !== '' && !preg_match('/^GTM-[A-Z0-9]{4,}$/i', $v)) throw new ValidationError('El ID de Tag Manager empieza por GTM- (por ejemplo GTM-AB12CD3)');
            if ($k === 'pixel_id' && $v !== '' && !ctype_digit($v)) throw new ValidationError('El ID del píxel de Meta son solo números');
            $v = in_array($k, ['ga_id', 'gtm_id'], true) ? strtoupper($v) : $v;
        } elseif (is_bool($def)) {
            $v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_int($def)) {
            $v = max(0, (int)$v);
        } else {
            $v = mb_substr(trim((string)$v), 0, ['description' => 1000, 'policies' => 3000, 'seo_description' => 300, 'maps_url' => 600, 'tripadvisor' => 600, 'facebook' => 300, 'tiktok' => 300][$k] ?? 200);
        }
        $clean[$k] = $v;
    }
    if (isset($clean['slot_minutes']) && $clean['slot_minutes'] < 5) $clean['slot_minutes'] = 15;
    if (isset($clean['capacity']) && $clean['capacity'] < 1) $clean['capacity'] = 1;
    if (isset($clean['max_people']) && $clean['max_people'] < 1) $clean['max_people'] = 1;
    if (isset($clean['smtp_port']) && !in_array($clean['smtp_port'], [465, 587], true)) throw new ValidationError('El puerto tiene que ser 465 o 587');
    if (!empty($clean['smtp_on'])) {
        $cur = settings();
        if (($clean['smtp_user'] ?? $cur['smtp_user']) === '' || (($clean['smtp_pass'] ?? $cur['smtp_pass']) === '')) {
            throw new ValidationError('Para activarlo pon tu dirección de Gmail y la contraseña de aplicación');
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    foreach ($clean as $k => $v) {
        q('DELETE FROM settings WHERE k = ?', [$k]);
        q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, json_encode($v, JSON_UNESCAPED_UNICODE)]);
    }
    $pdo->commit();
    return settings(true);
}

function clean_hours($hours): array
{
    $out = [];
    for ($d = 1; $d <= 7; $d++) {
        $out[(string)$d] = [];
        foreach ((array)($hours[(string)$d] ?? $hours[$d] ?? []) as $r) {
            if (!is_array($r) || count($r) < 2) continue;
            [$a, $b] = [(string)$r[0], (string)$r[1]];
            if (!is_time($a) || !is_time($b) || $b <= $a) continue;
            $out[(string)$d][] = [$a, $b];
        }
        usort($out[(string)$d], fn($x, $y) => strcmp($x[0], $y[0]));
    }
    return $out;
}

const WEEKDAYS = ['1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '7' => 'Domingo'];

function ranges_text(array $ranges): string
{
    if (!$ranges) return t('Cerrado');
    return implode(' ' . t('y') . ' ', array_map(fn($r) => $r[0] . '–' . $r[1], $ranges));
}

/** Enlace completo a una red social a partir de lo que se escriba (usuario, @usuario o enlace). */
function social_url(string $net, string $v): string
{
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('#^https?://#i', $v)) return $v;
    $u = ltrim($v, '@');
    return match ($net) {
        'instagram' => 'https://instagram.com/' . rawurlencode($u),
        'facebook'  => 'https://facebook.com/' . rawurlencode($u),
        'tiktok'    => 'https://www.tiktok.com/@' . rawurlencode($u),
        default     => 'https://' . $v,
    };
}

/* ---------- Validación ---------- */

function is_date($v): bool
{
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $y);
}

function is_time($v): bool
{
    return is_string($v) && (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v);
}

function str_in(array $in, string $key, int $max, bool $required = false, string $label = ''): string
{
    $v = trim((string)($in[$key] ?? ''));
    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    if ($required && $v === '') throw new ValidationError(t('Falta: %s', t($label ?: $key)));
    return mb_substr($v, 0, $max);
}

/* ---------- HTTP ---------- */

function input(): array
{
    static $in = null;
    if ($in !== null) return $in;
    $in = $_POST;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $json = json_decode((string)file_get_contents('php://input'), true);
        if (is_array($json)) $in = $json + $in;
    }
    return $in;
}

function out($data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function only_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Usa POST', 405);
}

function client_ip(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . (cfg()['secret'] ?? ''));
}

/** URL pública de la raíz de la web (sin barra final). */
function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $root = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (preg_match('#/(api|panel)$#', $root)) $root = dirname($root);
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($root, '/');
}

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ---------- Correo ---------- */

/** Ajustes para la app de gestión: sin la contraseña del correo. */
function settings_admin(): array
{
    $s = settings();
    $s['smtp_pass_set'] = $s['smtp_pass'] !== '';
    $s['smtp_pass'] = '';
    $s['tr_google_key_set'] = $s['tr_google_key'] !== '';
    $s['tr_google_key'] = '';
    $s['smtp_error'] = (string)(q("SELECT v FROM settings WHERE k = '_smtp_error'")->fetchColumn() ?: '');
    return $s;
}

/** ¿Los emails salen por Gmail (Google trata los datos como proveedor de correo)? */
function mail_via_google(): bool
{
    $s = settings();
    return $s['smtp_on'] && (str_ends_with($s['smtp_host'], 'gmail.com') || preg_match('/@(gmail|googlemail)\.com$/', $s['smtp_user']));
}

/** Aviso al restaurante: siempre en español, aunque lo dispare un cliente que navega en otro idioma. */
function restaurant_mail(string $subject, string $body): bool
{
    $s = settings();
    return with_lang('es', fn() => send_mail($s['notify_email'] ?: $s['email'], $subject, $body));
}

function send_mail(string $to, string $subject, string $body, bool $throw = false): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $s = settings();
    // Identificación del remitente en todos los emails (LSSI-CE)
    $who = trim(($s['owner_name'] ?: $s['name']) . ($s['owner_nif'] ? ' · NIF ' . $s['owner_nif'] : ''));
    $addr = trim($s['address'] . ', ' . $s['city'], ', ');
    $body .= "\n\n--\n" . $who . ($addr ? "\n" . $addr : '') . "\n" . t('Este email es solo sobre tu reserva o tu cuenta: no enviamos publicidad.') . "\n"
        . t('Privacidad') . ': ' . base_url() . '/legal.php' . (lang() !== 'es' ? '?lang=' . lang() : '') . '#privacidad';
    $replyTo = filter_var($s['email'], FILTER_VALIDATE_EMAIL) ? $s['email'] : '';

    if ($s['smtp_on'] && $s['smtp_user'] !== '' && $s['smtp_pass'] !== '') {
        try {
            smtp_send($to, $subject, $body, $replyTo);
            q("DELETE FROM settings WHERE k = '_smtp_error'");
            return true;
        } catch (Throwable $e) {
            $msg = date('j/n H:i') . ' · ' . $e->getMessage();
            q("DELETE FROM settings WHERE k = '_smtp_error'");
            q("INSERT INTO settings (k, v) VALUES ('_smtp_error', ?)", [$msg]);
            error_log('[restaurante] SMTP: ' . $e->getMessage());
            if ($throw) throw new ValidationError($e->getMessage());
            // Si Gmail falla, se intenta con el correo del hosting para que el email no se pierda
        }
    }
    $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from = 'reservas@' . $host;
    $headers = [
        'From: ' . mb_encode_mimeheader($s['name']) . " <$from>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
    return @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers));
}

/**
 * Envío por SMTP con usuario y contraseña (Gmail con contraseña de aplicación, Hostinger, Outlook…).
 * Puerto 465: conexión cifrada desde el principio. Puerto 587: STARTTLS.
 */
function smtp_send(string $to, string $subject, string $body, string $replyTo = ''): void
{
    $s = settings();
    $host = $s['smtp_host'] ?: 'smtp.gmail.com';
    $port = (int)$s['smtp_port'] ?: 465;
    $user = $s['smtp_user'];
    $pass = dec($s['smtp_pass']);
    if ($pass === '') throw new RuntimeException('Falta la contraseña de aplicación');

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("No se puede conectar con $host:$port ($errstr). Puede que el hosting bloquee ese puerto: prueba con el otro (465 o 587).");
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): array {
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return [(int)substr($text, 0, 3), trim($text)];
    };
    $cmd = function (string $c, array $ok, string $label = '') use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        [$code, $text] = $read();
        if (!in_array($code, $ok, true)) throw new RuntimeException(($label ?: 'El servidor de correo') . " respondió: $text");
        return $text;
    };

    try {
        [$code, $text] = $read();
        if ($code !== 220) throw new RuntimeException("El servidor de correo no responde bien: $text");
        $me = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
        $cmd("EHLO $me", [250]);
        if ($port !== 465) {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('No se pudo cifrar la conexión con el servidor de correo');
            }
            $cmd("EHLO $me", [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334]);
        try {
            $cmd(base64_encode($pass), [235]);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Gmail no acepta el usuario o la contraseña. Usa una «contraseña de aplicación» de 16 letras, no la contraseña normal de tu cuenta de Google.');
        }
        $cmd("MAIL FROM:<$user>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251], 'La dirección de destino');
        $cmd('DATA', [354]);

        $name = settings()['name'];
        $domain = substr(strrchr($user, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . mb_encode_mimeheader($name, 'UTF-8') . " <$user>",
            "To: <$to>",
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . "@$domain>",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($replyTo !== '' && strcasecmp($replyTo, $user) !== 0) $headers[] = "Reply-To: <$replyTo>";
        $msg = implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
        $cmd($msg . "\r\n.", [250], 'Al enviar el email, el servidor');
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

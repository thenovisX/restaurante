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
    return $ok = true;
}

/** Actualiza la base de datos de instalaciones anteriores sin perder datos. */
const SCHEMA_VERSION = 2;
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
    q("DELETE FROM settings WHERE k = '_schema'");
    q("INSERT INTO settings (k, v) VALUES ('_schema', ?)", [(string)SCHEMA_VERSION]);
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
    'notify_email'  => '',
    'owner_name'    => '',
    'owner_nif'     => '',
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
    'closed_dates'  => [],     // ['2026-12-25', …]
    'slot_minutes'  => 30,     // cada cuánto se puede reservar
    'capacity'      => 20,     // comensales máximos por franja
    'max_people'    => 10,     // máximo por reserva online
    'min_notice'    => 60,     // minutos de antelación mínima
    'last_before'   => 60,     // última reserva X minutos antes del cierre
    'days_ahead'    => 60,     // hasta cuántos días se puede reservar
    'auto_confirm'  => false,  // confirmar sin revisar
    // Políticas de reserva (se muestran en la web y hay que aceptarlas al reservar)
    'cancel_hours'  => 4,      // horas antes para cancelar desde la cuenta del cliente
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
        if ($k === 'photos') continue; // las fotos van por su propia ruta
        if ($k === 'hours') {
            $v = clean_hours($v);
        } elseif ($k === 'closed_dates') {
            $v = array_values(array_unique(array_filter((array)$v, 'is_date')));
            sort($v);
        } elseif (is_bool($def)) {
            $v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
        } elseif (is_int($def)) {
            $v = max(0, (int)$v);
        } else {
            $v = mb_substr(trim((string)$v), 0, ['description' => 1000, 'policies' => 3000, 'maps_url' => 600, 'tripadvisor' => 600, 'facebook' => 300, 'tiktok' => 300][$k] ?? 200);
        }
        $clean[$k] = $v;
    }
    if (isset($clean['slot_minutes']) && $clean['slot_minutes'] < 5) $clean['slot_minutes'] = 15;
    if (isset($clean['capacity']) && $clean['capacity'] < 1) $clean['capacity'] = 1;
    if (isset($clean['max_people']) && $clean['max_people'] < 1) $clean['max_people'] = 1;

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
    if (!$ranges) return 'Cerrado';
    return implode(' y ', array_map(fn($r) => $r[0] . '–' . $r[1], $ranges));
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
    if ($required && $v === '') throw new ValidationError('Falta: ' . ($label ?: $key));
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

function send_mail(string $to, string $subject, string $body): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    $s = settings();
    $host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $from = 'reservas@' . $host;
    $headers = [
        'From: ' . mb_encode_mimeheader($s['name']) . " <$from>",
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if (filter_var($s['email'], FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $s['email'];
    @mail($to, mb_encode_mimeheader($subject), $body, implode("\r\n", $headers));
}

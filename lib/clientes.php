<?php
declare(strict_types=1);

/*
 * Cuentas de clientes de la web: registro, acceso, sus reservas y cancelación.
 * Usa una sesión PHP propia guardada en data/sesiones (protegida y fuera del alcance de otras webs del hosting).
 * La cookie solo se crea cuando alguien entra o se registra: quien solo mira la web no recibe ninguna.
 */

const CUSTOMER_COOKIE = 'cliente_sesion';
const CUSTOMER_DAYS = 60;

function customer_session(bool $create): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) return true;
    if (!$create && empty($_COOKIE[CUSTOMER_COOKIE])) return false;
    $dir = dirname(__DIR__) . '/data/sesiones';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', (string)(CUSTOMER_DAYS * 86400));
    ini_set('session.use_strict_mode', '1');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name(CUSTOMER_COOKIE);
    session_set_cookie_params([
        'lifetime' => CUSTOMER_DAYS * 86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    return session_start();
}

function current_customer(): ?array
{
    static $c = false;
    if ($c !== false) return $c;
    if (!customer_session(false) || empty($_SESSION['cid'])) return $c = null;
    $row = q('SELECT id, name, email, phone, created_at FROM customers WHERE id = ?', [(int)$_SESSION['cid']])->fetch();
    return $c = ($row ?: null);
}

function customer_out(?array $c): ?array
{
    return $c ? ['id' => (int)$c['id'], 'name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone']] : null;
}

/** Bloquea peticiones con cookie que vengan de otra web (protección CSRF). */
function same_origin_only(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        fail('Petición no permitida', 403);
    }
}

function customer_attempts_guard(): string
{
    $ip = hash('sha256', 'cliente' . client_ip());
    q('DELETE FROM login_attempts WHERE at < ?', [date('Y-m-d H:i:s', time() - 900)]);
    if ((int)q('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip])->fetchColumn() >= 10) {
        throw new ValidationError('Demasiados intentos. Espera 15 minutos.');
    }
    return $ip;
}

function customer_login_session(int $id): void
{
    customer_session(true);
    session_regenerate_id(true);
    $_SESSION['cid'] = $id;
    q('UPDATE customers SET last_login = ? WHERE id = ?', [now(), $id]);
}

function customer_register(array $in): array
{
    if (!empty($in['website'])) throw new ValidationError('No se pudo crear la cuenta');
    $name = str_in($in, 'name', 80, true, 'nombre');
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    $phone = str_in($in, 'phone', 30);
    $pass = (string)($in['password'] ?? '');
    if (mb_strlen($name) < 2) throw new ValidationError('Escribe tu nombre');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 6) throw new ValidationError('El teléfono no parece correcto');
    if (strlen($pass) < 8) throw new ValidationError('La contraseña necesita al menos 8 caracteres');
    if (empty($in['privacy'])) throw new ValidationError('Tienes que aceptar la política de privacidad');
    $ip = customer_attempts_guard();
    if (q('SELECT id FROM customers WHERE email = ?', [$email])->fetch()) {
        q('INSERT INTO login_attempts (ip, at) VALUES (?, ?)', [$ip, now()]);
        throw new ValidationError('Ya hay una cuenta con ese email. Entra con tu contraseña.');
    }
    q('INSERT INTO customers (name, email, phone, pass_hash, created_at) VALUES (?, ?, ?, ?, ?)',
        [$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), now()]);
    $id = (int)db()->lastInsertId();
    customer_login_session($id);
    // Las reservas que hizo antes con ese email pasan a su cuenta
    q('UPDATE reservations SET customer_id = ? WHERE customer_id = 0 AND email = ?', [$id, $email]);
    return customer_out(q('SELECT * FROM customers WHERE id = ?', [$id])->fetch());
}

function customer_login(array $in): array
{
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    $pass = (string)($in['password'] ?? '');
    $ip = customer_attempts_guard();
    $c = q('SELECT * FROM customers WHERE email = ?', [$email])->fetch();
    if (!$c || !password_verify($pass, $c['pass_hash'])) {
        q('INSERT INTO login_attempts (ip, at) VALUES (?, ?)', [$ip, now()]);
        throw new ValidationError('Email o contraseña incorrectos');
    }
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    customer_login_session((int)$c['id']);
    return customer_out($c);
}

function customer_logout(): void
{
    if (!customer_session(false)) return;
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(CUSTOMER_COOKIE, '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) fail('Entra en tu cuenta para continuar', 401);
    return $c;
}

function customer_update(array $c, array $in): array
{
    $name = str_in($in, 'name', 80, true, 'nombre');
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    $phone = str_in($in, 'phone', 30);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    if ($email !== $c['email'] && q('SELECT id FROM customers WHERE email = ? AND id <> ?', [$email, $c['id']])->fetch()) {
        throw new ValidationError('Ese email ya lo usa otra cuenta');
    }
    $new = (string)($in['new_password'] ?? '');
    if ($new !== '') {
        $row = q('SELECT pass_hash FROM customers WHERE id = ?', [$c['id']])->fetch();
        if (!password_verify((string)($in['password'] ?? ''), $row['pass_hash'])) throw new ValidationError('La contraseña actual no es correcta');
        if (strlen($new) < 8) throw new ValidationError('La nueva contraseña necesita al menos 8 caracteres');
        q('UPDATE customers SET pass_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $c['id']]);
    }
    q('UPDATE customers SET name = ?, email = ?, phone = ? WHERE id = ?', [$name, $email, $phone, $c['id']]);
    return customer_out(q('SELECT * FROM customers WHERE id = ?', [$c['id']])->fetch());
}

function customer_delete(array $c, array $in): void
{
    $row = q('SELECT pass_hash FROM customers WHERE id = ?', [$c['id']])->fetch();
    if (!password_verify((string)($in['password'] ?? ''), $row['pass_hash'])) throw new ValidationError('La contraseña no es correcta');
    // Las reservas se quedan para el restaurante, pero dejan de estar unidas a la cuenta
    q('UPDATE reservations SET customer_id = 0 WHERE customer_id = ?', [$c['id']]);
    q('DELETE FROM customers WHERE id = ?', [$c['id']]);
    customer_logout();
}

function can_cancel(array $r): bool
{
    if (!in_array($r['status'], RES_ACTIVE, true)) return false;
    $start = strtotime($r['date'] . ' ' . $r['time']);
    return $start - (int)settings()['cancel_hours'] * 3600 > time();
}

function customer_reservations(array $c): array
{
    $rows = q('SELECT * FROM reservations WHERE customer_id = ? ORDER BY rdate DESC, rtime DESC', [$c['id']])->fetchAll();
    $now = date('Y-m-d H:i');
    $up = $past = [];
    foreach ($rows as $row) {
        $r = res_out($row);
        $r['can_cancel'] = can_cancel($r);
        $r['when'] = fmt_date_es($r['date']) . ', ' . $r['time'];
        if ($r['date'] . ' ' . $r['time'] >= $now && in_array($r['status'], RES_ACTIVE, true)) array_unshift($up, $r);
        else $past[] = $r;
    }
    return ['upcoming' => $up, 'past' => $past, 'cancel_hours' => (int)settings()['cancel_hours']];
}

function customer_cancel(array $c, int $id): array
{
    $row = q('SELECT * FROM reservations WHERE id = ? AND customer_id = ?', [$id, $c['id']])->fetch();
    if (!$row) throw new ValidationError('No encontramos esa reserva en tu cuenta');
    $r = res_out($row);
    if (!can_cancel($r)) {
        $s = settings();
        throw new ValidationError("Las reservas se pueden cancelar online hasta {$s['cancel_hours']} horas antes. Llámanos" . ($s['phone'] ? " al {$s['phone']}" : '') . '.');
    }
    q("UPDATE reservations SET status = 'cancelada', updated_at = ? WHERE id = ?", [now(), $id]);
    $r = get_reservation($id);
    $s = settings();
    send_mail(
        $s['notify_email'] ?: $s['email'],
        "Reserva cancelada por el cliente: {$r['name']}, " . fmt_date_es($r['date']) . " {$r['time']}",
        "El cliente ha cancelado su reserva desde su cuenta.\n\nNombre: {$r['name']}\nTeléfono: {$r['phone']}\nDía: " . fmt_date_es($r['date']) . " a las {$r['time']}\nPersonas: {$r['people']}"
    );
    notify_customer($r);
    return $r;
}

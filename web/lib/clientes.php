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
    session_name(CUSTOMER_COOKIE);
    session_set_cookie_params([
        'lifetime' => CUSTOMER_DAYS * 86400, 'path' => '/', 'secure' => cookie_secure(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    return session_start();
}

function current_customer(): ?array
{
    static $c = false;
    if ($c !== false) return $c;
    if (!customer_session(false) || empty($_SESSION['cid'])) return $c = null;
    $row = q('SELECT id, name, email, phone, created_at, pass_hash FROM customers WHERE id = ?', [(int)$_SESSION['cid']])->fetch();
    // Si la contraseña cambió después de entrar (en este u otro dispositivo), la sesión deja de valer
    $pv = $row ? pass_version($row['pass_hash']) : '';
    if ($row && isset($_SESSION['pv']) && !hash_equals($_SESSION['pv'], $pv)) $row = null;
    if (!$row) { $_SESSION = []; return $c = null; }
    $_SESSION['pv'] ??= $pv;
    unset($row['pass_hash']);
    return $c = customer_plain($row);
}

/** Fila de cliente con los datos descifrados. */
function customer_plain(array $row): array
{
    foreach (['name', 'email', 'phone'] as $k) if (isset($row[$k])) $row[$k] = dec($row[$k]);
    return $row;
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

/** Huella de la contraseña guardada en la sesión: al cambiarla se cierran las demás sesiones de esa cuenta. */
function pass_version(string $hash): string
{
    return substr(hash('sha256', $hash . (cfg()['secret'] ?? '')), 0, 32);
}

function customer_attempts_guard(string $email = ''): string
{
    $ip = hash('sha256', 'cliente' . client_ip());
    if (attempts_count($ip) >= 10 || ($email !== '' && attempts_count(account_key('cliente', $email)) >= 15)) {
        throw new ValidationError('Demasiados intentos. Espera 15 minutos.');
    }
    return $ip;
}

function customer_login_session(int $id): void
{
    customer_session(true);
    session_regenerate_id(true);
    $_SESSION['cid'] = $id;
    $_SESSION['pv'] = pass_version((string)q('SELECT pass_hash FROM customers WHERE id = ?', [$id])->fetchColumn());
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
    if (strlen($pass) > 256) throw new ValidationError('La contraseña es demasiado larga');
    if (empty($in['privacy'])) throw new ValidationError('Tienes que aceptar las condiciones de uso y la política de privacidad');
    $ip = customer_attempts_guard();
    $old = q('SELECT id, verified_at FROM customers WHERE email_idx = ?', [email_idx($email)])->fetch();
    if ($old && $old['verified_at']) {
        q('INSERT INTO login_attempts (ip, at) VALUES (?, ?)', [$ip, now()]);
        throw new ValidationError('Ya hay una cuenta con ese email. Entra con tu contraseña.');
    }
    if ($old) {
        // Cuenta sin activar con ese email: se sustituye (quien la creó no demostró que el email era suyo)
        q('DELETE FROM customers WHERE id = ?', [$old['id']]);
    }
    q('INSERT INTO customers (name, email, email_idx, phone, pass_hash, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [enc($name), enc($email), email_idx($email), enc($phone), pass_hash($pass), now()]);
    $id = (int)db()->lastInsertId();
    audit('registro', $id, 'Acepta condiciones de uso y privacidad, versión ' . LEGAL_VERSION);
    send_activation($id, $email, $name);
    return ['pending' => true, 'email' => $email];
}

/** Doble opt-in: envía el enlace para activar la cuenta (válido 48 horas). */
function send_activation(int $id, string $email, string $name): void
{
    $token = bin2hex(random_bytes(24));
    q('UPDATE customers SET verify_hash = ?, verify_expires = ? WHERE id = ?', [hash('sha256', $token), date('Y-m-d H:i:s', time() + 48 * 3600), $id]);
    $url = base_url() . '/cuenta.php?activar=' . $token . '&c=' . $id;
    $s = settings();
    send_mail($email, t('Activa tu cuenta en %s', $s['name']),
        t('Hola %s:', $name) . "\n\n" . t('Para activar tu cuenta en %s abre este enlace (caduca en 48 horas):', $s['name']) . "\n\n$url\n\n" . t('Si no has creado tú esta cuenta, ignora este email y no se activará.'));
    if (!empty(cfg()['debug'])) error_log("[restaurante] enlace de activación: $url");
}

function customer_activate(int $id, string $token): bool
{
    $c = q('SELECT * FROM customers WHERE id = ?', [$id])->fetch();
    if (!$c || $c['verified_at'] || $c['verify_hash'] === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) return (bool)($c['verified_at'] ?? false);
    if (!hash_equals($c['verify_hash'], hash('sha256', $token)) || strtotime((string)$c['verify_expires']) < time()) return false;
    q("UPDATE customers SET verified_at = ?, verify_hash = '', verify_expires = NULL WHERE id = ?", [now(), $id]);
    // Ahora que el email está comprobado, sus reservas anteriores pasan a su cuenta
    q('UPDATE reservations SET customer_id = ? WHERE customer_id = 0 AND email_idx = ?', [$id, $c['email_idx']]);
    audit('email_verificado', $id);
    customer_login_session($id);
    return true;
}

/** Cambio de email: el nuevo solo se guarda cuando se abre el enlace que le llega a ese email (válido 48 horas). */
function send_email_change(int $id, string $email, string $name): void
{
    $token = bin2hex(random_bytes(24));
    q('UPDATE customers SET new_email = ?, verify_hash = ?, verify_expires = ? WHERE id = ?',
        [enc($email), hash('sha256', $token), date('Y-m-d H:i:s', time() + 48 * 3600), $id]);
    $url = base_url() . '/cuenta.php?email=' . $token . '&c=' . $id;
    $s = settings();
    send_mail($email, t('Confirma tu nuevo email en %s', $s['name']),
        t('Hola %s:', $name) . "\n\n" . t('Para usar este email en tu cuenta de %s abre este enlace (caduca en 48 horas):', $s['name']) . "\n\n$url\n\n" . t('Si no lo has pedido tú, ignora este email: tu cuenta seguirá con el email de antes.'));
    if (!empty(cfg()['debug'])) error_log("[restaurante] enlace de cambio de email: $url");
}

function customer_email_confirm(int $id, string $token): bool
{
    $c = q('SELECT * FROM customers WHERE id = ?', [$id])->fetch();
    if (!$c || !$c['verified_at'] || ($c['new_email'] ?? '') === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) return false;
    if (!hash_equals($c['verify_hash'], hash('sha256', $token)) || strtotime((string)$c['verify_expires']) < time()) return false;
    $email = dec($c['new_email']);
    if (q('SELECT id FROM customers WHERE email_idx = ? AND id <> ?', [email_idx($email), $id])->fetch()) return false;
    q("UPDATE customers SET email = ?, email_idx = ?, new_email = '', verify_hash = '', verify_expires = NULL WHERE id = ?", [enc($email), email_idx($email), $id]);
    audit('cambio_email', $id);
    return true;
}

function customer_resend(array $in): void
{
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    customer_attempts_guard();
    $c = q('SELECT * FROM customers WHERE email_idx = ?', [email_idx($email)])->fetch();
    // Respuesta igual exista o no la cuenta, para no revelar qué emails están registrados
    if ($c && !$c['verified_at']) send_activation((int)$c['id'], $email, dec($c['name']));
}

function customer_login(array $in): array
{
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    $pass = (string)($in['password'] ?? '');
    $ip = customer_attempts_guard($email);
    $c = q('SELECT * FROM customers WHERE email_idx = ?', [email_idx($email)])->fetch();
    if (!$c) pass_dummy($pass);
    if (!$c || !password_verify($pass, $c['pass_hash'])) {
        attempt_add($ip);
        attempt_add(account_key('cliente', $email));
        throw new ValidationError('Email o contraseña incorrectos');
    }
    if (pass_old($c['pass_hash'])) q('UPDATE customers SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $c['id']]);
    if (!$c['verified_at']) {
        throw new ValidationError('Tu cuenta aún no está activada. Abre el enlace que te enviamos por email (mira también en spam).');
    }
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    customer_login_session((int)$c['id']);
    return customer_out(customer_plain($c));
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
    if ($email !== $c['email'] && q('SELECT id FROM customers WHERE email_idx = ? AND id <> ?', [email_idx($email), $c['id']])->fetch()) {
        throw new ValidationError('Ese email ya lo usa otra cuenta');
    }
    $new = (string)($in['new_password'] ?? '');
    $emailChanged = $email !== $c['email'];
    if ($new !== '' || $emailChanged) {
        $row = q('SELECT pass_hash FROM customers WHERE id = ?', [$c['id']])->fetch();
        if (!password_verify((string)($in['password'] ?? ''), $row['pass_hash'])) {
            throw new ValidationError($emailChanged && $new === '' ? 'Para cambiar el email escribe tu contraseña actual (en «Cambiar contraseña»)' : 'La contraseña actual no es correcta');
        }
    }
    if ($new !== '') {
        if (strlen($new) < 8) throw new ValidationError('La nueva contraseña necesita al menos 8 caracteres');
        if (strlen($new) > 256) throw new ValidationError('La contraseña es demasiado larga');
        $hash = pass_hash($new);
        q('UPDATE customers SET pass_hash = ? WHERE id = ?', [$hash, $c['id']]);
        $_SESSION['pv'] = pass_version($hash);   // esta sesión sigue; las de otros dispositivos se cierran
        audit('cambio_contrasena', (int)$c['id']);
    }
    // El email nuevo no se usa hasta que se confirma desde ese email (si no, alguien podría quedarse con el email
    // de otra persona y recibir en su cuenta las reservas que esa persona haga con él)
    if ($emailChanged) send_email_change((int)$c['id'], $email, $name);
    if ($name !== $c['name'] || $phone !== $c['phone']) audit('cambio_datos', (int)$c['id']);
    q('UPDATE customers SET name = ?, phone = ? WHERE id = ?', [enc($name), enc($phone), $c['id']]);
    return customer_out(customer_plain(q('SELECT * FROM customers WHERE id = ?', [$c['id']])->fetch())) + ($emailChanged ? ['email_pending' => $email] : []);
}

function customer_delete(array $c, array $in): void
{
    $row = q('SELECT pass_hash FROM customers WHERE id = ?', [$c['id']])->fetch();
    if (!password_verify((string)($in['password'] ?? ''), $row['pass_hash'])) throw new ValidationError('La contraseña no es correcta');
    // Las reservas se quedan para el restaurante, pero dejan de estar unidas a la cuenta
    q('UPDATE reservations SET customer_id = 0 WHERE customer_id = ?', [$c['id']]);
    q('DELETE FROM customers WHERE id = ?', [$c['id']]);
    audit('cuenta_borrada', (int)$c['id']);
    customer_logout();
}

function can_cancel(array $r): bool
{
    if (!in_array($r['status'], RES_ACTIVE, true)) return false;
    $start = strtotime($r['date'] . ' ' . $r['time']);
    return $start > time();
}

function customer_reservations(array $c): array
{
    $rows = q('SELECT * FROM reservations WHERE customer_id = ? ORDER BY rdate DESC, rtime DESC', [$c['id']])->fetchAll();
    $now = date('Y-m-d H:i');
    $up = $past = [];
    foreach ($rows as $row) {
        $r = res_out($row);
        $r['can_cancel'] = can_cancel($r);
        $r['when'] = fmt_date_l($r['date']) . ', ' . $r['time'];
        if ($r['date'] . ' ' . $r['time'] >= $now && in_array($r['status'], RES_ACTIVE, true)) array_unshift($up, $r);
        else $past[] = $r;
    }
    return ['upcoming' => $up, 'past' => $past];
}

function customer_cancel(array $c, int $id): array
{
    $row = q('SELECT * FROM reservations WHERE id = ? AND customer_id = ?', [$id, $c['id']])->fetch();
    if (!$row) throw new ValidationError('No encontramos esa reserva en tu cuenta');
    $r = res_out($row);
    if (!can_cancel($r)) {
        $s = settings();
        throw new ValidationError($s['phone'] ? t('Esta reserva ya no se puede cancelar desde la web. Llámanos al %s.', $s['phone']) : t('Esta reserva ya no se puede cancelar desde la web. Llámanos.'));
    }
    q("UPDATE reservations SET status = 'cancelada', cancel_by = 'cliente', updated_at = ?, seq = ? WHERE id = ?", [now(), next_seq(), $id]);
    $r = get_reservation($id);
    $s = settings();
    restaurant_mail(
        "Reserva cancelada por el cliente: {$r['name']}, " . fmt_date_es($r['date']) . " {$r['time']}",
        "El cliente ha cancelado su reserva desde su cuenta.\n\nNombre: {$r['name']}\nDía: " . fmt_date_es($r['date']) . " a las {$r['time']}\nPersonas: {$r['people']}"
    );
    notify_customer($r);
    return $r;
}

/** Portabilidad (RGPD art. 20): todos los datos del cliente en un archivo JSON. */
function customer_export(array $c): array
{
    $res = array_map(function ($r) {
        $o = res_out($r);
        unset($o['id'], $o['source'], $o['customer']);
        return $o;
    }, q('SELECT * FROM reservations WHERE customer_id = ? ORDER BY rdate, rtime', [$c['id']])->fetchAll());
    $log = q('SELECT at, event, detail FROM audit_log WHERE customer_id = ? ORDER BY id', [$c['id']])->fetchAll();
    audit('exportacion_datos', (int)$c['id']);
    return [
        'generado' => date('c'),
        'responsable' => settings()['owner_name'] ?: settings()['name'],
        'cuenta' => ['nombre' => $c['name'], 'email' => $c['email'], 'telefono' => $c['phone'], 'alta' => $c['created_at']],
        'reservas' => $res,
        'registro_de_actividad' => $log,
    ];
}

/** El cliente con sesión confirma su reserva desde «Mi cuenta» (igual que con el enlace del WhatsApp). */
function customer_confirm(array $c, int $id): array
{
    $row = q('SELECT * FROM reservations WHERE id = ? AND customer_id = ?', [$id, $c['id']])->fetch();
    if (!$row || $row['status'] !== 'esperando') throw new ValidationError('Esta reserva no está pendiente de tu confirmación');
    [$state] = confirm_by_customer($id, dec($row['verify_token']));
    if ($state !== 'confirmada') throw new ValidationError('No se pudo confirmar la reserva');
    return get_reservation($id);
}

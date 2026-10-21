<?php
declare(strict_types=1);

const TOKEN_DAYS = 90;

function login(array $in): array
{
    $user = str_in($in, 'username', 60, true, 'usuario');
    $pass = (string)($in['password'] ?? '');
    $ip = client_ip();

    q('DELETE FROM login_attempts WHERE at < ?', [date('Y-m-d H:i:s', time() - 900)]);
    $fails = (int)q('SELECT COUNT(*) FROM login_attempts WHERE ip = ?', [$ip])->fetchColumn();
    if ($fails >= 10) throw new ValidationError('Demasiados intentos. Espera 15 minutos.');

    $u = q('SELECT * FROM users WHERE username = ?', [$user])->fetch();
    if (!$u || !password_verify($pass, $u['pass_hash'])) {
        q('INSERT INTO login_attempts (ip, at) VALUES (?, ?)', [$ip, now()]);
        throw new ValidationError('Usuario o contraseña incorrectos');
    }
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    q('DELETE FROM tokens WHERE last_used < ?', [date('Y-m-d H:i:s', time() - TOKEN_DAYS * 86400)]);

    $token = bin2hex(random_bytes(32));
    q('INSERT INTO tokens (token, user_id, created_at, last_used) VALUES (?, ?, ?, ?)', [hash('sha256', $token), $u['id'], now(), now()]);
    return ['token' => $token, 'user' => $u['username'], 'restaurant' => settings()['name']];
}

function request_token(): string
{
    $t = $_SERVER['HTTP_X_TOKEN'] ?? '';
    if ($t === '' && preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $t = $m[1];
    return preg_match('/^[a-f0-9]{64}$/', $t) ? $t : '';
}

function require_auth(): array
{
    $t = request_token();
    if ($t === '') fail('Inicia sesión', 401);
    $hash = hash('sha256', $t);
    $u = q('SELECT u.id, u.username, t.last_used FROM tokens t JOIN users u ON u.id = t.user_id WHERE t.token = ?', [$hash])->fetch();
    if (!$u || strtotime($u['last_used']) < time() - TOKEN_DAYS * 86400) fail('La sesión ha caducado. Vuelve a entrar.', 401);
    if (strtotime($u['last_used']) < time() - 300) q('UPDATE tokens SET last_used = ? WHERE token = ?', [now(), $hash]);
    return $u;
}

function logout(): void
{
    $t = request_token();
    if ($t) q('DELETE FROM tokens WHERE token = ?', [hash('sha256', $t)]);
}

function change_password(array $user, array $in): void
{
    $cur = (string)($in['current'] ?? '');
    $new = (string)($in['new'] ?? '');
    $u = q('SELECT * FROM users WHERE id = ?', [$user['id']])->fetch();
    if (!password_verify($cur, $u['pass_hash'])) throw new ValidationError('La contraseña actual no es correcta');
    if (strlen($new) < 8) throw new ValidationError('La nueva contraseña necesita al menos 8 caracteres');
    q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
    audit('admin_cambio_contrasena', 0, 'Usuario del panel: ' . $user['username']);
    // Cierra las demás sesiones
    q('DELETE FROM tokens WHERE user_id = ? AND token <> ?', [$user['id'], hash('sha256', request_token())]);
}

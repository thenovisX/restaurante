<?php
declare(strict_types=1);

const TOKEN_DAYS = 90;
/** Permisos de los empleados. Ver las reservas y la carta lo pueden hacer todos. */
const PERMS = [
    'reservas' => 'Gestionar reservas: aceptar, rechazar, apuntar, editar y borrar',
    'carta'    => 'Cambiar la carta',
    'ajustes'  => 'Cambiar los ajustes de la web',
];

function user_perms(array $u): array
{
    if (($u['role'] ?? 'admin') === 'admin') return array_keys(PERMS);
    return array_values(array_intersect(array_keys(PERMS), array_filter(explode(',', (string)($u['perms'] ?? '')))));
}

function user_can(array $u, string $perm): bool
{
    return in_array($perm, user_perms($u), true);
}

/** Corta la petición si el usuario no tiene ese permiso. */
function require_perm(array $u, string $perm): void
{
    if (!user_can($u, $perm)) fail('No tienes permiso para hacer esto. Pídeselo al administrador.', 403);
}

function require_admin(array $u): void
{
    if (($u['role'] ?? '') !== 'admin') fail('Solo un administrador puede hacer esto.', 403);
}

function user_out(array $u): array
{
    return ['id' => (int)$u['id'], 'username' => $u['username'], 'name' => (string)($u['name'] ?? ''), 'role' => $u['role'] ?? 'admin',
        'perms' => user_perms($u), 'active' => (bool)($u['active'] ?? 1), 'created_at' => $u['created_at'] ?? ''];
}

function users_list(): array
{
    return array_map('user_out', q('SELECT * FROM users ORDER BY role, username')->fetchAll());
}

function active_admins(int $exceptId = 0): int
{
    return (int)q("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?", [$exceptId])->fetchColumn();
}

/** Crear o editar un usuario del panel (solo administradores). */
function user_save(array $me, array $in): array
{
    $id = (int)($in['id'] ?? 0);
    $username = mb_strtolower(trim((string)($in['username'] ?? '')));
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) throw new ValidationError('El usuario tiene que tener de 3 a 40 letras sin espacios (puede llevar números, puntos y guiones)');
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 80);
    $role = ($in['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
    $perms = implode(',', array_intersect(array_keys(PERMS), (array)($in['perms'] ?? [])));
    $active = filter_var($in['active'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $pass = (string)($in['password'] ?? '');
    if ($pass !== '' && strlen($pass) < 8) throw new ValidationError('La contraseña necesita al menos 8 caracteres');
    if (strlen($pass) > 256) throw new ValidationError('La contraseña es demasiado larga');
    if (q('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])->fetch()) throw new ValidationError('Ya hay un usuario con ese nombre');

    if ($id) {
        $old = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
        if (!$old) throw new ValidationError('El usuario no existe');
        if ($old['role'] === 'admin' && ($role !== 'admin' || !$active) && !active_admins($id)) {
            throw new ValidationError('Tiene que quedar al menos un administrador activo');
        }
        q('UPDATE users SET username = ?, name = ?, role = ?, perms = ?, active = ? WHERE id = ?', [$username, $name, $role, $perms, $active, $id]);
        if ($pass !== '') q('UPDATE users SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $id]);
        // Si se desactiva o se le cambia la contraseña, se cierran sus sesiones (salvo la tuya)
        if (!$active || ($pass !== '' && $id !== (int)$me['id'])) q('DELETE FROM tokens WHERE user_id = ?', [$id]);
        audit('usuario_editado', 0, "Usuario del panel «{$username}» editado por {$me['username']}");
    } else {
        if ($pass === '') throw new ValidationError('Pon una contraseña para el usuario nuevo');
        q('INSERT INTO users (username, pass_hash, created_at, name, role, perms, active) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$username, pass_hash($pass), now(), $name, $role, $perms, $active]);
        $id = (int)db()->lastInsertId();
        audit('usuario_creado', 0, "Usuario del panel «{$username}» creado por {$me['username']}");
    }
    return user_out(q('SELECT * FROM users WHERE id = ?', [$id])->fetch());
}

function user_delete(array $me, int $id): void
{
    if ($id === (int)$me['id']) throw new ValidationError('No puedes borrar tu propio usuario');
    $u = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
    if (!$u) return;
    if ($u['role'] === 'admin' && !active_admins($id)) throw new ValidationError('Tiene que quedar al menos un administrador activo');
    q('DELETE FROM tokens WHERE user_id = ?', [$id]);
    q('DELETE FROM users WHERE id = ?', [$id]);
    audit('usuario_borrado', 0, "Usuario del panel «{$u['username']}» borrado por {$me['username']}");
}

function login(array $in): array
{
    $user = str_in($in, 'username', 60, true, 'usuario');
    $pass = (string)($in['password'] ?? '');
    $ip = client_ip();

    // Límite por IP y también por usuario (frena a quien prueba contraseñas desde muchas IP)
    $acct = account_key('panel', $user);
    if (attempts_count($ip) >= 10 || attempts_count($acct) >= 15) throw new ValidationError('Demasiados intentos. Espera 15 minutos.');

    $u = q('SELECT * FROM users WHERE username = ?', [$user])->fetch();
    if (!$u) pass_dummy($pass);
    if (!$u || !password_verify($pass, $u['pass_hash'])) {
        attempt_add($ip);
        attempt_add($acct);
        throw new ValidationError('Usuario o contraseña incorrectos');
    }
    if (pass_old($u['pass_hash'])) q('UPDATE users SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $u['id']]);
    if (isset($u['active']) && !(int)$u['active']) throw new ValidationError('Este usuario está desactivado. Habla con el administrador.');
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    q('DELETE FROM tokens WHERE last_used < ?', [date('Y-m-d H:i:s', time() - TOKEN_DAYS * 86400)]);

    $token = bin2hex(random_bytes(32));
    q('INSERT INTO tokens (token, user_id, created_at, last_used) VALUES (?, ?, ?, ?)', [hash('sha256', $token), $u['id'], now(), now()]);
    return ['token' => $token, 'user' => $u['username'], 'restaurant' => settings()['name'], 'me' => user_out($u)];
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
    $u = q('SELECT u.*, t.last_used FROM tokens t JOIN users u ON u.id = t.user_id WHERE t.token = ?', [$hash])->fetch();
    if (!$u || strtotime($u['last_used']) < time() - TOKEN_DAYS * 86400) fail('La sesión ha caducado. Vuelve a entrar.', 401);
    if (isset($u['active']) && !(int)$u['active']) fail('Este usuario está desactivado. Habla con el administrador.', 401);
    unset($u['pass_hash']);
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
    if (strlen($new) > 256) throw new ValidationError('La contraseña es demasiado larga');
    q('UPDATE users SET pass_hash = ? WHERE id = ?', [pass_hash($new), $user['id']]);
    audit('admin_cambio_contrasena', 0, 'Usuario del panel: ' . $user['username']);
    // Cierra las demás sesiones
    q('DELETE FROM tokens WHERE user_id = ? AND token <> ?', [$user['id'], hash('sha256', request_token())]);
}

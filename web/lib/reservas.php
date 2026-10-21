<?php
declare(strict_types=1);

const RES_STATUSES = ['pendiente', 'confirmada', 'rechazada', 'cancelada'];
const RES_ACTIVE = ['pendiente', 'confirmada']; // ocupan sitio

function fmt_date_es(string $date): string
{
    $ts = strtotime($date);
    $dias = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int)date('N', $ts)] . ' ' . (int)date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)];
}

/**
 * Horas disponibles de un día con las plazas libres en cada una.
 * $ignoreRules = true lo usa el panel: deja apuntar reservas aunque esté lleno o fuera de plazo.
 */
function day_slots(string $date, bool $ignoreRules = false): array
{
    if (!is_date($date)) throw new ValidationError('Fecha no válida');
    $s = settings();
    $res = ['date' => $date, 'open' => false, 'reason' => '', 'slots' => []];

    if (!$ignoreRules) {
        if ($date < today()) return ['reason' => 'Esa fecha ya ha pasado'] + $res;
        $limit = date('Y-m-d', strtotime('+' . (int)$s['days_ahead'] . ' days'));
        if ($date > $limit) return ['reason' => 'Solo se puede reservar con ' . $s['days_ahead'] . ' días de antelación como máximo'] + $res;
    }
    if (in_array($date, $s['closed_dates'], true)) return ['reason' => 'Ese día el restaurante está cerrado'] + $res;

    $ranges = $s['hours'][date('N', strtotime($date))] ?? [];
    if (!$ranges) return ['reason' => 'Ese día el restaurante cierra'] + $res;

    $booked = [];
    $ph = implode(',', array_fill(0, count(RES_ACTIVE), '?'));
    $rows = q("SELECT rtime, SUM(people) AS p FROM reservations WHERE rdate = ? AND status IN ($ph) GROUP BY rtime", array_merge([$date], RES_ACTIVE))->fetchAll();
    foreach ($rows as $r) $booked[$r['rtime']] = (int)$r['p'];

    $step = max(5, (int)$s['slot_minutes']) * 60;
    $earliest = time() + (int)$s['min_notice'] * 60;
    foreach ($ranges as [$a, $b]) {
        $t = strtotime("$date $a");
        $end = strtotime("$date $b") - (int)$s['last_before'] * 60;
        if ($end < $t) $end = $t;
        for (; $t <= $end; $t += $step) {
            if (!$ignoreRules && $t < $earliest) continue;
            $hm = date('H:i', $t);
            $res['slots'][] = ['time' => $hm, 'left' => max(0, (int)$s['capacity'] - ($booked[$hm] ?? 0))];
        }
    }
    $res['open'] = (bool)array_filter($res['slots'], fn($x) => $x['left'] > 0);
    if (!$res['open']) $res['reason'] = $res['slots'] ? 'No quedan mesas libres ese día' : 'Ya no se puede reservar para hoy';
    return $res;
}

function reservation_fields(array $in, bool $fromPanel): array
{
    $d = [
        'name'   => str_in($in, 'name', 80, true, 'nombre'),
        'phone'  => str_in($in, 'phone', 30, true, 'teléfono'),
        'email'  => str_in($in, 'email', 120),
        'rdate'  => str_in($in, 'date', 10, true, 'fecha'),
        'rtime'  => str_in($in, 'time', 5, true, 'hora'),
        'people' => (int)($in['people'] ?? 0),
        'notes'  => mb_substr(trim((string)($in['notes'] ?? '')), 0, 500),
    ];
    if (mb_strlen($d['name']) < 2) throw new ValidationError('Escribe un nombre');
    if (strlen(preg_replace('/\D/', '', $d['phone'])) < 6) throw new ValidationError('El teléfono no parece correcto');
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    if (!is_date($d['rdate'])) throw new ValidationError('Fecha no válida');
    if (!is_time($d['rtime'])) throw new ValidationError('Hora no válida');
    $max = $fromPanel ? 200 : (int)settings()['max_people'];
    if ($d['people'] < 1 || $d['people'] > $max) {
        throw new ValidationError($fromPanel ? 'Número de personas no válido' : "Para grupos de más de $max personas, llámanos por teléfono");
    }
    return $d;
}

function create_public_reservation(array $in): array
{
    if (!empty($in['website'])) throw new ValidationError('No se pudo enviar'); // trampa anti-bots
    if (empty($in['privacy'])) throw new ValidationError('Tienes que aceptar las políticas de reserva y de privacidad');

    $customer = current_customer();
    if ($customer) {
        // Con cuenta, los datos de contacto salen de la cuenta si no se escriben otros
        foreach (['name', 'email', 'phone'] as $k) if (trim((string)($in[$k] ?? '')) === '') $in[$k] = $customer[$k];
    }
    $d = reservation_fields($in, false);
    $ip = client_ip();
    $recent = (int)q('SELECT COUNT(*) FROM reservations WHERE ip = ? AND created_at > ?', [$ip, date('Y-m-d H:i:s', time() - 3600)])->fetchColumn();
    if ($recent >= 5) throw new ValidationError('Has hecho demasiadas reservas seguidas. Llámanos por teléfono.');

    $pdo = db();
    $pdo->beginTransaction();
    $slots = day_slots($d['rdate']);
    $slot = null;
    foreach ($slots['slots'] as $x) if ($x['time'] === $d['rtime']) $slot = $x;
    if (!$slot) {
        $pdo->rollBack();
        throw new ValidationError($slots['reason'] ?: 'Esa hora no está disponible');
    }
    if ($slot['left'] < $d['people']) {
        $pdo->rollBack();
        throw new ValidationError($slot['left'] > 0 ? "A esa hora solo quedan {$slot['left']} plazas" : 'Esa hora ya está completa, prueba otra');
    }

    $s = settings();
    $status = $s['auto_confirm'] ? 'confirmada' : 'pendiente';
    q('INSERT INTO reservations (name, phone, email, rdate, rtime, people, notes, status, source, ip, created_at, updated_at, customer_id)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $d['name'], $d['phone'], $d['email'], $d['rdate'], $d['rtime'], $d['people'], $d['notes'],
        $status, 'web', $ip, now(), now(), $customer ? (int)$customer['id'] : 0,
    ]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();

    $when = fmt_date_es($d['rdate']) . ' a las ' . $d['rtime'];
    send_mail(
        $s['notify_email'] ?: $s['email'],
        "Nueva reserva: {$d['name']}, {$d['people']} pers., $when",
        "Nueva reserva desde la web\n\nNombre: {$d['name']}\nTeléfono: {$d['phone']}\nEmail: {$d['email']}\n"
        . "Día: $when\nPersonas: {$d['people']}\nNotas: {$d['notes']}\n\nEstado: $status\nGestiónala desde la app."
    );
    if ($status === 'confirmada') notify_customer(get_reservation($id));

    return [
        'id' => $id,
        'status' => $status,
        'when' => $when,
        'people' => $d['people'],
        'account' => (bool)$customer,
        'message' => $status === 'confirmada'
            ? "Reserva confirmada para el $when. ¡Te esperamos!"
            : "Hemos recibido tu reserva para el $when. Te confirmaremos lo antes posible.",
    ];
}

function get_reservation(int $id): array
{
    $r = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
    if (!$r) throw new ValidationError('La reserva no existe');
    return res_out($r);
}

function res_out(array $r): array
{
    return [
        'id' => (int)$r['id'], 'name' => $r['name'], 'phone' => $r['phone'], 'email' => $r['email'],
        'date' => $r['rdate'], 'time' => $r['rtime'], 'people' => (int)$r['people'], 'notes' => $r['notes'],
        'status' => $r['status'], 'source' => $r['source'], 'created_at' => $r['created_at'],
        'customer' => (int)($r['customer_id'] ?? 0) > 0,
    ];
}

function list_reservations(string $from, string $to): array
{
    if (!is_date($from) || !is_date($to)) throw new ValidationError('Fechas no válidas');
    $rows = q('SELECT * FROM reservations WHERE rdate BETWEEN ? AND ? ORDER BY rdate, rtime, id', [$from, $to])->fetchAll();
    return array_map('res_out', $rows);
}

/** Resumen por día de un mes para el calendario. */
function month_summary(string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) throw new ValidationError('Mes no válido');
    $from = "$month-01";
    $to = date('Y-m-t', strtotime($from));
    $rows = q('SELECT rdate, status, people FROM reservations WHERE rdate BETWEEN ? AND ?', [$from, $to])->fetchAll();
    $days = [];
    foreach ($rows as $r) {
        if (!in_array($r['status'], RES_ACTIVE, true)) continue;
        $d = &$days[$r['rdate']];
        $d ??= ['count' => 0, 'people' => 0, 'pending' => 0];
        $d['count']++;
        $d['people'] += (int)$r['people'];
        if ($r['status'] === 'pendiente') $d['pending']++;
        unset($d);
    }
    return ['month' => $month, 'days' => $days, 'closed_dates' => settings()['closed_dates']];
}

/** Reservas web nuevas desde el último id que conoce la app. */
function changes(int $since): array
{
    $last = (int)q('SELECT COALESCE(MAX(id), 0) FROM reservations')->fetchColumn();
    $pending = (int)q("SELECT COUNT(*) FROM reservations WHERE status = 'pendiente' AND rdate >= ?", [today()])->fetchColumn();
    $new = [];
    if ($since > 0) {
        $new = array_map('res_out', q("SELECT * FROM reservations WHERE id > ? AND source = 'web' ORDER BY id", [$since])->fetchAll());
    }
    return ['last_id' => $last, 'pending' => $pending, 'new' => $new];
}

function set_reservation_status(int $id, string $status): array
{
    if (!in_array($status, RES_STATUSES, true)) throw new ValidationError('Estado no válido');
    $before = get_reservation($id);
    q('UPDATE reservations SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $id]);
    $after = get_reservation($id);
    if ($before['status'] !== $status) notify_customer($after);
    return $after;
}

/** Crear o editar una reserva desde el panel (p. ej. una llamada). */
function save_reservation(array $in): array
{
    $d = reservation_fields($in, true);
    day_slots($d['rdate'], true); // valida formato
    $id = (int)($in['id'] ?? 0);
    if ($id) {
        get_reservation($id);
        q('UPDATE reservations SET name=?, phone=?, email=?, rdate=?, rtime=?, people=?, notes=?, updated_at=? WHERE id=?', [
            $d['name'], $d['phone'], $d['email'], $d['rdate'], $d['rtime'], $d['people'], $d['notes'], now(), $id,
        ]);
        return get_reservation($id);
    }
    q('INSERT INTO reservations (name, phone, email, rdate, rtime, people, notes, status, source, ip, created_at, updated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
        $d['name'], $d['phone'], $d['email'], $d['rdate'], $d['rtime'], $d['people'], $d['notes'],
        'confirmada', 'panel', '', now(), now(),
    ]);
    return get_reservation((int)db()->lastInsertId());
}

function delete_reservation(int $id): void
{
    q('DELETE FROM reservations WHERE id = ?', [$id]);
}

function notify_customer(array $r): void
{
    if ($r['email'] === '' || $r['source'] !== 'web') return;
    $s = settings();
    $when = fmt_date_es($r['date']) . ' a las ' . $r['time'];
    $tel = $s['phone'] ? "\nSi necesitas cambiar algo, llámanos al {$s['phone']}." : '';
    $msgs = [
        'confirmada' => ["Reserva confirmada en {$s['name']}", "Hola {$r['name']}:\n\nTu reserva para {$r['people']} personas el $when está confirmada. ¡Te esperamos!$tel\n\n{$s['name']}\n{$s['address']}"],
        'rechazada'  => ["No podemos atender tu reserva en {$s['name']}", "Hola {$r['name']}:\n\nLo sentimos, no tenemos mesa para {$r['people']} personas el $when.$tel\n\n{$s['name']}"],
        'cancelada'  => ["Reserva cancelada en {$s['name']}", "Hola {$r['name']}:\n\nTu reserva del $when ha quedado cancelada.$tel\n\n{$s['name']}"],
    ];
    if (isset($msgs[$r['status']])) send_mail($r['email'], ...$msgs[$r['status']]);
}

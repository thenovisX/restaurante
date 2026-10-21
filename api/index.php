<?php
declare(strict_types=1);

/*
 * API del restaurante. La usan la web pública y la app de gestión.
 * Rutas: api/?r=<ruta>. Las de gestión necesitan la cabecera X-Token (se obtiene con r=login).
 */

require dirname(__DIR__) . '/lib/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Token, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!installed()) fail('La web aún no está instalada. Abre install.php en el navegador.', 503);

$r = (string)($_GET['r'] ?? '');
$in = input();
$id = (int)($in['id'] ?? $_GET['id'] ?? 0);

try {
    /* ---------- Públicas ---------- */
    switch ($r) {
        case 'ping':
            out(['restaurant' => settings()['name'], 'version' => 1]);
        case 'info':
            out(public_info());
        case 'menu':
            out(menu_data(false));
        case 'slots':
            out(day_slots((string)($_GET['date'] ?? '')));
        case 'reserve':
            only_post(); same_origin_only();
            out(create_public_reservation($in), 201);
        case 'login':
            only_post();
            out(login($in));

        /* Cuentas de clientes (cookie de sesión, solo desde la propia web) */
        case 'c_me':
            out(['customer' => customer_out(current_customer())]);
        case 'c_register':
            only_post(); same_origin_only();
            out(customer_register($in), 201);
        case 'c_login':
            only_post(); same_origin_only();
            out(customer_login($in));
        case 'c_logout':
            only_post(); same_origin_only();
            customer_logout();
            out(true);
        case 'c_resend':
            only_post(); same_origin_only();
            customer_resend($in);
            out(true);
        case 'c_export':
            $data = customer_export(require_customer());
            header('Content-Disposition: attachment; filename="mis-datos.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        case 'c_reservations':
            out(customer_reservations(require_customer()));
        case 'c_confirm':
            only_post(); same_origin_only();
            out(customer_confirm(require_customer(), $id));
        case 'c_cancel':
            only_post(); same_origin_only();
            out(customer_cancel(require_customer(), $id));
        case 'c_profile':
            only_post(); same_origin_only();
            out(customer_update(require_customer(), $in));
        case 'c_delete':
            only_post(); same_origin_only();
            customer_delete(require_customer(), $in);
            out(true);
    }

    /* ---------- Gestión (requieren sesión) ---------- */
    $user = require_auth();
    switch ($r) {
        case 'logout':
            logout();
            out(true);
        case 'me':
            out(['user' => $user['username'], 'restaurant' => settings()['name'], 'today' => today()]);

        case 'reservations':
            $from = (string)($_GET['from'] ?? today());
            out(list_reservations($from, (string)($_GET['to'] ?? $from)));
        case 'requests':
            out(list_requests());
        case 'month':
            out(month_summary((string)($_GET['month'] ?? date('Y-m'))));
        case 'changes':
            out(changes((int)($_GET['since'] ?? 0)));
        case 'slots_admin':
            out(day_slots((string)($_GET['date'] ?? ''), true));
        case 'reservation_status':
            only_post();
            out(set_reservation_status($id, (string)($in['status'] ?? '')));
        case 'reservation_save':
            only_post();
            out(save_reservation($in));
        case 'reservation_delete':
            only_post();
            delete_reservation($id);
            out(true);

        case 'admin_menu':
            out(menu_data(true));
        case 'category_save':
            only_post();
            out(save_category($in));
        case 'category_delete':
            only_post();
            delete_category($id);
            out(true);
        case 'category_move':
            only_post();
            move_category($id, (int)($in['dir'] ?? 1));
            out(true);
        case 'dish_save':
            only_post();
            out(save_dish($in, $_FILES['photo'] ?? null));
        case 'dish_toggle':
            only_post();
            out(toggle_dish($id, filter_var($in['available'] ?? false, FILTER_VALIDATE_BOOLEAN)));
        case 'dish_move':
            only_post();
            move_dish($id, (int)($in['dir'] ?? 1));
            out(true);
        case 'dish_delete':
            only_post();
            delete_dish($id);
            out(true);

        case 'settings':
            out(settings_admin() + ['allergens' => allergen_list(), 'web_photos' => web_photos_info()]);
        case 'web_photo':
            only_post();
            out(web_photo_set((string)($in['key'] ?? ''), $_FILES['photo'] ?? null));
        case 'web_photo_reset':
            only_post();
            out(web_photo_reset((string)($in['key'] ?? '')));
        case 'settings_save':
            only_post();
            settings_save($in);
            out(settings_admin());
        case 'mail_test':
            only_post();
            $to = trim((string)($in['to'] ?? '')) ?: (settings()['notify_email'] ?: settings()['email']);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new ValidationError('Pon antes el email para avisos de reservas (en Contacto)');
            $s = settings();
            if (!$s['smtp_on']) throw new ValidationError('Activa primero el envío con tu cuenta y guarda');
            send_mail($to, "Prueba de email de {$s['name']}", "¡Funciona! Los emails de la web ya salen desde {$s['smtp_user']}.\n\nA partir de ahora los clientes recibirán desde esta cuenta el enlace para activar su cuenta y los avisos de sus reservas.", true);
            out(['to' => $to]);
        case 'password':
            only_post();
            change_password($user, $in);
            out(true);
    }
    fail('Ruta desconocida', 404);
} catch (ValidationError $e) {
    fail($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('[restaurante] ' . $e);
    fail(!empty(cfg()['debug']) ? $e->getMessage() : 'Error en el servidor. Inténtalo de nuevo.', 500);
}

function public_info(): array
{
    $s = settings();
    $keys = ['name', 'tagline', 'description', 'address', 'city', 'phone', 'whatsapp', 'email', 'instagram', 'hours', 'closed_dates', 'max_people', 'days_ahead', 'late_minutes', 'policies'];
    return array_intersect_key($s, array_flip($keys)) + ['today' => today()];
}

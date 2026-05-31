<?php
// ─── Headers CORS & JSON ──────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Supprimer le préfixe /api ou /www/api selon l'environnement
$uri = preg_replace('#^(/www)?/api#', '', $uri);

// ════════════════════════════════════════════════════════════════
// ROUTES AUTH (patients)
// ════════════════════════════════════════════════════════════════
if ($uri === '/auth/login/patient' && $method === 'POST') {
    require __DIR__ . '/auth/login.php';

} elseif ($uri === '/auth/register/patient' && $method === 'POST') {
    require __DIR__ . '/auth/register.php';

} elseif ($uri === '/auth/password/forgot' && $method === 'POST') {
    require __DIR__ . '/auth/forgot_password.php';

} elseif ($uri === '/auth/password/reset' && $method === 'POST') {
    require __DIR__ . '/auth/reset_password.php';

} elseif ($uri === '/auth/fcm/update' && $method === 'POST') {
    require __DIR__ . '/auth/update_fcm.php';

// ════════════════════════════════════════════════════════════════
// ROUTES SERVICES
// ════════════════════════════════════════════════════════════════
} elseif ($uri === '/services' && $method === 'GET') {
    require __DIR__ . '/services/list.php';

} elseif (preg_match('#^/services/(\d+)/sous-services$#', $uri, $m) && $method === 'GET') {
    $_GET['service_id'] = $m[1];
    require __DIR__ . '/services/sous_services.php';

// ════════════════════════════════════════════════════════════════
// ROUTES CONSULTATIONS
// ════════════════════════════════════════════════════════════════
} elseif ($uri === '/consultations' && $method === 'GET') {
    require __DIR__ . '/consultations/history.php';

} elseif ($uri === '/consultations/scanner-qr' && $method === 'POST') {
    require __DIR__ . '/consultations/scan_qr.php';

} elseif ($uri === '/consultations/rdv-distance' && $method === 'POST') {
    require __DIR__ . '/consultations/remote_appointment.php';

} elseif (preg_match('#^/consultations/(\d+)/rang$#', $uri, $m) && $method === 'GET') {
    $_GET['consultation_id'] = $m[1];
    require __DIR__ . '/consultations/queue_status.php';

} elseif (preg_match('#^/consultations/(\d+)/annuler$#', $uri, $m) && $method === 'POST') {
    $_GET['consultation_id'] = $m[1];
    require __DIR__ . '/consultations/cancel.php';

// ════════════════════════════════════════════════════════════════
// ROUTES NOTIFICATIONS
// ════════════════════════════════════════════════════════════════
} elseif (preg_match('#^/notifications/historique/(\d+)$#', $uri, $m) && $method === 'GET') {
    $_GET['patient_id'] = $m[1];
    require __DIR__ . '/notifications/history.php';

// ════════════════════════════════════════════════════════════════
// 404
// ════════════════════════════════════════════════════════════════
} else {
    jsonError("Route introuvable : $method $uri", 'NOT_FOUND', 404);
}

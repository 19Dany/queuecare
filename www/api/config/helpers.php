<?php
// ─── Réponses JSON ────────────────────────────────────────────
function jsonSuccess($data = null, string $message = 'OK', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message]);
    exit;
}

function jsonError(string $message, string $errorCode = 'ERROR', int $httpCode = 400): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false, 'data' => null, 'message' => $message,
        'error'   => ['code' => $errorCode, 'message' => $message],
    ]);
    exit;
}

// ─── JWT HS256 sans librairie ─────────────────────────────────
function jwtEncode(array $payload): string {
    $h = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = base64url(json_encode($payload));
    $s = base64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    return "$h.$p.$s";
}

function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $expected = base64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    if (!hash_equals($expected, $s)) return null;
    $data = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    if (isset($data['exp']) && $data['exp'] < time()) return null;
    return $data;
}

function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// ─── Auth middleware ──────────────────────────────────────────
function requireAuth(): array {
    $header  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token   = str_replace('Bearer ', '', $header);
    $payload = jwtDecode($token);
    if (!$payload) jsonError('Token invalide ou expiré', 'UNAUTHORIZED', 401);
    return $payload;
}

// ─── Body JSON de la requête ──────────────────────────────────
function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

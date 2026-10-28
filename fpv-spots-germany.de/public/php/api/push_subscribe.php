<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Web-Push: Abonnement speichern
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$body   = json_decode(file_get_contents('php://input'), true) ?: [];

$csrfToken = $_SESSION['csrf_token'] ?? '';
if (!hash_equals($csrfToken, (string)($body['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungueltiger CSRF-Token.']);
    exit;
}

$endpoint = trim((string)($body['endpoint'] ?? ''));
$p256dh   = trim((string)($body['keys']['p256dh'] ?? ''));
$auth     = trim((string)($body['keys']['auth']   ?? ''));
$ua       = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

if ($endpoint === '' || $p256dh === '' || $auth === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Unvollstaendige Subscription.']);
    exit;
}

if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungueltiger Endpoint.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             user_id    = VALUES(user_id),
             p256dh     = VALUES(p256dh),
             auth       = VALUES(auth),
             user_agent = VALUES(user_agent)"
    );
    $stmt->execute([$userId, $endpoint, $p256dh, $auth, $ua]);

    try {
        $aStmt = $pdo->prepare(
            "INSERT INTO audit_logs (action, user_id, ip_address) VALUES ('push_subscribe', ?, ?)"
        );
        $ip = function_exists('client_ip') ? (string)client_ip() : '';
        $aStmt->execute([$userId, $ip !== '' ? $ip : '0.0.0.0']);
    } catch (PDOException $e) {
        error_log('push_subscribe audit: ' . $e->getMessage());
    }

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    error_log('push_subscribe.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler.']);
}

<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Web-Push: Abonnement entfernen
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
if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Endpoint fehlt.']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?");
    $stmt->execute([$endpoint, $userId]);
    echo json_encode(['ok' => true, 'removed' => $stmt->rowCount()]);
} catch (PDOException $e) {
    error_log('push_unsubscribe.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler.']);
}

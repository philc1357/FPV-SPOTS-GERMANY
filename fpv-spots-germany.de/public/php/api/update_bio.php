<?php
// =============================================================
// FPV Spots Germany – Bio-Update API
// POST /public/php/api/update_bio.php
// Body (JSON): { bio: string, csrf_token: string }
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$csrfToken = $_SESSION['csrf_token'] ?? '';
if (!hash_equals($csrfToken, $body['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungültiger CSRF-Token.']);
    exit;
}

$bio = trim($body['bio'] ?? '');

if (mb_strlen($bio, 'UTF-8') < 1 || mb_strlen($bio, 'UTF-8') > 1000) {
    http_response_code(422);
    echo json_encode(['error' => 'Beschreibung muss 1–1000 Zeichen lang sein.']);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
$stmt->execute([$bio, (int)$_SESSION['user_id']]);

echo json_encode(['success' => true]);

<?php
// =============================================================
// Spot favorisieren / Favorit entfernen (Toggle)
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

// Redirect-Ziel: nur relative Pfade auf eigene Seite zulassen (Schutz vor Open Redirect).
// FILTER_SANITIZE_URL entfernt nur ungültige Zeichen, validiert aber NICHT den Host.
$redirectInput = $_POST['redirect'] ?? '';
$redirect = (preg_match('#^/[a-zA-Z0-9_\-/]*\.php(\?[a-zA-Z0-9_=&%\-]*)?(#[a-zA-Z0-9_\-]*)?$#', $redirectInput))
    ? $redirectInput
    : '/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $redirect);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$spotId = (int)($_POST['spot_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($spotId <= 0) {
    header('Location: ' . $redirect);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT 1 FROM spot_favorites WHERE user_id = ? AND spot_id = ?");
    $stmt->execute([$userId, $spotId]);

    if ($stmt->fetchColumn()) {
        $pdo->prepare("DELETE FROM spot_favorites WHERE user_id = ? AND spot_id = ?")->execute([$userId, $spotId]);
    } else {
        $pdo->prepare("INSERT INTO spot_favorites (user_id, spot_id) VALUES (?, ?)")->execute([$userId, $spotId]);
    }
} catch (PDOException $e) {
    error_log('favorite_submit.php error: ' . $e->getMessage());
}

header('Location: ' . $redirect);
exit;

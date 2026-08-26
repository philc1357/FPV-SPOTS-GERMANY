<?php
declare(strict_types=1);
// =============================================================
// Verbesserungsvorschlag erstellen
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/analytics.php';   // PRG-Marker fuer die Reichweitenmessung

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$body   = trim($_POST['body'] ?? '');
$userId = (int)$_SESSION['user_id'];

if (strlen($body) < 3 || strlen($body) > 1000) {
    header('Location: /public/php/kritik.php');
    exit;
}

// Nur ein tatsaechlich gespeicherter Eintrag soll spaeter ein
// Ereignis ausloesen – der Redirect ist im Fehlerfall derselbe.
$erfolg = false;

try {
    $stmt = $pdo->prepare("INSERT INTO suggestions (user_id, body) VALUES (?, ?)");
    $stmt->execute([$userId, $body]);
    $erfolg = true;
} catch (PDOException $e) {
    error_log('suggestion_submit.php error: ' . $e->getMessage());
}

$ziel = '/public/php/kritik.php';
header('Location: ' . ($erfolg ? umami_prg_marker($ziel, 'vorschlag') : $ziel));
exit;

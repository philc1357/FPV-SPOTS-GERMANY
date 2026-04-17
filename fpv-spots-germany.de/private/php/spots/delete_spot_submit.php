<?php
// =============================================================
// Spot löschen – Form-Submit
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$spotId  = (int)($_POST['spot_id'] ?? 0);
$userId  = (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if ($spotId <= 0) {
    header('Location: /');
    exit;
}

// Besitzer-/Admin-Check
$stmt = $pdo->prepare("SELECT user_id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
$row = $stmt->fetch();

if (!$row || ((int)$row['user_id'] !== $userId && !$isAdmin)) {
    header('Location: /');
    exit;
}

// Bilder vom Dateisystem löschen
$stmt = $pdo->prepare("SELECT filename FROM spot_images WHERE spot_id = ?");
$stmt->execute([$spotId]);
$images = $stmt->fetchAll();

$uploadDir = __DIR__ . '/../../public/uploads/imgs/';
foreach ($images as $img) {
    $path = $uploadDir . $img['filename'];
    if (is_file($path)) {
        unlink($path);
    }
}

// Spot löschen (CASCADE entfernt Kommentare, Bewertungen, spot_images-Einträge)
$stmt = $pdo->prepare("DELETE FROM spots WHERE id = ?");
$stmt->execute([$spotId]);

header('Location: /public/php/dashboard.php');
exit;

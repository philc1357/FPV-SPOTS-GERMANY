<?php
// =============================================================
// Parkmöglichkeit bearbeiten – jeder eingeloggte Nutzer
// =============================================================
session_start();

require_once __DIR__ . '/db.php';

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

$spotId      = (int)($_POST['spot_id'] ?? 0);
$parkingInfo = trim($_POST['parking_info'] ?? '');

if ($spotId <= 0) {
    header('Location: /');
    exit;
}

// Parkinfo: leer => "Unbekannt"
if ($parkingInfo === '') {
    $parkingInfo = 'Unbekannt';
}

if (strlen($parkingInfo) > 500) {
    header("Location: /public/php/spot_detail.php?id=$spotId");
    exit;
}

// Pruefen ob Spot existiert
$stmt = $pdo->prepare("SELECT id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
if (!$stmt->fetch()) {
    header('Location: /');
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE spots SET parking_info = ?, parking_updated_by = ?, parking_updated_at = NOW() WHERE id = ?");
    $stmt->execute([$parkingInfo, $_SESSION['user_id'], $spotId]);
} catch (PDOException $e) {
    error_log('parking_info_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/spot_detail.php?id=$spotId");
exit;

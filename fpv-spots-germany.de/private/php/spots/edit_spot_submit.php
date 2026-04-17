<?php
// =============================================================
// Spot bearbeiten – Form-Submit
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/dashboard.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/php/dashboard.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$spotId      = (int)($_POST['spot_id'] ?? 0);
$userId      = (int)$_SESSION['user_id'];
$isAdmin     = !empty($_SESSION['is_admin']);
$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$spotType    = trim($_POST['spot_type'] ?? '');
$difficulty   = trim($_POST['difficulty'] ?? '');
$parkingInfo  = trim($_POST['parking_info'] ?? '');

// Parkinfo: leer oder nicht gesendet => "Unbekannt"
if ($parkingInfo === '') {
    $parkingInfo = 'Unbekannt';
}

if ($spotId <= 0) {
    header('Location: /public/php/dashboard.php');
    exit;
}

// Besitzer-/Admin-Check
$stmt = $pdo->prepare("SELECT user_id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
$row = $stmt->fetch();

if (!$row || ((int)$row['user_id'] !== $userId && !$isAdmin)) {
    header('Location: /public/php/dashboard.php');
    exit;
}

// Validierung
$allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Verein', 'Wasser', 'Sonstige'];
$allowedDiff  = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];

if (empty($name) || strlen($name) > 100) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}
if (strlen($description) > 2000) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}
if (strlen($parkingInfo) > 500) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}
if (!in_array($spotType, $allowedTypes, true) || !in_array($difficulty, $allowedDiff, true)) {
    header("Location: /public/php/edit_spot.php?id=$spotId");
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE spots SET name = ?, description = ?, spot_type = ?, difficulty = ?, parking_info = ? WHERE id = ?"
    );
    $stmt->execute([$name, $description, $spotType, $difficulty, $parkingInfo, $spotId]);
} catch (PDOException $e) {
    error_log('edit_spot_submit.php error: ' . $e->getMessage());
}

header("Location: /public/php/edit_spot.php?id=$spotId&success=1");
exit;

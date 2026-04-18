<?php
// =============================================================
// Foto-Upload für einen Spot
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

// Nur POST akzeptieren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// Authentifizierung pruefen
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// CSRF-Token pruefen
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$spotId = (int)($_POST['spot_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($spotId <= 0) {
    header('Location: /');
    exit;
}

// Spot existiert?
$stmt = $pdo->prepare("SELECT id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
if (!$stmt->fetch()) {
    header('Location: /');
    exit;
}

// Datei vorhanden?
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = 'Keine Datei hochgeladen oder Upload-Fehler.';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}

$file = $_FILES['photo'];

// Max. 5 MB
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    $_SESSION['upload_error'] = 'Die Datei ist zu gross (max. 5 MB).';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}

// Erlaubte MIME-Typen pruefen (ueber den tatsaechlichen Dateiinhalt)
$allowedMime = ['image/jpeg', 'image/png'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMime, true)) {
    $_SESSION['upload_error'] = 'Nur JPG/JPEG und PNG sind erlaubt.';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}

// Dateiendung aus dem Original-Dateinamen pruefen
$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExt = ['jpg', 'jpeg', 'png'];

if (!in_array($ext, $allowedExt, true)) {
    $_SESSION['upload_error'] = 'Nur JPG/JPEG und PNG sind erlaubt.';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}

// Bild tatsaechlich lesbar? (verhindert manipulierte Dateien)
$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    $_SESSION['upload_error'] = 'Die Datei ist kein gueltiges Bild.';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}

// Eindeutigen Dateinamen erzeugen
$newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
$uploadDir = __DIR__ . '/../../../public/uploads/imgs/';
$destination = $uploadDir . $newFilename;

// Zielverzeichnis anlegen falls noetig
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    $_SESSION['upload_error'] = 'Fehler beim Speichern der Datei.';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}

// In DB speichern
try {
    $stmt = $pdo->prepare(
        "INSERT INTO spot_images (spot_id, user_id, filename) VALUES (?, ?, ?)"
    );
    $stmt->execute([$spotId, $userId, $newFilename]);

    $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'IMAGE_UPLOADED', ?)";
    $pdo->prepare($logSql)->execute([$userId, $_SERVER['REMOTE_ADDR']]);
} catch (PDOException $e) {
    // Datei wieder loeschen wenn DB-Insert fehlschlaegt
    @unlink($destination);
    error_log('upload_submit.php error: ' . $e->getMessage());
    $_SESSION['upload_error'] = 'Datenbankfehler. Bitte erneut versuchen.';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}

header("Location: /spot_detail.php?id=$spotId");
exit;

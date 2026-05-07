<?php
declare(strict_types=1);
// =============================================================
// Einzelnes Spot-Foto löschen – Form-Submit
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$imageId = (int)($_POST['image_id'] ?? 0);
$spotId  = (int)($_POST['spot_id']  ?? 0);
$userId  = (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if ($imageId <= 0 || $spotId <= 0) {
    header('Location: /dashboard.php');
    exit;
}

// Bild + Spot-Owner laden
$stmt = $pdo->prepare(
    "SELECT si.id, si.filename, si.spot_id, si.user_id AS uploader_id, s.user_id AS spot_owner
     FROM spot_images si
     JOIN spots s ON si.spot_id = s.id
     WHERE si.id = ? AND si.spot_id = ?"
);
$stmt->execute([$imageId, $spotId]);
$row = $stmt->fetch();

// Berechtigt: Uploader des Fotos, Spot-Ersteller oder Admin
if (
    !$row || (
        (int)$row['uploader_id'] !== $userId
        && (int)$row['spot_owner'] !== $userId
        && !$isAdmin
    )
) {
    header('Location: /dashboard.php');
    exit;
}

// Datei vom Dateisystem löschen (defensiv: nur Basename)
$uploadDir = __DIR__ . '/../../../public/uploads/imgs/';
$path      = $uploadDir . basename((string)$row['filename']);
if (is_file($path)) {
    @unlink($path);
}

// DB-Eintrag löschen
$stmt = $pdo->prepare("DELETE FROM spot_images WHERE id = ?");
$stmt->execute([$imageId]);

// Audit-Log
$logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'IMAGE_DELETED', ?)";
$pdo->prepare($logSql)->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? '']);

// Redirect-Ziel via Whitelist
$redirect = $_POST['redirect'] ?? '';
if ($redirect === 'detail') {
    header('Location: /spot_detail.php?id=' . $spotId . '&photo_deleted=1#photos');
} else {
    header('Location: /edit_spot.php?id=' . $spotId . '&photo_deleted=1#photos');
}
exit;

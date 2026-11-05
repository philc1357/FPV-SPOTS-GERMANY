<?php
declare(strict_types=1);
// =============================================================
// Einzelnes Spot-Foto um 90 Grad drehen – Form-Submit
//
// Fuer Bilder, die vor dem EXIF-Orientierungs-Fix hochgeladen
// wurden und deshalb gekippt gespeichert sind. Das gedrehte Bild
// bekommt einen NEUEN Dateinamen, damit Browser-, .htaccess- und
// Service-Worker-Caches nicht die alte Version ausliefern.
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/image_utils.php';

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

$imageId   = (int)($_POST['image_id'] ?? 0);
$spotId    = (int)($_POST['spot_id']  ?? 0);
$userId    = (int)$_SESSION['user_id'];
$isAdmin   = !empty($_SESSION['is_admin']);
$direction = (string)($_POST['direction'] ?? '');

// Richtung per Whitelist: rechts = im Uhrzeigersinn
$degreesMap = ['right' => 90, 'left' => -90];
if ($imageId <= 0 || $spotId <= 0 || !isset($degreesMap[$direction])) {
    header('Location: /dashboard.php');
    exit;
}

// Redirect-Ziel via Whitelist
$redirectBase = ($_POST['redirect'] ?? '') === 'detail'
    ? '/spot_detail.php?id=' . $spotId
    : '/edit_spot.php?id=' . $spotId;

// Bild + Spot-Owner laden
$stmt = $pdo->prepare(
    "SELECT si.id, si.filename, si.user_id AS uploader_id, s.user_id AS spot_owner
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

// Rate-Limit: max. 60 Drehungen pro User und Stunde (CPU-Last durch GD)
$rl = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action = 'IMAGE_ROTATED' AND user_id = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rl->execute([$userId]);
if ((int)$rl->fetchColumn() >= 60) {
    header('Location: ' . $redirectBase . '#photos');
    exit;
}

// =============================================================
// Drehen: neue Datei + Thumbnail schreiben, DB umstellen,
// erst danach die alten Dateien entfernen
// =============================================================
$uploadDir = __DIR__ . '/../../../public/uploads/imgs/';
$oldName   = basename((string)$row['filename']);
$oldPath   = $uploadDir . $oldName;
$ext       = strtolower(pathinfo($oldName, PATHINFO_EXTENSION));

if (!is_file($oldPath) || !in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
    header('Location: ' . $redirectBase . '#photos');
    exit;
}

$newName = bin2hex(random_bytes(16)) . '.' . $ext;
$newPath = $uploadDir . $newName;

if (!rotate_image_file($oldPath, $newPath, $degreesMap[$direction])) {
    error_log('photo_rotate_submit.php: Drehen fehlgeschlagen fuer ' . $oldName);
    header('Location: ' . $redirectBase . '#photos');
    exit;
}

if (!create_thumbnail($newPath, thumb_path($newPath))) {
    error_log('photo_rotate_submit.php: Thumbnail fehlgeschlagen fuer ' . $newName);
}

try {
    $pdo->prepare("UPDATE spot_images SET filename = ? WHERE id = ?")
        ->execute([$newName, $imageId]);

    $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'IMAGE_ROTATED', ?)")
        ->execute([$userId, client_ip()]);
} catch (PDOException $e) {
    // Alten Zustand behalten, neue Dateien verwerfen
    @unlink($newPath);
    @unlink(thumb_path($newPath));
    error_log('photo_rotate_submit.php error: ' . $e->getMessage());
    header('Location: ' . $redirectBase . '#photos');
    exit;
}

@unlink($oldPath);
@unlink(thumb_path($oldPath));

header('Location: ' . $redirectBase . '&photo_rotated=1#photos');
exit;

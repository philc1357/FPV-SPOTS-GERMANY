<?php
declare(strict_types=1);
// =============================================================
// Forum – Beitrag erstellen
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/image_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forum.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /forum.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$userId = (int)$_SESSION['user_id'];
$title  = trim((string)($_POST['title'] ?? ''));
$body   = trim((string)($_POST['body']  ?? ''));

$titleLen = mb_strlen($title);
$bodyLen  = mb_strlen($body);

if ($titleLen < 3 || $titleLen > 150) {
    $_SESSION['forum_error'] = 'Die Überschrift muss zwischen 3 und 150 Zeichen lang sein.';
    header('Location: /forum.php');
    exit;
}
if ($bodyLen < 10 || $bodyLen > 5000) {
    $_SESSION['forum_error'] = 'Der Beitrag muss zwischen 10 und 5000 Zeichen lang sein.';
    header('Location: /forum.php');
    exit;
}

// Rate-Limit: max. 10 Beiträge pro Stunde
$rl = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action = 'FORUM_POST_CREATED' AND user_id = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rl->execute([$userId]);
if ((int)$rl->fetchColumn() >= 10) {
    $_SESSION['forum_error'] = 'Beitrags-Limit erreicht (10 pro Stunde).';
    header('Location: /forum.php');
    exit;
}

// Bilder einsammeln (optional, max. 4)
$uploadedFiles = [];
if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $count = count($_FILES['photos']['name']);
    if ($count > 4) {
        $_SESSION['forum_error'] = 'Maximal 4 Bilder pro Beitrag erlaubt.';
        header('Location: /forum.php');
        exit;
    }
    for ($i = 0; $i < $count; $i++) {
        $err = $_FILES['photos']['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $_SESSION['forum_error'] = 'Upload-Fehler bei einer Datei.';
            header('Location: /forum.php');
            exit;
        }
        $uploadedFiles[] = [
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'name'     => $_FILES['photos']['name'][$i],
            'size'     => $_FILES['photos']['size'][$i],
            'mime'     => '',
        ];
    }
}

if (count($uploadedFiles) > 4) {
    $_SESSION['forum_error'] = 'Maximal 4 Bilder pro Beitrag erlaubt.';
    header('Location: /forum.php');
    exit;
}

$maxSize     = 5 * 1024 * 1024;
$allowedMime = ['image/jpeg', 'image/png'];
$allowedExt  = ['jpg', 'jpeg', 'png'];
$finfo       = new finfo(FILEINFO_MIME_TYPE);

// Vorab-Validierung aller Bilder, damit kein Post entsteht falls eines ungültig ist
foreach ($uploadedFiles as &$f) {
    if ($f['size'] > $maxSize) {
        $_SESSION['forum_error'] = 'Eine Datei ist zu groß (max. 5 MB).';
        header('Location: /forum.php');
        exit;
    }
    $mime = $finfo->file($f['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        $_SESSION['forum_error'] = 'Nur JPG/JPEG und PNG sind erlaubt.';
        header('Location: /forum.php');
        exit;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $_SESSION['forum_error'] = 'Nur JPG/JPEG und PNG sind erlaubt.';
        header('Location: /forum.php');
        exit;
    }
    if (getimagesize($f['tmp_name']) === false) {
        $_SESSION['forum_error'] = 'Eine Datei ist kein gültiges Bild.';
        header('Location: /forum.php');
        exit;
    }
    $f['mime'] = $mime;
}
unset($f);

$uploadDir = __DIR__ . '/../../../public/uploads/forum/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$savedFiles = [];
$postId     = 0;

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("INSERT INTO forum_posts (user_id, title, body) VALUES (?, ?, ?)");
    $ins->execute([$userId, $title, $body]);
    $postId = (int)$pdo->lastInsertId();

    $imgIns = $pdo->prepare("INSERT INTO forum_post_images (post_id, filename) VALUES (?, ?)");

    foreach ($uploadedFiles as $f) {
        $ext         = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $uploadDir . $newFilename;

        if (!strip_exif_and_save($f['tmp_name'], $destination, $f['mime'])) {
            throw new RuntimeException('strip_exif_and_save failed');
        }
        $savedFiles[] = $destination;
        $imgIns->execute([$postId, $newFilename]);
    }

    $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'FORUM_POST_CREATED', ?)"
    )->execute([$userId, client_ip()]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($savedFiles as $path) {
        @unlink($path);
    }
    error_log('forum_post_submit.php error: ' . $e->getMessage());
    $_SESSION['forum_error'] = 'Beitrag konnte nicht gespeichert werden. Bitte erneut versuchen.';
    header('Location: /forum.php');
    exit;
}

$_SESSION['forum_success'] = 'Beitrag veröffentlicht.';
header('Location: /forum.php#post-' . $postId);
exit;

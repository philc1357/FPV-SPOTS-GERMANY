<?php
declare(strict_types=1);
// =============================================================
// Forum – Beitrag aktualisieren (nur Ersteller)
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
$postId = (int)($_POST['post_id'] ?? 0);
$title  = trim((string)($_POST['title'] ?? ''));
$body   = trim((string)($_POST['body']  ?? ''));

if ($postId <= 0) {
    header('Location: /forum.php');
    exit;
}

$editUrl = '/forum_edit.php?id=' . $postId;

// Owner-Check
$ownerStmt = $pdo->prepare("SELECT user_id FROM forum_posts WHERE id = ?");
$ownerStmt->execute([$postId]);
$ownerRow = $ownerStmt->fetch();
if (!$ownerRow || (int)$ownerRow['user_id'] !== $userId) {
    header('Location: /forum.php');
    exit;
}

// Validierung
if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
    $_SESSION['forum_error'] = 'Die Überschrift muss zwischen 3 und 150 Zeichen lang sein.';
    header('Location: ' . $editUrl);
    exit;
}
if (mb_strlen($body) < 10 || mb_strlen($body) > 5000) {
    $_SESSION['forum_error'] = 'Der Beitrag muss zwischen 10 und 5000 Zeichen lang sein.';
    header('Location: ' . $editUrl);
    exit;
}

// Rate-Limit
$rl = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action = 'FORUM_POST_UPDATED' AND user_id = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rl->execute([$userId]);
if ((int)$rl->fetchColumn() >= 30) {
    $_SESSION['forum_error'] = 'Bearbeitungs-Limit erreicht (30 pro Stunde).';
    header('Location: ' . $editUrl);
    exit;
}

// Bestehende Bilder laden
$curStmt = $pdo->prepare("SELECT id, filename FROM forum_post_images WHERE post_id = ?");
$curStmt->execute([$postId]);
$currentImages = $curStmt->fetchAll(); // [{id, filename}, ...]
$currentCount  = count($currentImages);

// Zu entfernende Bilder validieren (nur IDs, die zum Post gehören)
$removeIdsRaw = $_POST['remove_image_ids'] ?? [];
if (!is_array($removeIdsRaw)) { $removeIdsRaw = []; }
$removeIds = [];
foreach ($removeIdsRaw as $r) {
    $rid = (int)$r;
    if ($rid > 0) { $removeIds[$rid] = true; }
}
$removeFiles = [];
$validRemoveIds = [];
foreach ($currentImages as $img) {
    $iid = (int)$img['id'];
    if (isset($removeIds[$iid])) {
        $validRemoveIds[] = $iid;
        $removeFiles[]    = (string)$img['filename'];
    }
}

// Neue Uploads einsammeln
$uploadedFiles = [];
if (!empty($_FILES['new_photos']) && is_array($_FILES['new_photos']['name'])) {
    $count = count($_FILES['new_photos']['name']);
    for ($i = 0; $i < $count; $i++) {
        $err = $_FILES['new_photos']['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) { continue; }
        if ($err !== UPLOAD_ERR_OK) {
            $_SESSION['forum_error'] = 'Upload-Fehler bei einer Datei.';
            header('Location: ' . $editUrl);
            exit;
        }
        $uploadedFiles[] = [
            'tmp_name' => $_FILES['new_photos']['tmp_name'][$i],
            'name'     => $_FILES['new_photos']['name'][$i],
            'size'     => $_FILES['new_photos']['size'][$i],
            'mime'     => '',
        ];
    }
}

// Vorab-Check: Gesamtbilder ≤ 4
$finalCount = ($currentCount - count($validRemoveIds)) + count($uploadedFiles);
if ($finalCount > 4) {
    $_SESSION['forum_error'] = 'Maximal 4 Bilder pro Beitrag erlaubt (nach Bearbeitung wären es ' . $finalCount . ').';
    header('Location: ' . $editUrl);
    exit;
}

// Neue Bilder validieren
$maxSize     = 5 * 1024 * 1024;
$allowedMime = ['image/jpeg', 'image/png'];
$allowedExt  = ['jpg', 'jpeg', 'png'];
$finfo       = new finfo(FILEINFO_MIME_TYPE);

foreach ($uploadedFiles as &$f) {
    if ($f['size'] > $maxSize) {
        $_SESSION['forum_error'] = 'Eine Datei ist zu groß (max. 5 MB).';
        header('Location: ' . $editUrl);
        exit;
    }
    $mime = $finfo->file($f['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        $_SESSION['forum_error'] = 'Nur JPG/JPEG und PNG sind erlaubt.';
        header('Location: ' . $editUrl);
        exit;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $_SESSION['forum_error'] = 'Nur JPG/JPEG und PNG sind erlaubt.';
        header('Location: ' . $editUrl);
        exit;
    }
    if (getimagesize($f['tmp_name']) === false) {
        $_SESSION['forum_error'] = 'Eine Datei ist kein gültiges Bild.';
        header('Location: ' . $editUrl);
        exit;
    }
    $f['mime'] = $mime;
}
unset($f);

$uploadDir = __DIR__ . '/../../../public/uploads/forum/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$newSavedFiles = [];

try {
    $pdo->beginTransaction();

    $upd = $pdo->prepare("UPDATE forum_posts SET title = ?, body = ? WHERE id = ? AND user_id = ?");
    $upd->execute([$title, $body, $postId, $userId]);

    if (!empty($validRemoveIds)) {
        $placeholders = implode(',', array_fill(0, count($validRemoveIds), '?'));
        $delImg = $pdo->prepare(
            "DELETE FROM forum_post_images WHERE post_id = ? AND id IN ($placeholders)"
        );
        $delImg->execute(array_merge([$postId], $validRemoveIds));
    }

    if (!empty($uploadedFiles)) {
        $imgIns = $pdo->prepare("INSERT INTO forum_post_images (post_id, filename) VALUES (?, ?)");
        foreach ($uploadedFiles as $f) {
            $ext         = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = $uploadDir . $newFilename;
            if (!strip_exif_and_save($f['tmp_name'], $destination, $f['mime'])) {
                throw new RuntimeException('strip_exif_and_save failed');
            }
            $newSavedFiles[] = $destination;
            $imgIns->execute([$postId, $newFilename]);
        }
    }

    $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'FORUM_POST_UPDATED', ?)"
    )->execute([$userId, client_ip()]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($newSavedFiles as $path) {
        @unlink($path);
    }
    error_log('forum_post_update_submit.php error: ' . $e->getMessage());
    $_SESSION['forum_error'] = 'Beitrag konnte nicht aktualisiert werden. Bitte erneut versuchen.';
    header('Location: ' . $editUrl);
    exit;
}

// Nach erfolgreichem Commit: gelöschte Dateien von Disk entfernen
foreach ($removeFiles as $filename) {
    $safe = basename((string)$filename);
    if ($safe !== '') {
        @unlink($uploadDir . $safe);
    }
}

$_SESSION['forum_success'] = 'Beitrag aktualisiert.';
header('Location: /forum.php#post-' . $postId);
exit;

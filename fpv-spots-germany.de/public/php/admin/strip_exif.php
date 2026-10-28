<?php
declare(strict_types=1);
// =============================================================
// Admin-Tool: EXIF-Daten aus vorhandenen Uploads entfernen
// Einmalig ausführen, danach Datei löschen.
// =============================================================
require_once __DIR__ . '/../../../private/php/core/session_init.php';
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/image_utils.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$uploadDirs = [
    'Spot-Bilder'  => __DIR__ . '/../../uploads/imgs/',
    'Forum-Bilder' => __DIR__ . '/../../uploads/forum/',
];

$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        die('CSRF-Fehler');
    }

    $results = [];
    $allowedMime = ['image/jpeg', 'image/png'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($uploadDirs as $label => $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (new DirectoryIterator($dir) as $entry) {
            if ($entry->isDot() || !$entry->isFile()) {
                continue;
            }
            $path = $entry->getPathname();
            $mime = $finfo->file($path);
            if (!in_array($mime, $allowedMime, true)) {
                continue;
            }

            $tmp = $path . '.exiftmp';
            $ok  = strip_exif_and_save($path, $tmp, $mime);

            if (!$ok) {
                $results[] = ['file' => $entry->getFilename(), 'label' => $label, 'status' => 'fehler'];
                @unlink($tmp);
                continue;
            }

            if (!rename($tmp, $path)) {
                $results[] = ['file' => $entry->getFilename(), 'label' => $label, 'status' => 'fehler'];
                @unlink($tmp);
                continue;
            }

            $results[] = ['file' => $entry->getFilename(), 'label' => $label, 'status' => 'ok'];
        }
    }
}

// Vorschau: Dateien zählen
$preview = [];
foreach ($uploadDirs as $label => $dir) {
    $count = 0;
    if (is_dir($dir)) {
        foreach (new DirectoryIterator($dir) as $entry) {
            if (!$entry->isDot() && $entry->isFile() && $entry->getExtension() !== 'htaccess') {
                $count++;
            }
        }
    }
    $preview[$label] = $count;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EXIF entfernen – Admin</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
</head>
<body class="bg-dark text-light py-5">
<div class="container" style="max-width:680px">
    <h1 class="h4 mb-1">EXIF-Metadaten entfernen</h1>
    <p class="text-secondary mb-4">Verarbeitet alle vorhandenen Bilder via GD-Roundtrip und entfernt GPS, Gerätedaten und Zeitstempel.</p>

    <?php if ($results === null): ?>
        <div class="card bg-secondary-subtle border-0 mb-4">
            <div class="card-body">
                <h2 class="h6 mb-3">Vorhandene Dateien</h2>
                <ul class="mb-0">
                    <?php foreach ($preview as $label => $count): ?>
                        <li><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>: <strong><?= $count ?></strong> Datei(en)</li>
                    <?php endforeach ?>
                </ul>
                <hr class="my-2">
                <p class="mb-1 small text-secondary">Pfade (Debug):</p>
                <ul class="small text-secondary mb-0">
                    <?php foreach ($uploadDirs as $label => $dir): ?>
                        <li><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>: <code><?= htmlspecialchars(realpath($dir) ?: $dir, ENT_QUOTES, 'UTF-8') ?></code> (existiert: <?= is_dir($dir) ? 'ja' : 'nein' ?>)</li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-warning">
                EXIF aus allen Bildern entfernen
            </button>
        </form>

    <?php else: ?>
        <?php
        $ok    = array_filter($results, fn($r) => $r['status'] === 'ok');
        $fehler = array_filter($results, fn($r) => $r['status'] === 'fehler');
        ?>
        <div class="alert alert-<?= $fehler ? 'warning' : 'success' ?>">
            <strong><?= count($ok) ?></strong> Bilder bereinigt.
            <?php if ($fehler): ?>
                <strong><?= count($fehler) ?></strong> Fehler.
            <?php endif ?>
        </div>

        <?php if ($fehler): ?>
            <h2 class="h6 mt-3">Fehlerhafte Dateien</h2>
            <ul>
                <?php foreach ($fehler as $r): ?>
                    <li><?= htmlspecialchars($r['label'] . ' / ' . $r['file'], ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>

        <p class="mt-4 text-secondary small">Du kannst diese Datei jetzt löschen: <code>public/php/admin/strip_exif.php</code></p>
    <?php endif ?>
</div>
</body>
</html>

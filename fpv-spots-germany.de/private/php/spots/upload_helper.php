<?php
declare(strict_types=1);
// =============================================================
// Gemeinsame Foto-Upload-Logik für Spots
// -------------------------------------------------------------
// Wird von upload_submit.php (Detailseite) und spot_submit.php
// (Fotos direkt beim Erstellen) genutzt, damit beide Wege exakt
// dieselbe Prüfung durchlaufen: Upload-Fehlercode, Größe, MIME +
// Endung, lesbares Bild, Auflösung, EXIF entfernen, Thumbnail.
// Setzt db.php (client_ip) und image_utils.php voraus.
// =============================================================
require_once __DIR__ . '/../core/image_utils.php';

const UPLOAD_MAX_PER_HOUR = 50;
const UPLOAD_MAX_SIZE     = 10 * 1024 * 1024; // 10 MB

// =============================================================
// Dateinamen für die Anzeige entschärfen (Ausgabe erfolgt später escaped)
// =============================================================
function upload_display_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    if ($name === '') {
        return 'Unbenannte Datei';
    }
    return mb_strlen($name) > 100 ? mb_substr($name, 0, 100) . '…' : $name;
}

// =============================================================
// $_FILES[$field][] normalisieren, leere Datei-Slots verwerfen
//
// Der Index der Rückgabe entspricht der Reihenfolge im Formular –
// spot_submit.php ordnet darüber die Highlight-Plätze zu.
// =============================================================
function upload_collect_files(string $field): array
{
    $files = [];
    if (isset($_FILES[$field]) && is_array($_FILES[$field]['name'] ?? null)) {
        $count = count($_FILES[$field]['name']);
        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'name'     => (string)($_FILES[$field]['name'][$i] ?? ''),
                'tmp_name' => (string)($_FILES[$field]['tmp_name'][$i] ?? ''),
                'size'     => (int)($_FILES[$field]['size'][$i] ?? 0),
                'error'    => (int)($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            ];
        }
    }
    return array_values(array_filter($files, static fn(array $f): bool => $f['error'] !== UPLOAD_ERR_NO_FILE));
}

// =============================================================
// Rate-Limit: verbleibendes Stundenkontingent (Admins unbegrenzt)
// =============================================================
function upload_remaining_quota(PDO $pdo, int $userId, bool $isAdmin): int
{
    if ($isAdmin) {
        return PHP_INT_MAX;
    }
    $rl = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'IMAGE_UPLOADED' AND user_id = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $rl->execute([$userId]);
    return max(0, UPLOAD_MAX_PER_HOUR - (int)$rl->fetchColumn());
}

// =============================================================
// Dateien einzeln prüfen und speichern
//
// Ungültige Dateien werden übersprungen, alle anderen gespeichert.
// Rückgabe:
//   ids          => [Dateiindex => spot_images.id] der gespeicherten Bilder
//   saved        => Anzahl gespeicherter Bilder
//   skipped      => [['name' => …, 'reason' => …], …]
//   rate_limited => Stundenlimit hat Dateien abgeschnitten
// =============================================================
function upload_store_files(PDO $pdo, int $spotId, int $userId, array $files, int $remaining): array
{
    $uploadDir = __DIR__ . '/../../../public/uploads/imgs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedMime = ['image/jpeg', 'image/png'];
    $allowedExt  = ['jpg', 'jpeg', 'png'];
    $finfo       = new finfo(FILEINFO_MIME_TYPE);
    $rateLimited = false;
    $saved       = 0;
    $ids         = [];
    $skipped     = [];

    $insertImg = $pdo->prepare("INSERT INTO spot_images (spot_id, user_id, filename) VALUES (?, ?, ?)");
    $insertLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'IMAGE_UPLOADED', ?)");

    foreach ($files as $index => $file) {
        $displayName = upload_display_name($file['name']);
        $skip = static function (string $reason) use (&$skipped, $displayName): void {
            $skipped[] = ['name' => $displayName, 'reason' => $reason];
        };

        // --- Stundenlimit ---
        if ($saved >= $remaining) {
            $rateLimited = true;
            $skip('Stundenlimit erreicht (' . UPLOAD_MAX_PER_HOUR . ' Bilder pro Stunde)');
            continue;
        }

        // --- PHP-Upload-Fehler (u. a. Server-Größenlimit) ---
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $skip('Datei zu groß (max. 10 MB)');
            continue;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $skip('Upload-Fehler');
            continue;
        }

        // --- Größe ---
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $skip('Datei zu groß (max. 10 MB)');
            continue;
        }

        // --- Typ: Inhalt (MIME) und Endung ---
        $mimeType = (string)$finfo->file($file['tmp_name']);
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($mimeType, $allowedMime, true) || !in_array($ext, $allowedExt, true)) {
            $skip('Kein erlaubtes Format (nur JPG/PNG)');
            continue;
        }

        // --- Bild tatsächlich lesbar? ---
        if (getimagesize($file['tmp_name']) === false) {
            $skip('Keine gültige Bilddatei');
            continue;
        }
        if (!image_dimensions_ok($file['tmp_name'])) {
            $skip('Bild zu groß (Auflösung)');
            continue;
        }

        // --- Speichern (EXIF entfernen) + Thumbnail ---
        $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $uploadDir . $newFilename;

        if (!strip_exif_and_save($file['tmp_name'], $destination, $mimeType)) {
            error_log('upload_helper.php: Speichern fehlgeschlagen fuer ' . $newFilename);
            $skip('Fehler beim Speichern');
            continue;
        }

        // Schlägt das Thumbnail fehl, bleibt der Upload gültig (Anzeige nutzt Original)
        if (!create_thumbnail($destination, thumb_path($destination))) {
            error_log('upload_helper.php: Thumbnail fehlgeschlagen fuer ' . $newFilename);
        }

        // --- DB-Eintrag + Audit-Log ---
        try {
            $insertImg->execute([$spotId, $userId, $newFilename]);
            $ids[$index] = (int)$pdo->lastInsertId();
            $insertLog->execute([$userId, client_ip()]);
            $saved++;
        } catch (PDOException $e) {
            @unlink($destination);
            @unlink(thumb_path($destination));
            unset($ids[$index]);
            error_log('upload_helper.php error: ' . $e->getMessage());
            $skip('Datenbankfehler');
        }
    }

    return [
        'ids'          => $ids,
        'saved'        => $saved,
        'skipped'      => $skipped,
        'rate_limited' => $rateLimited,
    ];
}

// =============================================================
// Upload-Ergebnis in der Session sammeln (Anzeige auf der Detailseite)
// =============================================================
function upload_store_report(int $spotId, int $saved, array $skipped, int $maxSkipped = 50): void
{
    $report = $_SESSION['upload_report'] ?? null;
    if (!is_array($report) || (int)($report['spot_id'] ?? 0) !== $spotId) {
        $report = ['spot_id' => $spotId, 'saved' => 0, 'skipped' => []];
    }
    $report['saved']  += $saved;
    $report['skipped'] = array_slice(array_merge($report['skipped'], $skipped), 0, $maxSkipped);
    $_SESSION['upload_report'] = $report;
}

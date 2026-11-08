<?php
declare(strict_types=1);
// =============================================================
// Foto-Upload für einen Spot (eine oder mehrere Dateien)
// -------------------------------------------------------------
// Feld: photos[] (mehrere Dateien). Ungültige Dateien werden
// einzeln übersprungen, alle anderen gespeichert.
// Modus:
//   - ajax=1  → JSON-Antwort (JS schickt jedes Bild einzeln)
//   - sonst   → PRG-Redirect zurück zur Spot-Seite (No-JS-Fallback)
// Limits (nicht für Admins): 10 Dateien pro Request, 50 pro Stunde.
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/image_utils.php';

const UPLOAD_MAX_FILES_PER_REQUEST = 10;
const UPLOAD_MAX_PER_HOUR          = 50;
const UPLOAD_MAX_SIZE              = 10 * 1024 * 1024; // 10 MB
const UPLOAD_REPORT_MAX_SKIPPED    = 50;

$isAjax = post_str('ajax') === '1';

// =============================================================
// Antwort-Helfer: JSON oder Redirect
// =============================================================
function upload_respond(bool $isAjax, int $spotId, array $payload, int $status = 200): never
{
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
    header($spotId > 0 ? "Location: /spot_detail.php?id=$spotId" : 'Location: /');
    exit;
}

// Harter Abbruch vor der Dateiverarbeitung (für beide Modi)
function upload_fail(bool $isAjax, int $spotId, string $message, int $status): never
{
    if (!$isAjax && $spotId > 0) {
        $_SESSION['upload_error'] = $message;
    }
    upload_respond($isAjax, $spotId, ['ok' => false, 'error' => $message, 'saved' => 0, 'skipped' => []], $status);
}

// Dateinamen für die Anzeige entschärfen (Ausgabe erfolgt später escaped)
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
// Grundprüfungen: Methode, Login, CSRF, Spot
// =============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    upload_fail($isAjax, 0, 'Nicht angemeldet.', 401);
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    if ($isAjax) {
        upload_fail(true, 0, 'Sitzung abgelaufen. Bitte Seite neu laden.', 403);
    }
    die('CSRF-Fehler');
}

$spotId  = (int)($_POST['spot_id'] ?? 0);
$userId  = (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if ($spotId <= 0) {
    upload_fail($isAjax, 0, 'Ungültiger Spot.', 400);
}

$stmt = $pdo->prepare("SELECT id FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
if (!$stmt->fetch()) {
    upload_fail($isAjax, 0, 'Spot nicht gefunden.', 404);
}

// =============================================================
// Dateien einsammeln (photos[] normalisieren)
// =============================================================
$files = [];
if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'] ?? null)) {
    $count = count($_FILES['photos']['name']);
    for ($i = 0; $i < $count; $i++) {
        $files[] = [
            'name'     => (string)($_FILES['photos']['name'][$i] ?? ''),
            'tmp_name' => (string)($_FILES['photos']['tmp_name'][$i] ?? ''),
            'size'     => (int)($_FILES['photos']['size'][$i] ?? 0),
            'error'    => (int)($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE),
        ];
    }
}
// Leere Datei-Slots (kein Datei gewählt) ignorieren
$files = array_values(array_filter($files, fn($f) => $f['error'] !== UPLOAD_ERR_NO_FILE));

if ($files === []) {
    upload_fail($isAjax, $spotId, 'Keine Datei ausgewählt oder Gesamtgröße zu hoch.', 400);
}

if (!$isAdmin && count($files) > UPLOAD_MAX_FILES_PER_REQUEST) {
    upload_fail($isAjax, $spotId, 'Maximal ' . UPLOAD_MAX_FILES_PER_REQUEST . ' Bilder pro Upload erlaubt.', 400);
}

// =============================================================
// Rate-Limit: verbleibendes Stundenkontingent (Admins unbegrenzt)
// =============================================================
$remaining = PHP_INT_MAX;
if (!$isAdmin) {
    $rl = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'IMAGE_UPLOADED' AND user_id = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $rl->execute([$userId]);
    $remaining = max(0, UPLOAD_MAX_PER_HOUR - (int)$rl->fetchColumn());
}

// =============================================================
// Dateien einzeln prüfen und speichern
// =============================================================
$uploadDir = __DIR__ . '/../../../public/uploads/imgs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedMime = ['image/jpeg', 'image/png'];
$allowedExt  = ['jpg', 'jpeg', 'png'];
$finfo       = new finfo(FILEINFO_MIME_TYPE);
$rateLimited = false;
$saved       = 0;
$skipped     = [];

$insertImg = $pdo->prepare("INSERT INTO spot_images (spot_id, user_id, filename) VALUES (?, ?, ?)");
$insertLog = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'IMAGE_UPLOADED', ?)");

foreach ($files as $file) {
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
        error_log('upload_submit.php: Speichern fehlgeschlagen fuer ' . $newFilename);
        $skip('Fehler beim Speichern');
        continue;
    }

    // Schlägt das Thumbnail fehl, bleibt der Upload gültig (Anzeige nutzt Original)
    if (!create_thumbnail($destination, thumb_path($destination))) {
        error_log('upload_submit.php: Thumbnail fehlgeschlagen fuer ' . $newFilename);
    }

    // --- DB-Eintrag + Audit-Log ---
    try {
        $insertImg->execute([$spotId, $userId, $newFilename]);
        $insertLog->execute([$userId, client_ip()]);
        $saved++;
    } catch (PDOException $e) {
        @unlink($destination);
        @unlink(thumb_path($destination));
        error_log('upload_submit.php error: ' . $e->getMessage());
        $skip('Datenbankfehler');
    }
}

// =============================================================
// Ergebnis in der Session sammeln (Anzeige nach Reload)
// =============================================================
$report = $_SESSION['upload_report'] ?? null;
if (!is_array($report) || (int)($report['spot_id'] ?? 0) !== $spotId) {
    $report = ['spot_id' => $spotId, 'saved' => 0, 'skipped' => []];
}
$report['saved']  += $saved;
$report['skipped'] = array_slice(array_merge($report['skipped'], $skipped), 0, UPLOAD_REPORT_MAX_SKIPPED);
$_SESSION['upload_report'] = $report;

upload_respond($isAjax, $spotId, [
    'ok'           => true,
    'saved'        => $saved,
    'skipped'      => $skipped,
    'rate_limited' => $rateLimited,
]);

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
require_once __DIR__ . '/upload_helper.php'; // Prüfung/Speicherung, UPLOAD_MAX_PER_HOUR, UPLOAD_MAX_SIZE

const UPLOAD_MAX_FILES_PER_REQUEST = 10;
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
// Dateien einsammeln, Stundenkontingent, prüfen und speichern
// (gemeinsame Logik in upload_helper.php)
// =============================================================
$files = upload_collect_files('photos');

if ($files === []) {
    upload_fail($isAjax, $spotId, 'Keine Datei ausgewählt oder Gesamtgröße zu hoch.', 400);
}

if (!$isAdmin && count($files) > UPLOAD_MAX_FILES_PER_REQUEST) {
    upload_fail($isAjax, $spotId, 'Maximal ' . UPLOAD_MAX_FILES_PER_REQUEST . ' Bilder pro Upload erlaubt.', 400);
}

$result = upload_store_files($pdo, $spotId, $userId, $files, upload_remaining_quota($pdo, $userId, $isAdmin));
$saved       = $result['saved'];
$skipped     = $result['skipped'];
$rateLimited = $result['rate_limited'];

// Ergebnis in der Session sammeln (Anzeige nach Reload)
upload_store_report($spotId, $saved, $skipped, UPLOAD_REPORT_MAX_SKIPPED);

upload_respond($isAjax, $spotId, [
    'ok'           => true,
    'saved'        => $saved,
    'skipped'      => $skipped,
    'rate_limited' => $rateLimited,
]);

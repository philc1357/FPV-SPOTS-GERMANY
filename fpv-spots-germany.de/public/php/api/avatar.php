<?php
declare(strict_types=1);
// =============================================================
// API: Profilbild (Avatar) des eingeloggten Nutzers
//
//   POST /api/avatar.php                 – hochladen/ersetzen
//        Felder: avatar (Datei), crop_x, crop_y, crop_size, csrf_token
//   POST /api/avatar.php  _method=DELETE – entfernen
//
// Der Ausschnitt wird serverseitig aus dem Upload geschnitten und
// als 256×256-JPEG neu kodiert (siehe private/php/core/avatar.php).
// Limits (nicht für Admins): 10 Uploads pro Stunde.
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . "/../../../private/php/core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

require_once __DIR__ . '/../../../private/php/core/db.php';
require_once __DIR__ . '/../../../private/php/core/avatar.php';

const AVATAR_MAX_UPLOAD   = 10 * 1024 * 1024; // 10 MB, wie beim Spot-Upload
const AVATAR_MAX_PER_HOUR = 10;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// =============================================================
// Antwort-Helfer
// =============================================================
function avatar_respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function avatar_fail(string $message, int $status): never
{
    avatar_respond(['ok' => false, 'error' => $message], $status);
}

// =============================================================
// Grundprüfungen: Methode, Größe des Requests, Login, CSRF
// =============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    avatar_fail('Methode nicht erlaubt.', 405);
}

// Übersteigt der Body post_max_size, verwirft PHP $_POST und $_FILES
// komplett – ohne diese Prüfung käme ein irreführender CSRF-Fehler.
if ($_POST === [] && $_FILES === [] && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    avatar_fail('Datei zu groß (max. 10 MB).', 413);
}

if (!isset($_SESSION['user_id'])) {
    avatar_fail('Nicht eingeloggt.', 401);
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    avatar_fail('Sitzung abgelaufen. Bitte Seite neu laden.', 403);
}

$userId  = (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

// Aktuellen Avatar lesen (für Ersetzen/Löschen der alten Datei)
$stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) {
    avatar_fail('Nutzer nicht gefunden.', 404);
}
$oldAvatar = $row['avatar'] !== null ? (string)$row['avatar'] : null;

// =============================================================
// DELETE – Profilbild entfernen
// =============================================================
if (post_str('_method') === 'DELETE') {
    try {
        $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?")->execute([$userId]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'AVATAR_DELETED', ?)")
            ->execute([$userId, client_ip()]);
    } catch (PDOException $e) {
        error_log('api/avatar.php delete error: ' . $e->getMessage());
        avatar_fail('Profilbild konnte nicht entfernt werden.', 500);
    }
    avatar_delete_file($oldAvatar);
    avatar_respond(['ok' => true]);
}

// =============================================================
// Upload – Rate-Limit (Admins unbegrenzt)
// =============================================================
if (!$isAdmin) {
    $rl = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'AVATAR_UPLOADED' AND user_id = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $rl->execute([$userId]);
    if ((int)$rl->fetchColumn() >= AVATAR_MAX_PER_HOUR) {
        avatar_fail('Zu viele Änderungen. Bitte später erneut versuchen.', 429);
    }
}

// =============================================================
// Upload – Datei prüfen
// =============================================================
$file = $_FILES['avatar'] ?? null;
if (!is_array($file) || is_array($file['error'] ?? null)) {
    avatar_fail('Keine Datei ausgewählt.', 400);
}

$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
    avatar_fail('Datei zu groß (max. 10 MB).', 413);
}
if ($error === UPLOAD_ERR_NO_FILE) {
    avatar_fail('Keine Datei ausgewählt.', 400);
}
$tmpPath = (string)($file['tmp_name'] ?? '');
if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmpPath)) {
    avatar_fail('Upload-Fehler. Bitte erneut versuchen.', 400);
}

// --- Größe (tatsächliche Dateigröße, nicht die Client-Angabe) ---
$size = filesize($tmpPath);
if ($size === false || $size <= 0 || $size > AVATAR_MAX_UPLOAD) {
    avatar_fail('Datei zu groß (max. 10 MB).', 413);
}

// --- Typ: Inhalt (MIME) und Endung ---
$mimeType = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
$ext      = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)
    || !in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
    avatar_fail('Kein erlaubtes Format (nur JPG/PNG).', 415);
}

// --- Echtes Bild + Pixel-Obergrenze (Decompression-Bomb) ---
if (@getimagesize($tmpPath) === false) {
    avatar_fail('Keine gültige Bilddatei.', 422);
}
if (!image_dimensions_ok($tmpPath)) {
    avatar_fail('Bild zu groß (Auflösung).', 422);
}

// --- Ausschnitt: nur ganze Zahlen, Bereich klemmt create_avatar_image() ---
$cropX    = filter_var(post_str('crop_x'),    FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => IMAGE_MAX_EDGE]]);
$cropY    = filter_var(post_str('crop_y'),    FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => IMAGE_MAX_EDGE]]);
$cropSize = filter_var(post_str('crop_size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => IMAGE_MAX_EDGE]]);
if ($cropX === false || $cropY === false || $cropSize === false) {
    avatar_fail('Ungültiger Bildausschnitt.', 422);
}

// =============================================================
// Upload – Speichern, DB aktualisieren, alte Datei entfernen
// =============================================================
if (!is_dir(AVATAR_DIR) && !@mkdir(AVATAR_DIR, 0755, true) && !is_dir(AVATAR_DIR)) {
    error_log('api/avatar.php: Avatar-Ordner nicht anlegbar');
    avatar_fail('Fehler beim Speichern.', 500);
}

$newAvatar = avatar_new_filename();
$destPath  = avatar_path($newAvatar);

if (!create_avatar_image($tmpPath, $mimeType, $cropX, $cropY, $cropSize, $destPath)) {
    error_log('api/avatar.php: Erzeugung fehlgeschlagen fuer user ' . $userId);
    avatar_fail('Bild konnte nicht verarbeitet werden.', 422);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$newAvatar, $userId]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'AVATAR_UPLOADED', ?)")
        ->execute([$userId, client_ip()]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($destPath);
    error_log('api/avatar.php upload error: ' . $e->getMessage());
    avatar_fail('Fehler beim Speichern.', 500);
}

// Erst nach erfolgreichem DB-Update die alte Datei löschen
if ($oldAvatar !== $newAvatar) {
    avatar_delete_file($oldAvatar);
}

avatar_respond(['ok' => true, 'url' => AVATAR_URL_BASE . $newAvatar]);

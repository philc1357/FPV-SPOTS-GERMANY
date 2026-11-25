<?php
declare(strict_types=1);
// =============================================================
// Highlight-Bilder eines Spots festlegen – Form-Submit
// -------------------------------------------------------------
// Feld: highlight[<image_id>] = 0|1|2|3 (0 = kein Highlight).
// Berechtigt: Spot-Ersteller oder Admin – für alle Fotos des
// Spots, auch fremde (das Foto selbst wird nicht verändert).
// Alle Plätze 0 = Zurücksetzen, dann greift die Automatik.
// =============================================================
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/highlight_helper.php';

const HIGHLIGHTS_MAX_PER_HOUR = 60;

// =============================================================
// Grundprüfungen: Methode, Login, CSRF
// =============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$spotId  = (int)($_POST['spot_id'] ?? 0);
$userId  = (int)$_SESSION['user_id'];
$isAdmin = !empty($_SESSION['is_admin']);

if ($spotId <= 0) {
    header('Location: /dashboard.php');
    exit;
}

// Redirect-Ziel per Whitelist
$toDetail = post_str('redirect') === 'detail';
$backUrl  = $toDetail
    ? '/spot_detail.php?id=' . $spotId
    : '/edit_spot.php?id=' . $spotId;
$anchor   = $toDetail ? '#fotos' : '#photos';

// Fehler: Meldung in der Session, zurück zur Ausgangsseite
$fail = static function (string $message) use ($spotId, $backUrl, $anchor): never {
    $_SESSION['highlight_error'] = ['spot_id' => $spotId, 'message' => $message];
    header('Location: ' . $backUrl . $anchor);
    exit;
};

// =============================================================
// Berechtigung: Spot-Ersteller oder Admin
// =============================================================
$stmt = $pdo->prepare('SELECT user_id FROM spots WHERE id = ?');
$stmt->execute([$spotId]);
$ownerId = $stmt->fetchColumn();

if ($ownerId === false || ((int)$ownerId !== $userId && !$isAdmin)) {
    header('Location: /dashboard.php');
    exit;
}

// =============================================================
// Rate-Limit (Admins frei)
// =============================================================
if (!$isAdmin) {
    $rl = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'SPOT_HIGHLIGHTS_SET' AND user_id = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $rl->execute([$userId]);
    if ((int)$rl->fetchColumn() >= HIGHLIGHTS_MAX_PER_HOUR) {
        $fail('Zu viele Änderungen in kurzer Zeit. Bitte versuche es später erneut.');
    }
}

// =============================================================
// Eingabe validieren: highlight[<image_id>] = 0..3
// Der Button "Zurücksetzen" (reset=1) ignoriert die Auswahl.
// =============================================================
$raw = post_str('reset') === '1' ? [] : ($_POST['highlight'] ?? []);
if (!is_array($raw)) {
    $fail('Ungültige Auswahl.');
}

$byPos = []; // Platz => Bild-ID
foreach ($raw as $key => $value) {
    if (!is_string($value) || !ctype_digit((string)$key) || !ctype_digit($value)) {
        $fail('Ungültige Auswahl.');
    }
    $imageId = (int)$key;
    $pos     = (int)$value;
    if ($pos === 0) {
        continue;
    }
    if ($imageId <= 0 || $pos > SPOT_HIGHLIGHTS_MAX) {
        $fail('Ungültige Auswahl.');
    }
    if (isset($byPos[$pos])) {
        $fail('Jeder Platz darf nur einmal vergeben werden.');
    }
    $byPos[$pos] = $imageId;
}

// Nach Platz sortieren und zu 1..n verdichten (z. B. nur Platz 1 und 3 gewählt)
ksort($byPos);
$imageIds = array_values($byPos);

// Alle gewählten Bilder müssen zu diesem Spot gehören
if ($imageIds !== []) {
    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
    $check = $pdo->prepare("SELECT COUNT(*) FROM spot_images WHERE spot_id = ? AND id IN ($placeholders)");
    $check->execute(array_merge([$spotId], $imageIds));
    if ((int)$check->fetchColumn() !== count($imageIds)) {
        $fail('Mindestens ein Foto gehört nicht (mehr) zu diesem Spot.');
    }
}

// =============================================================
// Speichern + Audit-Log
// =============================================================
try {
    spot_set_highlights($pdo, $spotId, $imageIds);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'SPOT_HIGHLIGHTS_SET', ?)")
        ->execute([$userId, client_ip()]);
} catch (Throwable $e) {
    error_log('highlights_submit.php error: ' . $e->getMessage());
    $fail('Highlights konnten nicht gespeichert werden. Bitte erneut versuchen.');
}

header('Location: ' . $backUrl . '&highlights_saved=1' . $anchor);
exit;

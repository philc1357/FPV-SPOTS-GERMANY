<?php
declare(strict_types=1);
// =============================================================
// Passwort zurücksetzen – Form-Submit
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/password_blacklist.php';
require_once __DIR__ . '/../core/session_guard.php';
require_once __DIR__ . '/../core/account_mails.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forgot_password.php');
    exit;
}

if (!csrf_valid(post_str('csrf_token'))) {
    die('CSRF-Fehler');
}

$selector  = post_str('selector');
$validator = post_str('validator');
$newPass1  = post_str('password_field1');
$newPass2  = post_str('password');

// Passwort-Validierung (gleiche Regeln wie change_password_submit.php)
if ($newPass1 === '' || $newPass2 === '') {
    $error = "Bitte beide Passwortfelder ausfüllen.";
}

if (!isset($error) && strlen($newPass1) < 8) {
    $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
}

if (!isset($error) && strlen($newPass1) > 72) {
    $error = "Das Passwort darf maximal 72 Zeichen lang sein (Umlaute und Sonderzeichen zählen doppelt).";
}

if (!isset($error) && is_blacklisted_password($newPass1)) {
    $error = "Dieses Passwort ist zu häufig verwendet. Bitte wähle ein sichereres.";
}

if (!isset($error) && !hash_equals($newPass1, $newPass2)) {
    $error = "Die Passwörter stimmen nicht überein.";
}

// =============================================================
// Token prüfen + Passwort setzen – alles in EINER Transaktion
// SELECT … FOR UPDATE sperrt die Token-Zeile: zwei parallele
// Requests mit demselben Link können ihn nicht doppelt einlösen.
// Passwort, Token-Löschung und Session-Entwertung gelingen nur
// gemeinsam oder gar nicht.
// =============================================================
if (!isset($error)) {
    // Format vorab prüfen (spart eine DB-Abfrage bei Müll-Eingaben)
    if (!preg_match('/^[a-f0-9]{32}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        $error = "Dieser Link ist ungültig oder abgelaufen.";
    }
}

if (!isset($error)) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT t.id, t.validator_hash, t.user_id, u.username, u.email
             FROM password_reset_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.selector = ? AND t.expires_at > NOW()
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            $pdo->rollBack();
            $error = "Dieser Link ist ungültig oder abgelaufen.";
        } else {
            $newHash = password_hash($newPass1, PASSWORD_DEFAULT);

            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([$newHash, $token['user_id']]);

            // Token löschen (einmalig) – sicherheitshalber alle des Users
            $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")
                ->execute([$token['user_id']]);

            // Alle Anmeldungen des Users entwerten: password_changed_at setzen
            // (kippt laufende Sessions auf allen Geräten) + Remember-Tokens
            // und Geräte-Cookies löschen
            invalidate_all_user_sessions($pdo, (int)$token['user_id']);

            $pdo->commit();

            // Audit-Log
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'PASSWORD_RESET_COMPLETED', ?)")
                ->execute([$token['user_id'], client_ip()]);

            // Inhaber informieren
            send_password_changed_mail((string)$token['email'], (string)$token['username'], 'reset');

            $success = true;
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('reset_password_submit.php error: ' . $e->getMessage());
        $error = "Fehler beim Speichern. Bitte versuche es erneut.";
    }
}

$csrfToken  = $_SESSION['csrf_token'];
$isLoggedIn = isset($_SESSION['user_id']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen – FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../../public/includes/header.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    Passwort erfolgreich geändert. Du kannst dich jetzt einloggen.
                </div>
                <a href="/" class="btn btn-primary w-100">Zur Startseite</a>
            <?php else: ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <a href="/forgot_password.php" class="btn btn-warning w-100">Neuen Link anfordern</a>
            <?php endif; ?>
        </div>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
</body>
</html>

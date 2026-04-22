<?php
// =============================================================
// Passwort zurücksetzen – Form-Submit
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/password_blacklist.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/forgot_password.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$selector  = $_POST['selector'] ?? '';
$validator = $_POST['validator'] ?? '';
$newPass1  = $_POST['password_field1'] ?? '';
$newPass2  = $_POST['password'] ?? '';

// Passwort-Validierung (gleiche Regeln wie change_password_submit.php)
if (empty($newPass1) || empty($newPass2)) {
    $error = "Bitte beide Passwortfelder ausfüllen.";
}

if (!isset($error) && strlen($newPass1) < 8) {
    $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
}

if (!isset($error) && strlen($newPass1) > 50) {
    $error = "Das Passwort darf maximal 50 Zeichen lang sein.";
}

if (!isset($error) && is_blacklisted_password($newPass1)) {
    $error = "Dieses Passwort ist zu häufig verwendet. Bitte wähle ein sichereres.";
}

if (!isset($error) && !hash_equals($newPass1, $newPass2)) {
    $error = "Die Passwörter stimmen nicht überein.";
}

// Token validieren
if (!isset($error)) {
    $stmt = $pdo->prepare(
        "SELECT id, validator_hash, user_id, expires_at
         FROM password_reset_tokens
         WHERE selector = ? AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$selector]);
    $token = $stmt->fetch();

    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        $error = "Dieser Link ist ungültig oder abgelaufen.";
    }
}

// Passwort aktualisieren
if (!isset($error)) {
    try {
        $newHash = password_hash($newPass1, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $token['user_id']]);

        // Token löschen (einmalig)
        $pdo->prepare("DELETE FROM password_reset_tokens WHERE id = ?")->execute([$token['id']]);

        // Alle Remember-Tokens des Users löschen (andere Sessions invalidieren)
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$token['user_id']]);

        // Audit-Log
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'PASSWORD_RESET_COMPLETED', ?)")
            ->execute([$token['user_id'], client_ip()]);

        $success = true;

    } catch (PDOException $e) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="/public/css/dashboard.css">
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
                <a href="/public/php/forgot_password.php" class="btn btn-warning w-100">Neuen Link anfordern</a>
            <?php endif; ?>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>
</html>

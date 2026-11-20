<?php
declare(strict_types=1);
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$isLoggedIn = true;
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];
$userId     = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort ändern – FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="container mt-5">
        <div class="fsg-card" style="max-width: 440px; margin: auto;">
        <h1 class="fsg-h2">Passwort ändern</h1>
        <div id="passwordError" class="text-danger small" style="display: none;">
            Die Passwörter stimmen nicht überein.
        </div>
        <form id="passwordForm" action="/private/php/account/change_password_submit.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <!-- Autofill unterbinden: Die Passwort-Bestätigung soll bewusst
                 eingetippt werden (Schutz bei offen gelassenem Rechner).
                 - Passwortfeld mit autocomplete="new-password": Browser füllen
                   dort kein gespeichertes Passwort ein ("off" ignorieren sie).
                 - Verstecktes Benutzername-Feld: ordnet das Formular dem Konto
                   zu, damit der Browser kein sichtbares Feld als Login-Feld nutzt.
                   Wird serverseitig nicht ausgewertet. -->
            <input type="text" name="pm_username" value="<?= $username ?>" autocomplete="username" class="d-none" tabindex="-1" aria-hidden="true">
            <label for="current_password" class="mb-2">Aktuelles Passwort:</label>
            <input id="current_password" type="password" name="current_password" class="form-control mb-2" maxlength="72" data-password-toggle required autocomplete="new-password">
            <label for="password" class="mb-2">Neues Passwort:</label>
            <input id="password" type="password" name="password_field1" class="form-control mb-2" minlength="8" maxlength="72" data-password-toggle required autocomplete="new-password">
            <label for="password_confirm" class="mb-2">Passwort wiederholen:</label>
            <input id="password_confirm" type="password" name="password" class="form-control mb-2" minlength="8" maxlength="72" data-password-toggle required autocomplete="new-password">
            <button type="submit" class="btn btn-primary">Bestätigen</button>
            <a href="/dashboard.php" class="btn btn-outline-secondary">Zurück</a>
        </form>
    </div>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
<script src="<?= asset('/private/js/password_confirm.js') ?>"></script>
</body>
</html>
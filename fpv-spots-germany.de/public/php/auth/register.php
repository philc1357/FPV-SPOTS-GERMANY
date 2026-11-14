<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../private/php/core/session_init.php';
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrieren – FPV Spots Germany</title>
    <meta name="description" content="Erstelle ein kostenloses Konto und teile deine besten FPV-Drohnen-Spots mit der Community.">
    <meta name="robots" content="noindex, follow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>

    <main class="container mt-5">
        <div class="fsg-card" style="max-width: 440px; margin: auto;">
            <h1 class="fsg-h2">Registrieren</h1>

            <form id="passwordForm" action="/private/php/auth/register_submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="Benutzername" minlength="5" maxlength="50" required></div>
<div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" minlength="10" maxlength="100" required></div>
                <div class="mb-3"><input id="password" type="password" name="password_field1" class="form-control" placeholder="Passwort" minlength="8" required></div>
                <div class="mb-3">
                    <input id="password_confirm" type="password" name="password" class="form-control" placeholder="Passwort wiederholen" required>
                    <div id="passwordError" class="text-danger small" style="display: none;">
                        Die Passwörter stimmen nicht überein.
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" value="1" required>
                    <label class="form-check-label small" for="terms">
                        Ich habe die <a href="/nutzungsbedingungen.php" target="_blank" class="fw-semibold">Nutzungsbedingungen</a> gelesen und akzeptiere sie. *
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-2">Registrieren</button>
            </form>
            <a href="/" class="btn btn-outline-secondary w-100 mb-2">Zurück zur Karte</a>
        </div>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
<script src="<?= asset('/private/js/password_confirm.js') ?>"></script>
</body>
</html>
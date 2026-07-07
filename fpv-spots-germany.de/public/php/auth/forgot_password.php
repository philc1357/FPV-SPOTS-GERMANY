<?php
declare(strict_types=1);
require_once __DIR__ . "/../../../private/php/core/session_init.php";
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
    <title>Passwort vergessen – FPV Spots Germany</title>
    <meta name="description" content="Setze dein Passwort zurück, um wieder Zugang zu deinem FPV Spots Germany Konto zu erhalten.">
    <meta name="robots" content="noindex, nofollow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
            <h1 class="h3">Passwort vergessen</h1>
            <p class="text-light small mb-3">Gib deine E-Mail-Adresse ein. Du erhältst einen Link zum Zurücksetzen deines Passworts.</p>

            <form action="/private/php/auth/forgot_password_submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="mb-3">
                    <input type="email" name="email" class="form-control" placeholder="E-Mail-Adresse" required>
                </div>
                <button type="submit" class="btn btn-success w-100 mb-2">Link senden</button>
            </form>
            <a href="/"><button class="btn btn-primary w-100 mb-2">Zurück</button></a>
        </div>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
</body>
</html>

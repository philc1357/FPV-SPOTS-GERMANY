<?php
declare(strict_types=1);
require_once __DIR__ . "/../../../private/php/core/session_init.php";
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
    <title>Nachricht gesendet – FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6">
            <div class="card card-dark text-light p-4 text-center">
                <h1 class="h4 mb-3">Nachricht gesendet</h1>
                <p class="text-secondary mb-4">Deine Kontaktanfrage wurde erfolgreich gespeichert. Wir melden uns so schnell wie möglich bei dir.</p>
                <a href="/" class="btn btn-primary">Zurück zur Karte</a>
            </div>
        </div>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
</body>
</html>

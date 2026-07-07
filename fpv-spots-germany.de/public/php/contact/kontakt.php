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

$userEmail = '';
if ($isLoggedIn) {
    require_once __DIR__ . '/../../../private/php/core/db.php';
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    if ($userData) {
        $userEmail = htmlspecialchars($userData['email'], ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontakt – FPV Spots Germany</title>
    <meta name="description" content="Kontaktiere das Team von FPV Spots Germany.">
    <meta name="robots" content="noindex, follow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
            <h1 class="h3">Kontaktformular</h1>

            <form id="contactForm" action="/private/php/contact/kontakt_submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="E-Mail" value="<?= $userEmail ?>"<?= $userEmail ? ' readonly' : '' ?>></div>
                <div class="mb-3"><textarea name="contact_field" class="form-control" rows="5" placeholder="Ihr Anliegen..."></textarea></div>
                <button type="submit" class="btn btn-success w-100 mb-2">Absenden</button>
            </form>
            <a href="/"><button class="btn btn-primary w-100 mb-2">Zurück</button></a>
        </div>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
</body>
</html>
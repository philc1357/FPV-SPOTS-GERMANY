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
    <title>Login – FPV Spots Germany</title>
    <meta name="description" content="Melde dich an, um FPV-Spots zu erstellen, bewerten und kommentieren.">
    <meta name="robots" content="noindex, follow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
            <h1 class="h3">Login</h1>

            <form id="passwordForm" action="/private/php/auth/login_submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="Benutzername" minlength="5" maxlength="50" required></div>
                <div class="mb-3"><input id="password" type="password" name="password" class="form-control" placeholder="Passwort" minlength="8" required></div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember_me" value="1" id="rememberMeLogin"
                           class="form-check-input">
                    <label for="rememberMeLogin" class="form-check-label small">Eingeloggt bleiben (30 Tage)</label>
                </div>
                <button type="submit" class="btn btn-success w-100 mb-2">Login</button>
            </form>
            <a href="/"><button class="btn btn-primary w-100 mb-2">Zurück</button></a>
        </div>
    </main>
</body>

<script src="/private/js/password_confirm.js"></script>
</html>
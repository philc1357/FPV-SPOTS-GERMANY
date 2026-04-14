<?php
session_start();
require_once __DIR__ . '/../../private/php/auth_check.php';

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
        <h1 class="h4">Passwort ändern</h1>
        <div id="passwordError" style="color: red; display: none; margin-top: 5px;">
            Die Passwörter stimmen nicht überein.
        </div>
        <form id="passwordForm" action="/private/php/data_changes/change_password_submit.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label for="new_password" class="mb-2">Neues Passwort:</label>
            <input id="password" type="password" name="password_field1" class="form-control mb-2" minlength="8" maxlength="50" required>
            <label for="new_password" class="mb-2">Passwort wiederholen:</label>
            <input id="password_confirm" type="password" name="password" class="form-control mb-2" minlength="8" maxlength="50" required>
            <button type="submit" class="btn btn-success">Bestätigen</button>
            <a href="/public/php/dashboard.php" class="btn btn-danger">Zurück</a>
        </form>
    </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
<script src="/private/js/password_confirm.js"></script>
</body>
</html>
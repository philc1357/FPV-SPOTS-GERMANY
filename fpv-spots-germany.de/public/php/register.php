<?php
session_start();

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/login_modal.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
            <h1 class="h3">Registrieren</h1>

            <form id="passwordForm" action="/private/php/register_submit.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="Benutzername" minlength="5" maxlength="50" required></div>
<div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" minlength="10" maxlength="100" required></div>
                <div class="mb-3"><input id="password" type="password" name="password_field1" class="form-control" placeholder="Passwort" minlength="8" required></div>
                <div class="mb-3">
                    <input id="password_confirm" type="password" name="password" class="form-control" placeholder="Passwort wiederholen" required>
                    <div id="passwordError" style="color: red; display: none; margin-top: 5px;">
                        Die Passwörter stimmen nicht überein.
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100 mb-2">Registrieren</button>
            </form>
            <a href="/"><button class="btn btn-primary w-100 mb-2">Zurück</button></a>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<script src="/private/js/password_confirm.js"></script>
</body>
</html>
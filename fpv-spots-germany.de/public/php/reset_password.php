<?php
session_start();

require_once __DIR__ . '/../../private/php/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken  = $_SESSION['csrf_token'];
$isLoggedIn = isset($_SESSION['user_id']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');

// Token aus URL prüfen
$selector  = $_GET['selector'] ?? '';
$validator = $_GET['validator'] ?? '';
$tokenValid = false;

if ($selector !== '' && $validator !== '') {
    $stmt = $pdo->prepare(
        "SELECT id, validator_hash, user_id, expires_at
         FROM password_reset_tokens
         WHERE selector = ? AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$selector]);
    $token = $stmt->fetch();

    if ($token && hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        $tokenValid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen – FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
            <?php if ($tokenValid): ?>
                <h1 class="h3">Neues Passwort setzen</h1>
                <form id="passwordForm" action="/private/php/reset_password_submit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="selector" value="<?= htmlspecialchars($selector, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="validator" value="<?= htmlspecialchars($validator, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <input id="password" type="password" name="password_field1" class="form-control"
                               placeholder="Neues Passwort" minlength="8" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <input id="password_confirm" type="password" name="password" class="form-control"
                               placeholder="Passwort wiederholen" minlength="8" maxlength="50" required>
                        <div id="passwordError" style="color: red; display: none; margin-top: 5px;">
                            Die Passwörter stimmen nicht überein.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 mb-2">Passwort ändern</button>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">
                    Dieser Link ist ungültig oder abgelaufen.
                </div>
                <a href="/public/php/forgot_password.php" class="btn btn-primary w-100 mb-2">Neuen Link anfordern</a>
            <?php endif; ?>
            <a href="/" class="btn btn-primary w-100">Zur Startseite</a>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<script src="/private/js/password_confirm.js"></script>
</body>
</html>

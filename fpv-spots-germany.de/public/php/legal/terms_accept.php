<?php
require_once __DIR__ . "/../../../private/php/core/session_init.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Nicht eingeloggte Nutzer brauchen diese Seite nicht
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// Bereits akzeptiert → weiterleiten
if (!empty($_SESSION['terms_ok'])) {
    header('Location: /');
    exit;
}

$isLoggedIn = true;
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutzungsbedingungen akzeptieren – FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container mt-5 mb-5">
    <div class="card bg-secondary text-white p-4" style="max-width: 600px; margin: auto;">

        <div class="alert alert-warning" role="alert">
            <i class="bi bi-shield-exclamation me-2"></i>
            <strong>Bitte bestätige unsere aktualisierten Nutzungsbedingungen</strong>
        </div>

        <p>
            Hallo <strong><?= $username ?></strong>,
        </p>
        <p>
            wir haben Nutzungsbedingungen eingeführt, die dich und alle Community-Mitglieder
            rechtlich absichern. Da FPV-Spots rechtlich komplex sind – Betretungsrechte,
            Luftrecht, Naturschutz – ist es wichtig, dass alle Nutzer die Eigenverantwortung
            für ihr Handeln anerkennen.
        </p>
        <p>
            <strong>Die Kernpunkte:</strong>
        </p>
        <ul class="small">
            <li>FPV Spots Germany ist eine reine Informationssammlung – kein Aufruf zu illegalem Betreten oder Fliegen.</li>
            <li>Jeder Nutzer ist selbst für die Einhaltung von Luftrecht, Betretungsrechten und Naturschutzverordnungen verantwortlich.</li>
            <li>Der Betreiber haftet nicht für die Richtigkeit nutzergenerierter Spot-Informationen.</li>
        </ul>
        <p>
            <a href="/nutzungsbedingungen.php" target="_blank" class="text-white fw-semibold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Nutzungsbedingungen vollständig lesen
            </a>
        </p>

        <form action="/private/php/legal/terms_accept_submit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="terms" name="terms" value="1" required>
                <label class="form-check-label" for="terms">
                    Ich habe die Nutzungsbedingungen gelesen und akzeptiere sie.
                </label>
            </div>
            <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-check-circle me-1"></i>Akzeptieren und weiter
            </button>
        </form>

        <hr class="my-3">

        <form method="POST" action="/private/php/auth/logout_submit.php" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <button type="submit" class="btn btn-outline-secondary w-100 btn-sm">
                Nicht akzeptieren und ausloggen
            </button>
        </form>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>
</html>

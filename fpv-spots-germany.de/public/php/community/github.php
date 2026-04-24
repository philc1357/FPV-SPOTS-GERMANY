<?php
declare(strict_types=1);
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/db.php';

$isLoggedIn = isset($_SESSION['user_id']) && $_SESSION['user_id'] !== null;
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Repository – FPV Spots Germany</title>
    <meta name="description" content="Der Quellcode von FPV Spots Germany auf GitHub – für technisch Interessierte.">
    <meta name="robots" content="index, follow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8">
            <div class="card card-dark text-light border-0">
                <div class="card-body p-4">
                    <h1 class="h3 mb-4"><i class="bi bi-github me-2"></i>GitHub Repository</h1>

                    <p class="lead mb-4">
                        FPV Spots Germany ist <strong>Open Source</strong> – der komplette Quellcode ist auf GitHub verfügbar.
                    </p>

                    <div class="alert alert-info mb-4" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Für technisch Interessierte:</strong> Schau dir den Code an, trage Verbesserungen bei oder gib Feedback zu technischen Details.
                    </div>

                    <div class="d-grid gap-2 mb-4">
                        <a href="https://github.com/philc1357/FPV-SPOTS-GERMANY"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn btn-primary btn-lg">
                            <i class="bi bi-github me-2"></i>
                            Zum Repository auf GitHub
                        </a>
                    </div>

                    <div class="mt-5 pt-4 border-top border-secondary">
                        <h5 class="mb-3">Was findest du im Repository?</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Vollständiger PHP/MySQL-Quellcode</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Frontend mit Bootstrap 5 und Leaflet</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Datenbank-Schema und Migration-Scripts</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Service Worker für PWA-Funktionalität</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Issues und Discussions für Feature-Requests</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

</body>
</html>

<?php
session_start();
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
    <title>Impressum – FPV Spots Germany</title>
    <meta name="description" content="Impressum von FPV Spots Germany.">
    <meta name="robots" content="noindex, follow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 600px; margin: auto;">
            <h1 class="h3 mb-4">Impressum</h1>

            <section aria-labelledby="angaben">
                <h2 id="angaben" class="h5">Angaben gemäß § 5 DDG</h2>
                <address>
                    Philipp Bauer<br>
                    Raimundstraße 10<br>
                    04177 Leipzig
                </address>
            </section>

            <section aria-labelledby="kontakt">
                <h2 id="kontakt" class="h5">Kontakt</h2>
                <p>
                    Telefon: <a href="tel:+4915238252427" class="text-white">01523 - 8252427</a><br>
                    E-Mail: <a href="mailto:info@fpv-spots-germany.de" class="text-white">info@fpv-spots-germany.de</a>
                </p>
            </section>

            <section aria-labelledby="verantwortlich">
                <h2 id="verantwortlich" class="h5">Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
                <address>
                    Philipp Bauer<br>
                    Raimundstraße 10<br>
                    04177 Leipzig
                </address>
            </section>

            <section aria-labelledby="streitschlichtung">
                <h2 id="streitschlichtung" class="h5">EU-Streitschlichtung</h2>
                <p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
                    <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer" class="text-white">https://ec.europa.eu/consumers/odr/</a>.
                </p>
                <p>Unsere E-Mail-Adresse findest du oben im Impressum.</p>
            </section>
            <a href="/"><button class="btn btn-primary w-100 mb-2">Zur Karte</button></a>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>
</html>

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
    <title>Impressum – FPV Spots Germany</title>
    <meta name="description" content="Impressum von FPV Spots Germany.">
    <meta name="robots" content="noindex, follow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

    <main class="container my-5">
        <?php
            $pageTitle  = 'Impressum';
            $pageKicker = 'Rechtliches';
            $pageIcon   = 'bi-info-circle';
            $pageNarrow = true;
            include __DIR__ . '/../../includes/page_head.php';
        ?>
        <article class="fsg-card fsg-card--prose">

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
                    Telefon: <a href="tel:+4915238252427">01523 - 8252427</a><br>
                    E-Mail: <a href="mailto:info@fpv-spots-germany.de">info@fpv-spots-germany.de</a>
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
                <h2 id="streitschlichtung" class="h5">Verbraucherstreitbeilegung</h2>
                <p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer
                    Verbraucherschlichtungsstelle teilzunehmen. Eine Übersicht der anerkannten
                    Streitbeilegungsstellen findest du unter
                    <a href="https://consumer-redress.ec.europa.eu/" target="_blank" rel="noopener noreferrer">https://consumer-redress.ec.europa.eu/</a>.
                </p>
                <p>Unsere E-Mail-Adresse findest du oben im Impressum.</p>
            </section>
            <section aria-labelledby="haftung-inhalte" class="mb-4">
                <h2 id="haftung-inhalte" class="h5">Haftung für Inhalte</h2>
                <p>
                    Die Inhalte dieser Website wurden mit größter Sorgfalt erstellt. Für die Richtigkeit,
                    Vollständigkeit und Aktualität der nutzergenerierten Spot-Informationen übernehmen
                    wir keine Gewähr. Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene
                    Inhalte verantwortlich, aber nicht verpflichtet, übermittelte oder gespeicherte
                    fremde Informationen zu überwachen.
                </p>
                <p>
                    Diese Plattform stellt keine Aufforderung dar, Orte zu betreten oder dort zu fliegen.
                    Nutzer sind selbst verantwortlich für die Einhaltung des Luftrechts, der
                    Betretungsrechte sowie lokaler Verordnungen. Weitere Informationen in unseren
                    <a href="/nutzungsbedingungen.php">Nutzungsbedingungen</a>.
                </p>
            </section>

            <section aria-labelledby="haftung-links" class="mb-4">
                <h2 id="haftung-links" class="h5">Haftung für Links</h2>
                <p>
                    Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte
                    wir keinen Einfluss haben. Für diese fremden Inhalte können wir keine Gewähr
                    übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige
                    Anbieter oder Betreiber verantwortlich.
                </p>
            </section>

            <a href="/" class="btn btn-outline-secondary w-100 mb-2">Zur Karte</a>
        </article>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
</body>
</html>

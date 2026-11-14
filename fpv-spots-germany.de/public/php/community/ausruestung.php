<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Ausrüstungsempfehlungen (BetaFPV-Partnerlinks)
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/affiliate.php';

// Feature aus (keine gültigen Einträge): Seite existiert nicht
if (!affiliate_enabled()) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Seite nicht gefunden.';
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

// Gruppierung: je Coptergröße ein Abschnitt, Produkte ohne Größe unter „Für alle Größen“
$groups = [];
foreach (AFFILIATE_SIZES as $size) {
    $items = array_values(array_filter(
        affiliate_products(),
        static fn(array $p): bool => in_array($size, $p['sizes'], true)
    ));
    if ($items) {
        $groups[$size] = $items;
    }
}
$general = array_values(array_filter(
    affiliate_products(),
    static fn(array $p): bool => $p['sizes'] === []
));
if ($general) {
    $groups['Für alle Größen'] = $general;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ausrüstung: BetaFPV-Empfehlungen – FPV Spots Germany</title>
    <meta name="description" content="Meine BetaFPV-Favoriten für FPV-Spots in Deutschland, sortiert nach Coptergröße – von Tinywhoop bis 5+ Zoll. Enthält Partnerlinks (Werbung).">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://fpv-spots-germany.de/ausruestung.php">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Ausrüstung: BetaFPV-Empfehlungen – FPV Spots Germany">
    <meta property="og:description" content="BetaFPV-Favoriten nach Coptergröße sortiert. Enthält Partnerlinks (Werbung).">
    <meta property="og:url" content="https://fpv-spots-germany.de/ausruestung.php">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>

    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

<main class="container my-5" style="max-width: 800px;">
    <article>
        <?php
            $pageTitle  = 'Ausrüstung: meine BetaFPV-Favoriten';
            $pageKicker = 'Empfehlungen';
            $pageIcon   = 'bi-bag';
            $pageLead   = 'Ich fliege selbst gerne mit BetaFPV. Hier stehen die Modelle, die ich empfehle – sortiert nach Coptergröße, passend zu den Spots auf der Karte.';
            include __DIR__ . '/../../includes/page_head.php';
        ?>
        <p class="small text-secondary">
            Diese Seite enthält Partnerlinks (Werbung). Dazu mehr in der
            <a href="/datenschutz.php#partnerlinks">Datenschutzerklärung</a>.
            Ob ein Flug an einem Spot zulässig ist, klärst du unabhängig davon über die Geozonen-Hinweise auf der jeweiligen Spot-Seite.
        </p>

        <?php
        $n = 0;
        foreach ($groups as $label => $items):
            $n++;
            $affiliateItems = $items;
            $affiliateTitle = $label;
            $affiliateId    = 'gruppe-' . $n;
            include __DIR__ . '/../../includes/affiliate_block.php';
        endforeach;
        ?>
    </article>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

</body>
</html>

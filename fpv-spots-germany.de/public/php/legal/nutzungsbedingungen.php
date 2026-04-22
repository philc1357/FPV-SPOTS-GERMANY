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
    <title>Nutzungsbedingungen – FPV Spots Germany</title>
    <meta name="description" content="Nutzungsbedingungen und Haftungsausschluss von FPV Spots Germany.">
    <meta name="robots" content="index, follow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

<main class="container mt-5 mb-5">
    <div class="card bg-secondary text-white p-4" style="max-width: 700px; margin: auto;">
        <h1 class="h3 mb-4">Nutzungsbedingungen &amp; Haftungsausschluss</h1>
        <p class="text-white-50 small">Stand: April 2026</p>

        <section aria-labelledby="zweck" class="mb-4">
            <h2 id="zweck" class="h5">1. Zweck der Plattform</h2>
            <p>
                FPV Spots Germany ist eine Community-Plattform zur Sammlung und Veröffentlichung von
                Informationen über potenzielle FPV-Drohnen-Spots in Deutschland. Die Plattform stellt
                ausschließlich von Nutzern eingereichte Informationen bereit und dient der Information
                der Community.
            </p>
            <p>
                <strong>Diese Plattform stellt keine Aufforderung dar, Orte zu betreten oder dort
                zu fliegen.</strong> Insbesondere wird niemand zu illegalen Aktivitäten animiert oder
                aufgefordert.
            </p>
        </section>

        <section aria-labelledby="eigenverantwortung" class="mb-4">
            <h2 id="eigenverantwortung" class="h5">2. Eigenverantwortung der Nutzer</h2>
            <p>
                Jede Person, die auf Basis der hier veröffentlichten Informationen handelt, tut dies
                auf eigene Verantwortung und eigenes Risiko. Vor dem Besuch eines Spots und vor jedem
                Flug bist du verpflichtet, dich selbst über folgende rechtliche Grundlagen zu informieren:
            </p>
            <ul>
                <li>
                    <strong>Luftrecht:</strong> Einhaltung der Luftverkehrs-Ordnung (LuftVO), insbesondere
                    § 21a ff. LuftVO (Betrieb von Drohnen), gültige EU-Drohnenführerscheine (A1/A3 oder A2),
                    Verbotszonen (z. B. Kontrollzonen, Naturschutz-Luftsperren) sowie die
                    <a href="https://www.dipul.de" target="_blank" rel="noopener noreferrer" class="text-white">
                        DIPUL-Karte (Digitale Plattform Unbemannte Luftfahrt)
                    </a>.
                </li>
                <li>
                    <strong>Betretungsrechte:</strong> Privatgelände darf nur mit ausdrücklicher Genehmigung
                    des Eigentümers betreten werden. Das Hausrecht liegt beim Grundstückseigentümer.
                    Industriebrachen, verlassene Gebäude und ähnliche Orte sind oft Privateigentum –
                    das Betreten kann Hausfriedensbruch (§ 123 StGB) darstellen.
                </li>
                <li>
                    <strong>Naturschutz:</strong> In Naturschutzgebieten, Nationalparks und Vogelschutzgebieten
                    gelten oft besondere oder vollständige Flugverbote. Informiere dich über lokale
                    Schutzgebietsverordnungen.
                </li>
                <li>
                    <strong>Lokale Verordnungen:</strong> Gemeinden und Landkreise können eigene
                    Regelungen für den Betrieb von Drohnen erlassen.
                </li>
            </ul>
        </section>

        <section aria-labelledby="haftung-inhalte" class="mb-4">
            <h2 id="haftung-inhalte" class="h5">3. Haftungsausschluss für Inhalte</h2>
            <p>
                Alle Spot-Informationen auf dieser Plattform sind nutzergenierte Inhalte. Der Betreiber
                prüft diese Inhalte nicht redaktionell und übernimmt keinerlei Gewähr für deren
                Richtigkeit, Vollständigkeit oder Aktualität.
            </p>
            <p>
                Der Betreiber haftet nicht für Schäden, die aus der Nutzung oder dem Vertrauen auf
                die hier bereitgestellten Informationen entstehen. Dies gilt insbesondere für:
            </p>
            <ul>
                <li>Unfälle, Verletzungen oder Sachschäden beim Besuch eines Spots</li>
                <li>Bußgelder oder rechtliche Konsequenzen wegen Verstößen gegen Luftrecht oder Betretungsverbote</li>
                <li>Falsche oder veraltete Angaben zu Parkmöglichkeiten, Zugangswegen oder Flugerlaubnissen</li>
            </ul>
        </section>

        <section aria-labelledby="verhaltensregeln" class="mb-4">
            <h2 id="verhaltensregeln" class="h5">4. Verhaltensregeln für Spot-Einreicher</h2>
            <p>Wer einen Spot einreicht, bestätigt folgendes:</p>
            <ul>
                <li>Die Angaben sind nach bestem Wissen korrekt.</li>
                <li>Es werden keine Spots eingetragen, die explizit zum illegalen Betreten von Privateigentum oder zum Fliegen in gesperrten Zonen auffordern.</li>
                <li>Foto-Uploads zeigen keine Personen ohne deren Einverständnis.</li>
                <li>Inhalte verstoßen nicht gegen geltendes Recht.</li>
            </ul>
            <p>
                Der Betreiber behält sich vor, Spots und Inhalte zu entfernen, die gegen diese
                Regeln verstoßen oder die Sicherheit anderer gefährden.
            </p>
        </section>

        <section aria-labelledby="haftung-links" class="mb-4">
            <h2 id="haftung-links" class="h5">5. Haftung für externe Links</h2>
            <p>
                Diese Website enthält Links zu externen Webseiten Dritter. Für deren Inhalte
                übernimmt der Betreiber keine Haftung. Die Inhalte verlinkter Seiten liegen
                ausschließlich in der Verantwortung der jeweiligen Anbieter.
            </p>
        </section>

        <section aria-labelledby="aenderungen" class="mb-4">
            <h2 id="aenderungen" class="h5">6. Änderungsvorbehalt</h2>
            <p>
                Der Betreiber behält sich vor, diese Nutzungsbedingungen jederzeit anzupassen.
                Die Weiternutzung der Plattform nach einer Änderung gilt als Zustimmung zu den
                aktualisierten Bedingungen.
            </p>
        </section>

        <a href="/"><button class="btn btn-primary w-100 mb-2">Zur Karte</button></a>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>
</html>

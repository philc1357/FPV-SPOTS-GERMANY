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
    <title>Anleitung – FPV Spots Germany</title>
    <meta name="description" content="Bedienungsanleitung für FPV Spots Germany: Karte und Filter nutzen, FPV-Spots finden, eigene Spots eintragen, bewerten, kommentieren und mit der Community austauschen.">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>

    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

    <main class="container my-5">
        <article class="card bg-secondary text-white p-4" style="max-width: 800px; margin: auto;">
            <h1 class="h3 mb-2">So funktioniert FPV Spots Germany</h1>
            <p class="mb-4">
                FPV Spots Germany ist eine Community-Plattform, auf der FPV-Piloten ihre Flugspots in
                Deutschland eintragen, bewerten und teilen. Auf dieser Seite findest du alles, was du
                zur Bedienung wissen musst.
            </p>

            <!-- ============ Karte & Filter ============ -->
            <section aria-labelledby="karte" class="mb-4">
                <h2 id="karte" class="h5"><i class="bi bi-map-fill me-1"></i> Die Karte</h2>
                <p>
                    Die <a href="/" class="text-white">Startseite</a> ist eine Vollbild-Karte mit allen
                    eingetragenen Spots. Jeder Marker steht für einen Spot: Die <strong>Farbe</strong>
                    zeigt den Spot-Typ (Bando, Feld, Gebirge, Park, Wald, Windpark, Sonstige), der farbige
                    <strong>Ring</strong> die Schwierigkeit (Anfänger, Mittel, Fortgeschritten, Profi).
                </p>
                <ul>
                    <li><strong>Kartenansicht wechseln:</strong> Unten links kannst du zwischen
                        <em>Straße</em>, <em>Satellit</em> und <em>Hybrid</em> umschalten. Die
                        Satellitenansicht hilft, Gelände und Bebauung vorab einzuschätzen.</li>
                    <li><strong>Legende &amp; Filter:</strong> Über die Legende blendest du Spots nach Typ,
                        Schwierigkeit und Coptergröße (Tinywhoop, 2–3 Zoll, 4–5 Zoll, 5+ Zoll) ein und aus.
                        Deine Auswahl wird gespeichert und ist beim nächsten Besuch wieder aktiv.</li>
                    <li><strong>Zu meinem Standort:</strong> Mit dem Standort-Button springst du zu deiner
                        aktuellen Position, um Spots in der Nähe zu finden. Dein Standort bleibt lokal im
                        Browser und wird nicht gespeichert.</li>
                    <li><strong>Spot-Vorschau:</strong> Ein Klick auf einen Marker öffnet eine Kurzinfo mit
                        Link zur vollständigen Spot-Seite.</li>
                </ul>
            </section>

            <!-- ============ Spot-Detailseite ============ -->
            <section aria-labelledby="spot-detail" class="mb-4">
                <h2 id="spot-detail" class="h5"><i class="bi bi-geo-alt-fill me-1"></i> Die Spot-Seite</h2>
                <p>
                    Auf der Detailseite eines Spots findest du Beschreibung, Typ, Schwierigkeit, passende
                    Coptergrößen, Fotos, Videos, Parkmöglichkeiten, die Sternebewertung sowie die
                    Kommentare anderer Piloten. Als angemeldeter Nutzer kannst du dort:
                </p>
                <ul>
                    <li>den Spot mit <strong>1 bis 5 Sternen bewerten</strong>,</li>
                    <li><strong>Kommentare</strong> schreiben, bearbeiten und löschen,</li>
                    <li><strong>Fotos hochladen</strong> (JPG oder PNG, max. 5 MB, bis zu 30 Bilder pro Spot –
                        EXIF-Daten werden beim Upload automatisch entfernt),</li>
                    <li><strong>Parkinformationen ergänzen</strong> – diese pflegt die Community gemeinsam,</li>
                    <li>den Spot als <strong>Favorit merken</strong> und im Dashboard wiederfinden,</li>
                    <li>Inhalte <strong>melden</strong>, wenn etwas nicht in Ordnung ist.</li>
                </ul>
            </section>

            <!-- ============ Spot eintragen ============ -->
            <section aria-labelledby="spot-eintragen" class="mb-4">
                <h2 id="spot-eintragen" class="h5"><i class="bi bi-plus-circle-fill me-1"></i> Eigenen Spot eintragen</h2>
                <p>
                    Dafür brauchst du ein kostenloses Konto. Melde dich an, öffne die Karte und
                    <strong>tippe auf die Stelle</strong>, an der dein Spot liegt. Es öffnet sich ein
                    Formular, in dem du Name, Beschreibung, Spot-Typ, Schwierigkeit, geeignete
                    Coptergrößen, Parkmöglichkeiten und optional bis zu zehn YouTube-Links einträgst.
                    Die Koordinaten übernimmt die Karte automatisch.
                </p>
                <p class="mb-0">
                    Eigene Spots kannst du jederzeit über die Spot-Seite <strong>bearbeiten</strong> oder
                    <strong>löschen</strong>.
                </p>
            </section>

            <!-- ============ Konto & Dashboard ============ -->
            <section aria-labelledby="konto" class="mb-4">
                <h2 id="konto" class="h5"><i class="bi bi-person-fill me-1"></i> Konto &amp; Dashboard</h2>
                <ul>
                    <li><strong>Registrierung &amp; Login</strong> erfolgen über das Menü oben rechts.
                        Mit „Angemeldet bleiben“ bleibst du 30 Tage eingeloggt.</li>
                    <li>Im <a href="/dashboard.php" class="text-white">Dashboard</a> siehst du deine eigenen
                        Spots und deine Favoriten und kannst eine kurze <strong>Bio</strong> hinterlegen.</li>
                    <li><strong>Öffentliches Profil:</strong> Jeder Nutzer hat eine Profilseite mit Bio und
                        einer Mini-Karte seiner Spots. Du kannst dein Profil auch auf privat stellen.</li>
                    <li><strong>Zugangsdaten ändern:</strong> Benutzername, E-Mail und Passwort änderst du
                        im Dashboard – jeweils mit Passwort-Bestätigung. Passwort vergessen? Dann nutze den
                        <a href="/forgot_password.php" class="text-white">Passwort-Reset per E-Mail</a>.</li>
                </ul>
            </section>

            <!-- ============ Nachrichten & Benachrichtigungen ============ -->
            <section aria-labelledby="nachrichten" class="mb-4">
                <h2 id="nachrichten" class="h5"><i class="bi bi-envelope-fill me-1"></i> Nachrichten &amp; Benachrichtigungen</h2>
                <p class="mb-0">
                    Über das <i class="bi bi-envelope-fill"></i>-Symbol auf einem Profil schreibst du anderen
                    Piloten direkt. Alle Unterhaltungen findest du unter
                    <a href="/messages.php" class="text-white">Nachrichten</a>. Das
                    <i class="bi bi-exclamation-circle-fill text-warning"></i>-Symbol im Menü zeigt dir an,
                    wenn es etwas Neues gibt – etwa eine Nachricht, einen Kommentar zu deinem Spot oder
                    einen neuen Verbesserungsvorschlag. Details siehst du unter
                    <a href="/benachrichtigungen.php" class="text-white">Benachrichtigungen</a>.
                </p>
            </section>

            <!-- ============ Community ============ -->
            <section aria-labelledby="community" class="mb-4">
                <h2 id="community" class="h5"><i class="bi bi-people-fill me-1"></i> Community</h2>
                <ul>
                    <li><a href="/forum.php" class="text-white">Forum</a> – Beiträge mit Bildern, Kommentaren
                        und Antworten.</li>
                    <li><a href="/kritik.php" class="text-white">Verbesserungsvorschläge</a> – Ideen einreichen
                        und für die Vorschläge anderer abstimmen (eine Stimme pro Nutzer).</li>
                    <li><a href="/neuigkeiten.php" class="text-white">Community Updates</a> – was zuletzt auf
                        der Plattform passiert ist.</li>
                    <li><a href="/updates.php" class="text-white">Website Updates</a> – Changelog der Seite.</li>
                </ul>
            </section>

            <!-- ============ App-Installation ============ -->
            <section aria-labelledby="app" class="mb-4">
                <h2 id="app" class="h5"><i class="bi bi-phone-fill me-1"></i> Als App installieren</h2>
                <p class="mb-0">
                    FPV Spots Germany ist eine Progressive Web App: Du kannst sie über den Hinweis im Browser
                    oder das Teilen-Menü deines Smartphones („Zum Home-Bildschirm“) installieren und dann wie
                    eine normale App öffnen.
                </p>
            </section>

            <!-- ============ Sicherheit & Recht ============ -->
            <section aria-labelledby="verantwortung" class="mb-4">
                <h2 id="verantwortung" class="h5"><i class="bi bi-shield-exclamation me-1"></i> Wichtig: deine Verantwortung</h2>
                <p class="mb-0">
                    Alle Spots stammen von Nutzern. Ein Eintrag ist <strong>keine Fluggenehmigung</strong>
                    und keine Aufforderung, ein Gelände zu betreten. Prüfe vor jedem Flug selbst die
                    geltenden Luftrechts- und Betretungsregeln, Schutzgebiete sowie eventuelle
                    Genehmigungspflichten. Mehr dazu in den
                    <a href="/nutzungsbedingungen.php" class="text-white">Nutzungsbedingungen</a>.
                </p>
            </section>

            <p>
                Noch Fragen offen? Schreib uns über das
                <a href="/kontakt.php" class="text-white">Kontaktformular</a>.
            </p>

            <div class="d-flex gap-2 flex-wrap">
                <a href="/" class="btn btn-primary flex-fill">Zur Karte</a>
                <a href="/kontakt.php" class="btn btn-outline-light flex-fill">Kontakt</a>
            </div>
        </article>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
</body>
</html>

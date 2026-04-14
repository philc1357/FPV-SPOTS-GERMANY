<?php
session_start();
require_once __DIR__ . '/../../private/php/auth_check.php';

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
    <title>Datenschutzerklärung – FPV Spots Germany</title>
    <meta name="description" content="Datenschutzerklärung von FPV Spots Germany – Informationen zur Verarbeitung personenbezogener Daten.">
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="https://fpv-spots-germany.de/public/php/datenschutz.php">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/login_modal.php'; ?>

    <main class="container my-5">
        <article class="card bg-secondary text-white p-4" style="max-width: 800px; margin: auto;">
            <h1 class="h3 mb-4">Datenschutzerklärung</h1>

            <!-- 1. Verantwortlicher -->
            <section aria-labelledby="verantwortlicher">
                <h2 id="verantwortlicher" class="h5">1. Verantwortlicher</h2>
                <address>
                    Philipp Bauer<br>
                    Raimundstraße 10<br>
                    04177 Leipzig<br>
                    E-Mail: <a href="mailto:info@fpv-spots-germany.de" class="text-white">info@fpv-spots-germany.de</a><br>
                    Telefon: <a href="tel:+4915238252427" class="text-white">01523 - 8252427</a>
                </address>
            </section>

            <!-- 2. Übersicht -->
            <section aria-labelledby="uebersicht">
                <h2 id="uebersicht" class="h5 mt-4">2. Übersicht der Verarbeitungen</h2>
                <p>Die nachfolgende Übersicht fasst die Arten der verarbeiteten Daten und die Zwecke ihrer Verarbeitung zusammen und verweist auf die betroffenen Personen.</p>
                <p><strong>Arten der verarbeiteten Daten:</strong> Bestandsdaten (Benutzername), Kontaktdaten (E-Mail-Adresse), Inhaltsdaten (Spot-Einträge, Bewertungen, Kommentare, Bilder), Nutzungsdaten (IP-Adresse, Zugriffszeiten), Meta-/Kommunikationsdaten (Session-Informationen), Standortdaten (nur bei freiwilliger Freigabe, ausschließlich lokal im Browser).</p>
                <p><strong>Betroffene Personen:</strong> Nutzer und Besucher der Website.</p>
            </section>

            <!-- 3. Rechtsgrundlagen -->
            <section aria-labelledby="rechtsgrundlagen">
                <h2 id="rechtsgrundlagen" class="h5 mt-4">3. Maßgebliche Rechtsgrundlagen</h2>
                <p>Die Verarbeitung personenbezogener Daten erfolgt auf Grundlage der folgenden Rechtsgrundlagen der DSGVO:</p>
                <ul>
                    <li><strong>Einwilligung (Art. 6 Abs. 1 lit. a DSGVO)</strong> – Die betroffene Person hat ihre Einwilligung in die Verarbeitung der sie betreffenden personenbezogenen Daten erteilt.</li>
                    <li><strong>Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO)</strong> – Die Verarbeitung ist zur Erfüllung eines Vertrags erforderlich, dessen Vertragspartei die betroffene Person ist (z.&nbsp;B. Bereitstellung des Benutzerkontos).</li>
                    <li><strong>Berechtigte Interessen (Art. 6 Abs. 1 lit. f DSGVO)</strong> – Die Verarbeitung ist zur Wahrung unserer berechtigten Interessen erforderlich, z.&nbsp;B. Sicherheit und Missbrauchsprävention.</li>
                </ul>
            </section>

            <!-- 4. Registrierung -->
            <section aria-labelledby="registrierung">
                <h2 id="registrierung" class="h5 mt-4">4. Registrierung und Benutzerkonto</h2>
                <p>Nutzer können auf unserer Website ein Benutzerkonto anlegen. Im Rahmen der Registrierung werden folgende Daten erhoben:</p>
                <ul>
                    <li>Benutzername</li>
                    <li>E-Mail-Adresse</li>
                    <li>Passwort (wird ausschließlich als kryptografischer Hash gespeichert)</li>
                </ul>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO).</p>
                <p><strong>Zweck:</strong> Bereitstellung des Benutzerkontos, Zuordnung von Spot-Einträgen, Bewertungen und Kommentaren.</p>
                <p><strong>Speicherdauer:</strong> Die Daten werden für die Dauer der Kontonutzung gespeichert und nach Löschung des Kontos gelöscht, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</p>
            </section>

            <!-- 5. Nutzerinhalte -->
            <section aria-labelledby="nutzerinhalte">
                <h2 id="nutzerinhalte" class="h5 mt-4">5. Nutzergenerierte Inhalte</h2>
                <p>Registrierte Nutzer können Spot-Einträge (Name, Beschreibung, Koordinaten, Kategorie, Schwierigkeit), Bewertungen, Kommentare und Bilder erstellen. Diese Inhalte werden zusammen mit der Benutzer-ID und dem Erstellungszeitpunkt gespeichert.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO).</p>
                <p><strong>Hinweis:</strong> Hochgeladene Bilder werden unter einem zufällig generierten Dateinamen gespeichert. Eine Rückverfolgung anhand des Dateinamens ist nicht möglich.</p>
            </section>

            <!-- 6. Kontaktformular -->
            <section aria-labelledby="kontaktformular">
                <h2 id="kontaktformular" class="h5 mt-4">6. Kontaktformular</h2>
                <p>Wenn Sie unser Kontaktformular nutzen, werden folgende Daten erhoben und verarbeitet:</p>
                <ul>
                    <li>E-Mail-Adresse</li>
                    <li>Inhalt Ihrer Nachricht</li>
                </ul>
                <p>Die Übermittlung erfolgt per E-Mail über unseren Mailserver. Die Daten werden ausschließlich zur Bearbeitung Ihrer Anfrage verwendet und danach gelöscht, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Bearbeitung Ihrer Kontaktanfrage.</p>
                <p><strong>Speicherdauer:</strong> Die Daten werden nach abschließender Bearbeitung Ihrer Anfrage gelöscht, spätestens nach 90 Tagen.</p>
            </section>

            <!-- 7. Protokollierung / Audit-Logs -->
            <section aria-labelledby="protokollierung">
                <h2 id="protokollierung" class="h5 mt-4">7. Sicherheitsprotokollierung (Audit-Logs)</h2>
                <p>Zur Erkennung und Abwehr von Missbrauch sowie zum Schutz unserer Systeme protokollieren wir sicherheitsrelevante Aktionen. Dabei werden folgende Daten erfasst:</p>
                <ul>
                    <li>Benutzer-ID (sofern angemeldet)</li>
                    <li>Art der Aktion (z.&nbsp;B. Anmeldung, Registrierung, Passwortwechsel)</li>
                    <li>IP-Adresse</li>
                    <li>Zeitstempel</li>
                </ul>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Sicherheit und Integrität unserer Systeme.</p>
                <p><strong>Speicherdauer:</strong> Audit-Logs werden nach 90 Tagen gelöscht, sofern kein berechtigtes Interesse an einer längeren Aufbewahrung besteht (z.&nbsp;B. bei laufenden Untersuchungen).</p>
            </section>

            <!-- 8. Cookies und Sessions -->
            <section aria-labelledby="cookies">
                <h2 id="cookies" class="h5 mt-4">8. Cookies und Session-Verwaltung</h2>
                <p>Unsere Website verwendet ausschließlich technisch notwendige Cookies:</p>
                <ul>
                    <li><strong>Session-Cookie (PHPSESSID):</strong> Ermöglicht die Zuordnung von Seitenaufrufen zu einer Benutzersitzung. Enthält keine personenbezogenen Daten und wird beim Schließen des Browsers gelöscht.</li>
                    <li><strong>CSRF-Token:</strong> Wird innerhalb der Session gespeichert und dient dem Schutz vor Cross-Site-Request-Forgery-Angriffen.</li>
                </ul>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – technisch notwendiger Betrieb der Website.</p>
                <p>Analyse-, Tracking- oder Marketing-Cookies werden <strong>nicht</strong> eingesetzt.</p>
            </section>

            <!-- 9. Kartendienst / Map Tiles -->
            <section aria-labelledby="kartendienst">
                <h2 id="kartendienst" class="h5 mt-4">9. Kartendienst (OpenStreetMap)</h2>
                <p>Für die Darstellung der interaktiven Karte nutzen wir Kartenkacheln (Map Tiles) von <strong>OpenStreetMap</strong>. Beim Laden der Karte wird eine Verbindung zu den Servern der OpenStreetMap Foundation (OSMF) hergestellt. Dabei wird Ihre IP-Adresse an die OSMF übermittelt.</p>
                <p><strong>Anbieter:</strong> OpenStreetMap Foundation, St John's Innovation Centre, Cowley Road, Cambridge, CB4 0WS, Vereinigtes Königreich.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Darstellung der Karteninhalte als Kernfunktionalität unseres Dienstes.</p>
                <p><strong>Datenschutzrichtlinie von OSMF:</strong> <a href="https://wiki.osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noopener noreferrer" class="text-white">https://wiki.osmfoundation.org/wiki/Privacy_Policy</a></p>
                <p>Wir selbst speichern keine Daten über Ihren Kartenzugriff oder die geladenen Kartenkacheln.</p>
            </section>

            <!-- 10. Standortfreigabe -->
            <section aria-labelledby="standort">
                <h2 id="standort" class="h5 mt-4">10. Standortfreigabe (Geolocation)</h2>
                <p>Beim ersten Besuch unserer Website werden Sie gefragt, ob Sie Ihren aktuellen Standort auf der Karte anzeigen möchten. Die Abfrage erfolgt über die standardisierte Browser-API <code>navigator.geolocation</code> und nur nach Ihrer ausdrücklichen Zustimmung.</p>
                <p><strong>Verarbeitete Daten:</strong> Geografische Koordinaten (Breitengrad, Längengrad) Ihres Endgeräts zum Zeitpunkt der Freigabe.</p>
                <p><strong>Zweck:</strong> Visuelle Darstellung Ihres Standorts auf der interaktiven Karte, um nahegelegene FPV-Spots leichter zu finden.</p>
                <p><strong>Rechtsgrundlage:</strong> Einwilligung (Art. 6 Abs. 1 lit. a DSGVO).</p>
                <p><strong>Speicherung und Übertragung:</strong> Ihre Standortdaten werden <strong>ausschließlich lokal in Ihrem Browser</strong> verarbeitet und weder an unsere Server übermittelt noch gespeichert. Lediglich Ihre Entscheidung (Zustimmung oder Ablehnung) wird als Cookie (<code>location_consent</code>) für 365 Tage gespeichert, damit Sie nicht bei jedem Besuch erneut gefragt werden.</p>
                <p><strong>Widerruf:</strong> Sie können Ihre Einwilligung jederzeit widerrufen, indem Sie den Cookie <code>location_consent</code> in den Einstellungen Ihres Browsers löschen oder die Standortberechtigung in den Browser-Einstellungen entziehen.</p>
            </section>

            <!-- 11. Externe Ressourcen -->
            <section aria-labelledby="cdn">
                <h2 id="cdn" class="h5 mt-4">11. Einbindung externer Ressourcen (CDN)</h2>
                <p>Wir binden CSS- und JavaScript-Bibliotheken (Bootstrap, Leaflet) über das Content Delivery Network <strong>jsDelivr</strong> ein. Beim Aufruf unserer Seiten wird eine Verbindung zu den Servern von jsDelivr hergestellt, wobei Ihre IP-Adresse übermittelt wird.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – performante Auslieferung der Website.</p>
                <p><strong>Datenschutzrichtlinie von jsDelivr:</strong> <a href="https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net" target="_blank" rel="noopener noreferrer" class="text-white">https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net</a></p>
            </section>

            <!-- 12. Betroffenenrechte -->
            <section aria-labelledby="betroffenenrechte">
                <h2 id="betroffenenrechte" class="h5 mt-4">12. Ihre Rechte als betroffene Person</h2>
                <p>Sie haben gemäß der DSGVO folgende Rechte:</p>
                <ul>
                    <li><strong>Auskunftsrecht (Art. 15 DSGVO):</strong> Sie können Auskunft über die von uns verarbeiteten personenbezogenen Daten verlangen.</li>
                    <li><strong>Recht auf Berichtigung (Art. 16 DSGVO):</strong> Sie können die Berichtigung unrichtiger Daten verlangen. Benutzername, E-Mail und Passwort können Sie eigenständig in Ihrem Dashboard ändern.</li>
                    <li><strong>Recht auf Löschung (Art. 17 DSGVO):</strong> Sie können die Löschung Ihrer personenbezogenen Daten verlangen, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</li>
                    <li><strong>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO):</strong> Sie können die Einschränkung der Verarbeitung Ihrer Daten verlangen.</li>
                    <li><strong>Recht auf Datenübertragbarkeit (Art. 20 DSGVO):</strong> Sie können verlangen, Ihre Daten in einem strukturierten, maschinenlesbaren Format zu erhalten.</li>
                    <li><strong>Widerspruchsrecht (Art. 21 DSGVO):</strong> Sie können der Verarbeitung Ihrer Daten, die auf berechtigtem Interesse basiert, jederzeit widersprechen.</li>
                    <li><strong>Beschwerderecht (Art. 77 DSGVO):</strong> Sie haben das Recht, sich bei einer Datenschutzaufsichtsbehörde zu beschweren. Zuständig ist der Sächsische Datenschutz- und Transparenzbeauftragte.</li>
                </ul>
                <p>Zur Ausübung Ihrer Rechte wenden Sie sich bitte an: <a href="mailto:info@fpv-spots-germany.de" class="text-white">info@fpv-spots-germany.de</a></p>
            </section>

            <!-- 13. Datensicherheit -->
            <section aria-labelledby="datensicherheit">
                <h2 id="datensicherheit" class="h5 mt-4">13. Datensicherheit</h2>
                <p>Wir setzen technische und organisatorische Maßnahmen ein, um Ihre Daten zu schützen. Dazu gehören unter anderem:</p>
                <ul>
                    <li>Verschlüsselte Übertragung per HTTPS/TLS</li>
                    <li>Passwort-Hashing mit modernen kryptografischen Verfahren</li>
                    <li>Schutz vor Cross-Site-Request-Forgery (CSRF) und SQL-Injection</li>
                    <li>Validierung und Bereinigung aller Nutzereingaben</li>
                </ul>
            </section>

            <!-- 14. Aktualität -->
            <section aria-labelledby="aktualitaet">
                <h2 id="aktualitaet" class="h5 mt-4">14. Aktualität und Änderung dieser Datenschutzerklärung</h2>
                <p>Diese Datenschutzerklärung ist aktuell gültig und hat den Stand April 2026.</p>
                <p>Durch die Weiterentwicklung unserer Website oder aufgrund geänderter gesetzlicher Vorgaben kann es notwendig werden, diese Datenschutzerklärung zu ändern. Die jeweils aktuelle Fassung kann jederzeit auf dieser Seite abgerufen werden.</p>
            </section>

            <a href="/" class="btn btn-primary w-100 mt-4 mb-2">Zur Karte</a>
        </article>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>
</html>

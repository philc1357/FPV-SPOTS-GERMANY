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
                <p><strong>Arten der verarbeiteten Daten:</strong> Bestandsdaten (Benutzername), Kontaktdaten (E-Mail-Adresse), Inhaltsdaten (Spot-Einträge, Bewertungen, Kommentare, Bilder, Direktnachrichten, Verbesserungsvorschläge), Nutzungsdaten (IP-Adresse, Zugriffszeiten), Meta-/Kommunikationsdaten (Session-Informationen, interne Benachrichtigungen), Standortdaten (nur bei freiwilliger Freigabe, ausschließlich lokal im Browser).</p>
                <p><strong>Betroffene Personen:</strong> Nutzer und Besucher der Website.</p>
                <p><strong>Zwecke der Verarbeitung:</strong> Bereitstellung der Plattformfunktionen, Authentifizierung, Nutzerkommunikation, Sicherheit und Missbrauchsprävention.</p>
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
                <p><strong>Änderungen an Kontodaten:</strong> Benutzername, E-Mail-Adresse und Passwort können jederzeit im Dashboard geändert werden. Jede Änderung wird in unserem Sicherheitsprotokoll (Audit-Log) mit IP-Adresse und Zeitstempel erfasst (siehe Abschnitt 14).</p>
                <p><strong>Speicherdauer:</strong> Die Daten werden für die Dauer der Kontonutzung gespeichert und nach Löschung des Kontos gelöscht, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</p>
            </section>

            <!-- 5. Eingeloggt bleiben -->
            <section aria-labelledby="remember-me">
                <h2 id="remember-me" class="h5 mt-4">5. „Eingeloggt bleiben"-Funktion</h2>
                <p>Bei der Anmeldung können Nutzer die Option „Eingeloggt bleiben" aktivieren. In diesem Fall wird eine persistente Anmeldesitzung eingerichtet.</p>
                <p><strong>Verarbeitete Daten:</strong></p>
                <ul>
                    <li>Ein verschlüsseltes Authentifizierungs-Token (bestehend aus einem zufällig generierten Selektor und einem Validator), das als sicheres Cookie im Browser gespeichert wird.</li>
                    <li>Ein kryptografischer Hash des Tokens sowie die zugehörige Benutzer-ID werden in unserer Datenbank hinterlegt.</li>
                </ul>
                <p><strong>Cookie:</strong> <code>remember_me</code> – HttpOnly, Secure, SameSite=Lax, Gültigkeitsdauer 30&nbsp;Tage.</p>
                <p><strong>Token-Rotation:</strong> Bei jeder automatischen Anmeldung über diesen Mechanismus wird das Token erneuert. Das alte Token wird dabei sofort ungültig gemacht.</p>
                <p><strong>Löschung:</strong> Das Token und der dazugehörige Datenbankeintrag werden gelöscht, wenn Sie sich abmelden oder Ihr Passwort ändern. Damit werden alle aktiven „Eingeloggt bleiben"-Sitzungen auf allen Geräten gleichzeitig beendet.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO) – Bereitstellung der komfortablen Anmeldefunktion.</p>
                <p><strong>Speicherdauer:</strong> 30&nbsp;Tage ab der letzten Nutzung; danach automatisch ungültig.</p>
            </section>

            <!-- 6. Öffentliches Benutzerprofil -->
            <section aria-labelledby="benutzerprofil">
                <h2 id="benutzerprofil" class="h5 mt-4">6. Öffentliches Benutzerprofil</h2>
                <p>Jedes Benutzerkonto verfügt über eine öffentlich zugängliche Profilseite. Dort werden folgende Daten angezeigt:</p>
                <ul>
                    <li>Benutzername</li>
                    <li>Optionale Kurzbeschreibung (Bio, max. 1.000 Zeichen) – nur wenn vom Nutzer hinterlegt</li>
                    <li>Datum der Kontoerstellung (Mitglied seit)</li>
                    <li>Liste der erstellten FPV-Spots</li>
                </ul>
                <p>Die E-Mail-Adresse wird <strong>nicht</strong> öffentlich angezeigt.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO) – Bereitstellung des Community-Profils als Teil des Plattformangebots.</p>
                <p><strong>Speicherdauer:</strong> Bis zur Löschung des Kontos.</p>
            </section>

            <!-- 7. Nutzerinhalte -->
            <section aria-labelledby="nutzerinhalte">
                <h2 id="nutzerinhalte" class="h5 mt-4">7. Nutzergenerierte Inhalte</h2>
                <p>Registrierte Nutzer können folgende Inhalte erstellen, die öffentlich auf der Website angezeigt werden:</p>
                <ul>
                    <li><strong>FPV-Spots:</strong> Name, Beschreibung (max. 2.000 Zeichen), GPS-Koordinaten, Kategorie (z.&nbsp;B. Bando, Feld, Park), Schwierigkeitsgrad sowie Parkinformationen.</li>
                    <li><strong>Bilder:</strong> Fotos zu einzelnen Spots (JPEG/PNG, max. 5&nbsp;MB). Hochgeladene Bilder werden unter einem zufällig generierten Dateinamen gespeichert; der ursprüngliche Dateiname wird nicht übernommen oder gespeichert.</li>
                    <li><strong>Bewertungen:</strong> Sternbewertungen (1–5) je Spot; pro Nutzer und Spot ist eine Bewertung möglich.</li>
                    <li><strong>Kommentare:</strong> Textbeiträge zu einzelnen Spots (max. 1.000 Zeichen).</li>
                </ul>
                <p>Alle Inhalte werden zusammen mit der Benutzer-ID und dem Erstellungszeitpunkt gespeichert und sind öffentlich einsehbar.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO).</p>
                <p><strong>Löschung:</strong> Inhalte können jederzeit vom Ersteller oder einem Administrator gelöscht werden. Bei Löschung eines Spots werden alle zugehörigen Bilder, Kommentare, Bewertungen und Meldungen automatisch mitgelöscht.</p>
                <p><strong>Speicherdauer:</strong> Bis zur Löschung durch den Nutzer, einen Administrator oder bei Kontoauflösung.</p>
            </section>

            <!-- 8. Direktnachrichten -->
            <section aria-labelledby="direktnachrichten">
                <h2 id="direktnachrichten" class="h5 mt-4">8. Direktnachrichten</h2>
                <p>Registrierte Nutzer können sich über ein internes Nachrichtensystem private Direktnachrichten senden.</p>
                <p><strong>Verarbeitete Daten:</strong></p>
                <ul>
                    <li>Benutzer-ID des Absenders und Empfängers</li>
                    <li>Nachrichtentext (max. 2.000 Zeichen)</li>
                    <li>Zeitstempel der Erstellung sowie des Lesens (Gelesen-Status)</li>
                </ul>
                <p><strong>Sichtbarkeit:</strong> Nachrichten sind ausschließlich für die beteiligten Gesprächspartner sichtbar.</p>
                <p><strong>Benachrichtigungen:</strong> Beim Eingang einer neuen Nachricht wird eine interne Plattformbenachrichtigung erstellt, die dem Empfänger angezeigt und nach dem Lesen als gelesen markiert wird.</p>
                <p><strong>Löschung:</strong> Nutzer können eine Konversation auf ihrer Seite entfernen. Die Nachrichten verbleiben dabei für den anderen Teilnehmer sichtbar, bis auch dieser die Konversation löscht oder das Konto aufgelöst wird.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO).</p>
                <p><strong>Speicherdauer:</strong> Bis zur Löschung durch die beteiligten Nutzer oder bei Kontoauflösung.</p>
            </section>

            <!-- 9. Verbesserungsvorschläge -->
            <section aria-labelledby="verbesserungsvorschlaege">
                <h2 id="verbesserungsvorschlaege" class="h5 mt-4">9. Verbesserungsvorschläge</h2>
                <p>Registrierte Nutzer können Verbesserungsvorschläge für die Plattform einreichen sowie für bestehende Vorschläge abstimmen.</p>
                <p><strong>Verarbeitete Daten:</strong></p>
                <ul>
                    <li>Benutzer-ID des Einreichenden</li>
                    <li>Text des Vorschlags (max. 1.000 Zeichen)</li>
                    <li>Benutzer-ID bei Abstimmungen (eine Stimme pro Nutzer und Vorschlag)</li>
                    <li>Erstellungszeitpunkt</li>
                </ul>
                <p><strong>Sichtbarkeit:</strong> Vorschläge und Abstimmungen sind ausschließlich für Administratoren einsehbar, nicht für andere Nutzer.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Weiterentwicklung und Verbesserung des Plattformangebots.</p>
                <p><strong>Speicherdauer:</strong> Bis zur Löschung durch einen Administrator oder bei Kontoauflösung.</p>
            </section>

            <!-- 10. Kontaktformular -->
            <section aria-labelledby="kontaktformular">
                <h2 id="kontaktformular" class="h5 mt-4">10. Kontaktformular</h2>
                <p>Wenn Sie unser Kontaktformular nutzen, werden folgende Daten erhoben und verarbeitet:</p>
                <ul>
                    <li>E-Mail-Adresse</li>
                    <li>Inhalt Ihrer Nachricht</li>
                </ul>
                <p>Die Übermittlung erfolgt per E-Mail über unseren Mailserver. Die Daten werden ausschließlich zur Bearbeitung Ihrer Anfrage verwendet und danach gelöscht, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Bearbeitung Ihrer Kontaktanfrage.</p>
                <p><strong>Speicherdauer:</strong> Die Daten werden nach abschließender Bearbeitung Ihrer Anfrage gelöscht, spätestens nach 90 Tagen.</p>
            </section>

            <!-- 11. Passwort zurücksetzen -->
            <section aria-labelledby="passwort-reset">
                <h2 id="passwort-reset" class="h5 mt-4">11. Passwort zurücksetzen</h2>
                <p>Über die Funktion „Passwort vergessen" können Nutzer ihr Passwort per E-Mail zurücksetzen.</p>
                <p><strong>Verarbeitete Daten:</strong></p>
                <ul>
                    <li>E-Mail-Adresse (zur Zuordnung des Kontos)</li>
                    <li>Zeitlich begrenztes, kryptografisch gesichertes Reset-Token (wird in der Datenbank ausschließlich als Hash gespeichert)</li>
                    <li>IP-Adresse und Zeitstempel der Anfrage (im Audit-Log, siehe Abschnitt 14)</li>
                </ul>
                <p><strong>Ablauf:</strong> Nach Eingabe der E-Mail-Adresse wird ein Einmal-Link an die hinterlegte Adresse versandt. Dieser Link ist <strong>60&nbsp;Minuten</strong> gültig und kann nur einmalig verwendet werden. Nach Nutzung oder Ablauf wird das Token automatisch gelöscht.</p>
                <p><strong>Sicherheitshinweis:</strong> Es wird kein Hinweis gegeben, ob eine eingegebene E-Mail-Adresse in unserem System registriert ist (Schutz vor Nutzer-Enumeration). Die Anfragen je IP-Adresse sind auf 3 in 5 Minuten begrenzt.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO) – Wiederherstellung des Kontozugangs.</p>
                <p><strong>Speicherdauer:</strong> Das Reset-Token wird nach Verwendung oder nach 60&nbsp;Minuten automatisch gelöscht.</p>
            </section>

            <!-- 12. E-Mail-Versand -->
            <section aria-labelledby="email-versand">
                <h2 id="email-versand" class="h5 mt-4">12. E-Mail-Versand (SMTP-Dienstleister)</h2>
                <p>Zum Versand transaktionaler E-Mails (Passwort-Reset-Links, Weiterleitung von Kontaktanfragen) nutzen wir den SMTP-Dienst unseres deutschen Hosting-Anbieters <strong>Kasserver</strong>.</p>
                <p><strong>Anbieter:</strong> maxcluster GmbH, Edmund-Rumpler-Straße 6, 51149 Köln, Deutschland.</p>
                <p><strong>Versandte E-Mail-Typen:</strong></p>
                <ul>
                    <li>Passwort-Reset-Links (an die hinterlegte E-Mail-Adresse des jeweiligen Nutzers)</li>
                    <li>Weitergeleitete Kontaktanfragen (an unsere interne Postfachadresse)</li>
                </ul>
                <p>Es werden <strong>keine</strong> Marketing- oder Newsletter-E-Mails versendet.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO) für Passwort-Reset-E-Mails; Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) für die Weiterleitung von Kontaktanfragen.</p>
            </section>

            <!-- 13. Protokollierung / Audit-Logs -->
            <section aria-labelledby="protokollierung">
                <h2 id="protokollierung" class="h5 mt-4">13. Sicherheitsprotokollierung (Audit-Logs)</h2>
                <p>Zur Erkennung und Abwehr von Missbrauch sowie zum Schutz unserer Systeme protokollieren wir sicherheitsrelevante Aktionen. Dabei werden folgende Daten erfasst:</p>
                <ul>
                    <li>Benutzer-ID (sofern angemeldet)</li>
                    <li>Art der Aktion (z.&nbsp;B. Anmeldung, Registrierung, Passwortwechsel)</li>
                    <li>IP-Adresse</li>
                    <li>Zeitstempel</li>
                </ul>
                <p><strong>Protokollierte Ereignisse:</strong> Registrierung, An- und Abmeldung, fehlgeschlagene Anmeldeversuche, Passwort-Reset (Anforderung und Abschluss), E-Mail-Änderung, Benutzernamen-Änderung, Passwort-Änderung, Spot-Erstellung und -Bearbeitung, Bild-Upload.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Sicherheit und Integrität unserer Systeme.</p>
                <p><strong>Speicherdauer:</strong> Audit-Logs werden nach 90 Tagen gelöscht, sofern kein berechtigtes Interesse an einer längeren Aufbewahrung besteht (z.&nbsp;B. bei laufenden Untersuchungen).</p>
            </section>

            <!-- 14. Cookies und Sessions -->
            <section aria-labelledby="cookies">
                <h2 id="cookies" class="h5 mt-4">14. Cookies, Session-Verwaltung und lokaler Speicher</h2>
                <p>Unsere Website verwendet ausschließlich technisch notwendige Cookies und Präferenz-Cookies. Analyse-, Tracking- oder Marketing-Cookies werden <strong>nicht</strong> eingesetzt.</p>

                <h3 class="h6 mt-3">Sitzungs- und Authentifizierungs-Cookies</h3>
                <ul>
                    <li><strong>Session-Cookie (<code>PHPSESSID</code>):</strong> Ermöglicht die Zuordnung von Seitenaufrufen zu einer Benutzersitzung. Enthält keine personenbezogenen Daten und wird beim Schließen des Browsers gelöscht.</li>
                    <li><strong>CSRF-Token:</strong> Wird innerhalb der Session gespeichert und dient dem Schutz vor Cross-Site-Request-Forgery-Angriffen.</li>
                    <li><strong>Angemeldet-bleiben-Cookie (<code>remember_me</code>):</strong> Enthält ein verschlüsseltes Authentifizierungs-Token für die „Eingeloggt bleiben"-Funktion. HttpOnly, Secure, SameSite=Lax. Gültig für 30&nbsp;Tage (wird nur gesetzt, wenn die entsprechende Option beim Login aktiviert wurde).</li>
                </ul>

                <h3 class="h6 mt-3">Einwilligungs-Cookies</h3>
                <ul>
                    <li><strong><code>cookie_consent</code>:</strong> Speichert die Bestätigung des Cookie-Hinweises. Gültig für 30&nbsp;Tage.</li>
                    <li><strong><code>location_consent</code>:</strong> Speichert Ihre Entscheidung zur Standortfreigabe (Zustimmung oder Ablehnung). Gültig für 365&nbsp;Tage. Es werden keine Standortkoordinaten im Cookie gespeichert.</li>
                </ul>

                <h3 class="h6 mt-3">Präferenz-Cookies</h3>
                <ul>
                    <li><strong><code>legend_types</code> und <code>legend_diffs</code>:</strong> Speichern Ihre gewählten Kartenfilter (Spot-Typen und Schwierigkeitsgrade). Gültig für 30&nbsp;Tage bei aktiver „Eingeloggt bleiben"-Funktion, andernfalls als Session-Cookie.</li>
                    <li><strong><code>fpv_map_layer</code>:</strong> Speichert Ihre bevorzugte Kartenansicht (Straße oder Satellit). Session-Cookie.</li>
                </ul>

                <h3 class="h6 mt-3">Benachrichtigungs-Cookies</h3>
                <ul>
                    <li><strong><code>last_seen_update</code></strong> und <strong><code>last_seen_suggestion</code>:</strong> Session-Cookies, die speichern, welche Plattform-Updates und Verbesserungsvorschläge Sie zuletzt gesehen haben, um Hinweissymbole korrekt darzustellen.</li>
                </ul>

                <h3 class="h6 mt-3">Lokaler Browser-Speicher</h3>
                <p>Zusätzlich zu Cookies nutzen wir den lokalen Speicher des Browsers:</p>
                <ul>
                    <li><strong>sessionStorage (<code>fpv_map_state</code>):</strong> Speichert den aktuellen Kartenausschnitt (Koordinaten und Zoom-Stufe) für die Dauer der Browser-Sitzung. Wird beim Schließen des Tabs gelöscht und nicht an unsere Server übertragen.</li>
                    <li><strong>localStorage (<code>pwa-install-dismissed</code>, <code>pwa-ios-dismissed</code>):</strong> Speichert, ob Sie den Hinweis zur Installation der App (PWA) abgelehnt haben. Gültig für 30&nbsp;Tage, ausschließlich im Browser gespeichert.</li>
                </ul>

                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) sowie § 25 Abs. 2 TTDSG – technisch notwendiger Betrieb der Website und Speicherung von Nutzereinstellungen.</p>
            </section>

            <!-- 15. Kartendienste -->
            <section aria-labelledby="kartendienst">
                <h2 id="kartendienst" class="h5 mt-4">15. Kartendienste</h2>
                <p>Für die Darstellung der interaktiven Karte werden je nach gewählter Kartenansicht Kartenkacheln (Map Tiles) von zwei verschiedenen externen Diensten geladen:</p>

                <h3 class="h6 mt-3">OpenStreetMap (Standard-Kartenansicht)</h3>
                <p>Beim Laden der Standard-Kartenansicht wird eine Verbindung zu den Servern der OpenStreetMap Foundation (OSMF) hergestellt. Dabei wird Ihre IP-Adresse an die OSMF übermittelt.</p>
                <p><strong>Anbieter:</strong> OpenStreetMap Foundation, St John's Innovation Centre, Cowley Road, Cambridge, CB4 0WS, Vereinigtes Königreich.</p>
                <p><strong>Datenschutzrichtlinie:</strong> <a href="https://wiki.osmfoundation.org/wiki/Privacy_Policy" target="_blank" rel="noopener noreferrer" class="text-white">https://wiki.osmfoundation.org/wiki/Privacy_Policy</a></p>

                <h3 class="h6 mt-3">Esri World Imagery (Satellitenansicht)</h3>
                <p>Wenn Sie die Satellitenansicht aktivieren, werden Kartenkacheln von den Servern von <strong>Esri</strong> geladen. Dabei wird Ihre IP-Adresse an Esri übermittelt.</p>
                <p><strong>Anbieter:</strong> Esri (Environmental Systems Research Institute, Inc.), 380 New York St, Redlands, CA 92373, USA.</p>
                <p><strong>Hinweis zur Drittlandübermittlung:</strong> Esri hat seinen Sitz in den USA. Die Übermittlung Ihrer IP-Adresse erfolgt auf Grundlage von Art. 49 Abs. 1 lit. b DSGVO im Zusammenhang mit der Nutzung der Satellitenansicht.</p>
                <p><strong>Datenschutzrichtlinie:</strong> <a href="https://www.esri.com/en-us/privacy/privacy-statements/privacy-statement" target="_blank" rel="noopener noreferrer" class="text-white">https://www.esri.com/en-us/privacy/privacy-statements/privacy-statement</a></p>

                <p class="mt-2"><strong>Rechtsgrundlage (beide Dienste):</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Darstellung der Karteninhalte als Kernfunktionalität unseres Dienstes.</p>
                <p>Wir selbst speichern keine Daten über Ihren Kartenzugriff oder die geladenen Kartenkacheln. Kartenkacheln können vom Browser oder Service Worker lokal zwischengespeichert werden (siehe Abschnitt 18).</p>
            </section>

            <!-- 16. Standortfreigabe -->
            <section aria-labelledby="standort">
                <h2 id="standort" class="h5 mt-4">16. Standortfreigabe (Geolocation)</h2>
                <p>Beim ersten Besuch unserer Website werden Sie gefragt, ob Sie Ihren aktuellen Standort auf der Karte anzeigen möchten. Die Abfrage erfolgt über die standardisierte Browser-API <code>navigator.geolocation</code> und nur nach Ihrer ausdrücklichen Zustimmung.</p>
                <p><strong>Verarbeitete Daten:</strong> Geografische Koordinaten (Breitengrad, Längengrad) Ihres Endgeräts zum Zeitpunkt der Freigabe.</p>
                <p><strong>Zweck:</strong> Visuelle Darstellung Ihres Standorts auf der interaktiven Karte, um nahegelegene FPV-Spots leichter zu finden.</p>
                <p><strong>Rechtsgrundlage:</strong> Einwilligung (Art. 6 Abs. 1 lit. a DSGVO).</p>
                <p><strong>Speicherung und Übertragung:</strong> Ihre Standortdaten werden <strong>ausschließlich lokal in Ihrem Browser</strong> verarbeitet und weder an unsere Server übermittelt noch gespeichert. Lediglich Ihre Entscheidung (Zustimmung oder Ablehnung) wird als Cookie (<code>location_consent</code>) für 365 Tage gespeichert, damit Sie nicht bei jedem Besuch erneut gefragt werden.</p>
                <p><strong>Widerruf:</strong> Sie können Ihre Einwilligung jederzeit widerrufen, indem Sie den Cookie <code>location_consent</code> in den Einstellungen Ihres Browsers löschen oder die Standortberechtigung in den Browser-Einstellungen entziehen.</p>
            </section>

            <!-- 17. Externe Ressourcen -->
            <section aria-labelledby="cdn">
                <h2 id="cdn" class="h5 mt-4">17. Einbindung externer Ressourcen (CDN)</h2>
                <p>Wir binden CSS- und JavaScript-Bibliotheken über externe Content Delivery Networks (CDN) ein. Beim Aufruf unserer Seiten wird eine Verbindung zu den jeweiligen CDN-Servern hergestellt, wobei Ihre IP-Adresse übermittelt wird.</p>
                <ul>
                    <li>
                        <strong>jsDelivr</strong> – Bootstrap (CSS/JS-Framework):<br>
                        <a href="https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net" target="_blank" rel="noopener noreferrer" class="text-white">https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net</a>
                    </li>
                    <li class="mt-2">
                        <strong>unpkg</strong> – Leaflet (JavaScript-Kartenbibliothek):<br>
                        Betrieben durch Cloudflare, Inc., 101 Townsend St, San Francisco, CA 94107, USA.<br>
                        <strong>Hinweis zur Drittlandübermittlung:</strong> unpkg wird über das Cloudflare-Netzwerk ausgeliefert und kann Server in den USA einbinden. Die Übermittlung erfolgt auf Grundlage von Art. 49 Abs. 1 lit. b DSGVO.
                    </li>
                </ul>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – performante und zuverlässige Auslieferung der Website.</p>
            </section>

            <!-- 18. PWA / Service Worker -->
            <section aria-labelledby="pwa">
                <h2 id="pwa" class="h5 mt-4">18. Progressive Web App (PWA) und Service Worker</h2>
                <p>Unsere Website kann als Progressive Web App (PWA) auf dem Gerät installiert werden. Zu diesem Zweck wird ein <strong>Service Worker</strong> im Browser registriert.</p>
                <p><strong>Funktionen des Service Workers:</strong></p>
                <ul>
                    <li>Statische Website-Ressourcen (CSS, JavaScript, Bilder) werden im lokalen Browser-Cache gespeichert, um die Ladegeschwindigkeit zu verbessern und eine eingeschränkte Offline-Nutzung zu ermöglichen.</li>
                    <li>Kartenkacheln werden temporär im Browser-Cache zwischengespeichert (max. 200 Einträge), um wiederholte Ladevorgänge zu beschleunigen.</li>
                    <li>API-Anfragen werden bevorzugt über das Netzwerk abgerufen; der Cache dient als Fallback bei fehlender Verbindung.</li>
                </ul>
                <p><strong>Datenschutz:</strong> Der Service Worker speichert <strong>keine personenbezogenen Daten</strong> im Cache. Es werden ausschließlich öffentliche, nicht personenbezogene Ressourcen lokal vorgehalten.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Verbesserung der Nutzererfahrung und Betriebsbereitschaft der Anwendung.</p>
            </section>

            <!-- 19. Betroffenenrechte -->
            <section aria-labelledby="betroffenenrechte">
                <h2 id="betroffenenrechte" class="h5 mt-4">19. Ihre Rechte als betroffene Person</h2>
                <p>Sie haben gemäß der DSGVO folgende Rechte:</p>
                <ul>
                    <li><strong>Auskunftsrecht (Art. 15 DSGVO):</strong> Sie können Auskunft über die von uns verarbeiteten personenbezogenen Daten verlangen.</li>
                    <li><strong>Recht auf Berichtigung (Art. 16 DSGVO):</strong> Sie können die Berichtigung unrichtiger Daten verlangen. Benutzername, E-Mail, Passwort und Bio können Sie eigenständig in Ihrem Dashboard ändern.</li>
                    <li><strong>Recht auf Löschung (Art. 17 DSGVO):</strong> Sie können die Löschung Ihrer personenbezogenen Daten verlangen, sofern keine gesetzlichen Aufbewahrungspflichten entgegenstehen. Bei Löschung Ihres Kontos werden alle zugehörigen Daten (Spots, Kommentare, Bewertungen, Nachrichten, Bilder) automatisch mitgelöscht.</li>
                    <li><strong>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO):</strong> Sie können die Einschränkung der Verarbeitung Ihrer Daten verlangen.</li>
                    <li><strong>Recht auf Datenübertragbarkeit (Art. 20 DSGVO):</strong> Sie können verlangen, Ihre Daten in einem strukturierten, maschinenlesbaren Format zu erhalten.</li>
                    <li><strong>Widerspruchsrecht (Art. 21 DSGVO):</strong> Sie können der Verarbeitung Ihrer Daten, die auf berechtigtem Interesse basiert, jederzeit widersprechen.</li>
                    <li><strong>Beschwerderecht (Art. 77 DSGVO):</strong> Sie haben das Recht, sich bei einer Datenschutzaufsichtsbehörde zu beschweren. Zuständig ist der Sächsische Datenschutz- und Transparenzbeauftragte.</li>
                </ul>
                <p>Zur Ausübung Ihrer Rechte wenden Sie sich bitte an: <a href="mailto:info@fpv-spots-germany.de" class="text-white">info@fpv-spots-germany.de</a></p>
            </section>

            <!-- 20. Datensicherheit -->
            <section aria-labelledby="datensicherheit">
                <h2 id="datensicherheit" class="h5 mt-4">20. Datensicherheit</h2>
                <p>Wir setzen technische und organisatorische Maßnahmen ein, um Ihre Daten zu schützen. Dazu gehören unter anderem:</p>
                <ul>
                    <li>Verschlüsselte Übertragung per HTTPS/TLS</li>
                    <li>Passwort-Hashing mit modernen kryptografischen Verfahren</li>
                    <li>Schutz vor Cross-Site-Request-Forgery (CSRF) und SQL-Injection</li>
                    <li>Validierung und Bereinigung aller Nutzereingaben</li>
                    <li>Ratenbegrenzung bei Anmeldeversuchen und sicherheitsrelevanten Aktionen</li>
                    <li>Token-Rotation bei der „Eingeloggt bleiben"-Funktion</li>
                </ul>
            </section>

            <!-- 21. Aktualität -->
            <section aria-labelledby="aktualitaet">
                <h2 id="aktualitaet" class="h5 mt-4">21. Aktualität und Änderung dieser Datenschutzerklärung</h2>
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

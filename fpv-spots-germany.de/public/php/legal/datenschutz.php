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
    <title>Datenschutzerklärung – FPV Spots Germany</title>
    <meta name="description" content="Datenschutzerklärung von FPV Spots Germany – Informationen zur Verarbeitung personenbezogener Daten.">
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="https://fpv-spots-germany.de/datenschutz.php">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

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
                <p><strong>Nutzungsbedingungen-Zustimmung:</strong> Bei der Registrierung (oder beim erstmaligen Login nach einer Aktualisierung der Nutzungsbedingungen) müssen Sie unseren Nutzungsbedingungen zustimmen. Der Zeitstempel dieser Zustimmung wird gespeichert und ermöglicht uns, Ihre aktuelle Zustimmung zu überprüfen.</p>
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
                    <li><strong>FPV-Spots:</strong> Name, Beschreibung (max. 2.000 Zeichen), GPS-Koordinaten, Kategorie (z.&nbsp;B. Bando, Feld, Park), Schwierigkeitsgrad sowie Parkinformationen. Die Parkinformationen können von jedem eingeloggten Nutzer bearbeitet werden; in diesem Fall wird die Benutzer-ID des letzten Bearbeiters und der Bearbeitungszeitpunkt gespeichert.</li>
                    <li><strong>Bilder:</strong> Fotos zu einzelnen Spots (JPEG/PNG, max. 5&nbsp;MB). Hochgeladene Bilder werden unter einem zufällig generierten Dateinamen gespeichert; der ursprüngliche Dateiname wird nicht übernommen oder gespeichert. Beim Upload werden EXIF-Metadaten (insbesondere GPS-Koordinaten, Aufnahmezeit und Gerätemodell) serverseitig entfernt, bevor das Bild gespeichert wird.</li>
                    <li><strong>YouTube-Videolinks:</strong> Pro Spot können bis zu 10 Links zu YouTube-Videos hinzugefügt werden. Gespeichert werden ausschließlich die 11-stellige YouTube-Video-ID sowie ein zugehöriger Titel (max. 150 Zeichen). Der Titel wird beim Speichern serverseitig einmalig über die öffentliche YouTube-oEmbed-Schnittstelle (<code>youtube.com/oembed</code>) abgerufen – dabei wird ausschließlich die Video-ID an YouTube übertragen, keine Nutzer- oder IP-Daten der Plattform-Besucher. Näheres zur Anzeige und Wiedergabe der Videos siehe Abschnitt 16b.</li>
                    <li><strong>Bewertungen:</strong> Sternbewertungen (1–5) je Spot; pro Nutzer und Spot ist eine Bewertung möglich.</li>
                    <li><strong>Kommentare:</strong> Textbeiträge zu einzelnen Spots (max. 1.000 Zeichen).</li>
                    <li><strong>Favoriten:</strong> Eingeloggte Nutzer können Spots als persönliche Favoriten speichern. Diese sind ausschließlich für den jeweiligen Nutzer sichtbar.</li>
                </ul>
                <p>Alle Inhalte werden zusammen mit der Benutzer-ID und dem Erstellungszeitpunkt gespeichert und sind öffentlich einsehbar (außer Favoriten).</p>
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
                <p><strong>Sichtbarkeit:</strong> Eingereichte Vorschläge sind öffentlich einsehbar – auch für nicht registrierte Nutzer. Abstimmungen (Benutzer-ID) sind ausschließlich für Administratoren einsehbar.</p>
                <p><strong>Admin-Kommentare und Benachrichtigungen:</strong> Administratoren können Kommentare zu Verbesserungsvorschlägen hinzufügen. Wenn ein Administrator kommentiert, erhält der Einreicher des Vorschlags eine interne Plattform-Benachrichtigung und eine E-Mail-Benachrichtigung, die den Kommentartext (max. 200 Zeichen) und einen Link zum Vorschlag enthält.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Weiterentwicklung und Verbesserung des Plattformangebots.</p>
                <p><strong>Speicherdauer:</strong> Bis zur Löschung durch einen Administrator oder bei Kontoauflösung.</p>
            </section>

            <!-- 9b. Community-Aktivitätsfeed (Neuigkeiten) -->
            <section aria-labelledby="neuigkeiten">
                <h2 id="neuigkeiten" class="h5 mt-4">9b. Community-Aktivitätsfeed („Neuigkeiten")</h2>
                <p>Auf der Seite „Neuigkeiten" werden die letzten 50 öffentlichen Aktivitäten der Community in chronologischer Reihenfolge angezeigt: neu erstellte Spots, neue Kommentare und neue Bewertungen.</p>
                <p><strong>Verarbeitete und angezeigte Daten:</strong></p>
                <ul>
                    <li>Benutzername des Erstellers (verlinkt auf das öffentliche Profil)</li>
                    <li>Spot-Name, Spot-Kategorie und Spot-ID</li>
                    <li>Bei Kommentaren: Auszug aus dem Kommentartext</li>
                    <li>Bei Bewertungen: vergebene Sterneanzahl (1–5)</li>
                    <li>Erstellungszeitpunkt</li>
                </ul>
                <p><strong>Sichtbarkeit:</strong> Die Seite ist öffentlich einsehbar – auch für nicht registrierte Nutzer. Aktivitäten zu privat markierten Spots werden <strong>nicht</strong> angezeigt.</p>
                <p><strong>Hinweismarker:</strong> Damit Nutzer erkennen, ob seit ihrem letzten Besuch neue Aktivitäten hinzugekommen sind, wird beim Aufruf der Seite „Benachrichtigungen" ein Cookie (<code>last_seen_notifications</code>) mit dem aktuellen Zeitstempel gesetzt (HttpOnly, Secure, SameSite=Lax, Gültigkeitsdauer 365&nbsp;Tage). Es werden keine personenbezogenen Daten in diesem Cookie gespeichert.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Förderung des Community-Austauschs und Sichtbarmachung von Aktivitäten.</p>
                <p><strong>Speicherdauer:</strong> Bis zur Löschung der zugrundeliegenden Inhalte (Spot, Kommentar, Bewertung) durch den Nutzer, einen Administrator oder bei Kontoauflösung.</p>
            </section>

            <!-- 9c. Forum -->
            <section aria-labelledby="forum">
                <h2 id="forum" class="h5 mt-4">9c. Forum</h2>
                <p>Registrierte Nutzer können im Forum Beiträge erstellen, kommentieren und auf Kommentare antworten (eine Antwort-Ebene).</p>
                <p><strong>Verarbeitete Daten:</strong></p>
                <ul>
                    <li>Benutzer-ID des Erstellers</li>
                    <li>Beitragstitel (3–150 Zeichen) und Beitragstext (10–5.000 Zeichen)</li>
                    <li>Bis zu 4 Bilder je Beitrag (JPEG/PNG, max. 5&nbsp;MB pro Bild). Bilder werden unter einem zufällig generierten Dateinamen gespeichert; EXIF-Metadaten werden vor der Speicherung serverseitig entfernt.</li>
                    <li>Kommentare und Antworten (2–2.000 Zeichen)</li>
                    <li>Erstellungszeitpunkt</li>
                </ul>
                <p><strong>Sichtbarkeit:</strong> Forenbeiträge, Kommentare und Bilder sind öffentlich einsehbar – auch für nicht registrierte Nutzer. Der Benutzername ist mit dem öffentlichen Profil verlinkt.</p>
                <p><strong>Bearbeitung und Löschung:</strong> Der Autor kann eigene Beiträge (Titel, Text und Bilder) jederzeit bearbeiten. Administratoren können Beiträge und Kommentare entfernen; bei Löschung eines Beitrags werden alle zugehörigen Bilder, Kommentare und Antworten automatisch mitgelöscht.</p>
                <p><strong>Rate-Limit:</strong> Maximal 10 Beiträge sowie 30 Kommentare pro Nutzer und Stunde.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO) für die Foren-Funktion; berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) hinsichtlich Rate-Limit-Protokollierung und Spam-Prävention.</p>
                <p><strong>Speicherdauer:</strong> Bis zur Löschung durch den Autor, einen Administrator oder bei Kontoauflösung.</p>
            </section>

            <!-- 10. Spot-Meldungen -->
            <section aria-labelledby="spot-meldungen">
                <h2 id="spot-meldungen" class="h5 mt-4">10. Spot-Meldungen</h2>
                <p>Eingeloggte Nutzer können Spots über ein Meldeformular melden, falls sie Probleme mit Kommentaren, Fotos, oder den Spot-Informationen entdecken.</p>
                <p><strong>Verarbeitete Daten:</strong></p>
                <ul>
                    <li>Benutzer-ID des Einreichenden</li>
                    <li>Spot-ID</li>
                    <li>Meldungstyp (Kommentar, Foto, Spot-Info, Spot-Allgemein)</li>
                    <li>Meldungstext (10–1.000 Zeichen)</li>
                    <li>Erstellungszeitpunkt</li>
                </ul>
                <p><strong>Sichtbarkeit:</strong> Meldungen sind ausschließlich für Administratoren einsehbar.</p>
                <p><strong>Rate-Limit:</strong> Maximal 10 Meldungen pro Nutzer und Stunde; pro Spot und Meldungstyp ist maximal 1 Meldung pro Nutzer pro Tag möglich.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Plattformintegrität und Missbrauchsprävention.</p>
                <p><strong>Speicherdauer:</strong> Bis zur Bearbeitung durch einen Administrator oder bei Kontoauflösung des Einreichenden.</p>
            </section>

            <!-- 11. Kontaktformular -->
            <section aria-labelledby="kontaktformular">
                <h2 id="kontaktformular" class="h5 mt-4">11. Kontaktformular</h2>
                <p>Wenn Sie unser Kontaktformular nutzen, werden folgende Daten erhoben und verarbeitet:</p>
                <ul>
                    <li>E-Mail-Adresse</li>
                    <li>Inhalt Ihrer Nachricht</li>
                    <li>Falls Sie eingeloggt sind: Ihre Benutzer-ID</li>
                </ul>
                <p><strong>Verarbeitung:</strong> Die Anfrage wird in unserer Datenbank gespeichert und per E-Mail an <strong>kontakt@fpv-spots-germany.de</strong> weitergeleitet. Die Daten werden zur Bearbeitung Ihrer Anfrage verwendet.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Bearbeitung Ihrer Kontaktanfrage.</p>
                <p><strong>Speicherdauer:</strong> Die Daten werden in der Datenbank nach abschließender Bearbeitung Ihrer Anfrage gelöscht, spätestens nach 90 Tagen.</p>
            </section>

            <!-- 12. Passwort zurücksetzen -->
            <section aria-labelledby="passwort-reset">
                <h2 id="passwort-reset" class="h5 mt-4">12. Passwort zurücksetzen</h2>
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

            <!-- 13. E-Mail-Versand -->
            <section aria-labelledby="email-versand">
                <h2 id="email-versand" class="h5 mt-4">13. E-Mail-Versand (SMTP-Dienstleister)</h2>
                <p>Zum Versand transaktionaler E-Mails nutzen wir den SMTP-Dienst unseres deutschen Hosting-Anbieters <strong>Kasserver</strong>.</p>
                <p><strong>Anbieter:</strong> maxcluster GmbH, Edmund-Rumpler-Straße 6, 51149 Köln, Deutschland.</p>
                <p><strong>Versandte E-Mail-Typen:</strong></p>
                <ul>
                    <li>Passwort-Reset-Links (an die hinterlegte E-Mail-Adresse des jeweiligen Nutzers)</li>
                    <li>Weitergeleitete Kontaktanfragen (an unsere interne Postfachadresse)</li>
                    <li>Benachrichtigungen bei Admin-Kommentaren zu Verbesserungsvorschlägen (an den Einreicher des Vorschlags)</li>
                </ul>
                <p>Es werden <strong>keine</strong> Marketing- oder Newsletter-E-Mails versendet.</p>
                <p><strong>Rechtsgrundlage:</strong> Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO) für Passwort-Reset-E-Mails; Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) für die Weiterleitung von Kontaktanfragen und Benachrichtigungen.</p>
            </section>

            <!-- 14. Protokollierung / Audit-Logs -->
            <section aria-labelledby="protokollierung">
                <h2 id="protokollierung" class="h5 mt-4">14. Sicherheitsprotokollierung (Audit-Logs)</h2>
                <p>Zur Erkennung und Abwehr von Missbrauch sowie zum Schutz unserer Systeme protokollieren wir sicherheitsrelevante Aktionen. Dabei werden folgende Daten erfasst:</p>
                <ul>
                    <li>Benutzer-ID (sofern angemeldet)</li>
                    <li>Art der Aktion (z.&nbsp;B. Anmeldung, Registrierung, Passwortwechsel)</li>
                    <li>IP-Adresse</li>
                    <li>Zeitstempel</li>
                </ul>
                <p><strong>Protokollierte Ereignisse:</strong> Registrierung, An- und Abmeldung, fehlgeschlagene Anmeldeversuche, Passwort-Reset (Anforderung und Abschluss), E-Mail-Änderung, Benutzernamen-Änderung, Passwort-Änderung, Spot-Erstellung und -Bearbeitung, Bild-Upload, Bild-Löschung sowie Forenaktionen (Beitrag erstellt, bearbeitet, gelöscht; Kommentar erstellt, gelöscht).</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Sicherheit und Integrität unserer Systeme.</p>
                <p><strong>Speicherdauer:</strong> Audit-Logs werden nach 90 Tagen gelöscht, sofern kein berechtigtes Interesse an einer längeren Aufbewahrung besteht (z.&nbsp;B. bei laufenden Untersuchungen).</p>
            </section>

            <!-- 15. Cookies und Sessions -->
            <section aria-labelledby="cookies">
                <h2 id="cookies" class="h5 mt-4">15. Cookies, Session-Verwaltung und lokaler Speicher</h2>
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
                    <li><strong><code>legend_types</code>, <code>legend_diffs</code> und <code>legend_sizes</code>:</strong> Speichern Ihre gewählten Kartenfilter (Spot-Typen, Schwierigkeitsgrade und Kopter-Größen). Gültig für 30&nbsp;Tage bei aktiver „Eingeloggt bleiben"-Funktion, andernfalls als Session-Cookie.</li>
                    <li><strong><code>pilot_class</code>:</strong> Speichert die von Ihnen gewählte Betriebsklasse Ihres Kopters (unter 250&nbsp;g, C1/C2 oder ab 250&nbsp;g Eigenbau), damit die Geozonen-Hinweise dazu passen. Enthält ausschließlich einen dieser drei Kennwerte, keine Angaben zu Ihrer Person oder Ihrem Gerät. Gültig für 30&nbsp;Tage bei aktiver „Eingeloggt bleiben"-Funktion, andernfalls als Session-Cookie. HttpOnly, Secure, SameSite=Lax.</li>
                </ul>

                <h3 class="h6 mt-3">Benachrichtigungs-Cookies</h3>
                <ul>
                    <li><strong><code>last_seen_notifications</code>:</strong> Speichert den Zeitstempel des letzten Besuchs der Seite „Benachrichtigungen", um das Hinweissymbol im Menü korrekt darzustellen. Gültig für 365&nbsp;Tage. HttpOnly, Secure, SameSite=Lax.</li>
                    <li><strong><code>last_seen_suggestion</code>:</strong> Speichert den Zeitstempel der letzten Ansicht der Verbesserungsvorschläge, um neue Vorschläge im Menü zu kennzeichnen. Gültig für 365&nbsp;Tage. HttpOnly, Secure, SameSite=Lax.</li>
                </ul>

                <h3 class="h6 mt-3">Lokaler Browser-Speicher</h3>
                <p>Zusätzlich zu Cookies nutzen wir den lokalen Speicher des Browsers:</p>
                <ul>
                    <li><strong>sessionStorage (<code>fpv_map_state</code>):</strong> Speichert den aktuellen Kartenausschnitt (Koordinaten und Zoom-Stufe) für die Dauer der Browser-Sitzung. Wird beim Schließen des Tabs gelöscht und nicht an unsere Server übertragen.</li>
                    <li><strong>sessionStorage (<code>fpv_map_layer</code>):</strong> Speichert Ihre bevorzugte Kartenansicht (Straße oder Satellit) für die aktuelle Browser-Sitzung.</li>
                    <li><strong>sessionStorage (<code>fpv_map_overlays</code>):</strong> Speichert, welche Geozonen-Ebenen Sie auf der Karte eingeblendet haben, für die Dauer der Browser-Sitzung.</li>
                    <li><strong>localStorage (<code>pwa-install-dismissed</code>, <code>pwa-ios-dismissed</code>):</strong> Speichert, ob Sie den Hinweis zur Installation der App (PWA) abgelehnt haben. Gültig für 30&nbsp;Tage, ausschließlich im Browser gespeichert.</li>
                </ul>

                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) sowie § 25 Abs. 2 TTDSG – technisch notwendiger Betrieb der Website und Speicherung von Nutzereinstellungen.</p>
            </section>

            <!-- 16. Kartendienste -->
            <section aria-labelledby="kartendienst">
                <h2 id="kartendienst" class="h5 mt-4">16. Kartendienste</h2>
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

            <!-- 16b. YouTube-Videoeinbindung -->
            <section aria-labelledby="youtube">
                <h2 id="youtube" class="h5 mt-4">16b. YouTube-Videoeinbindung (Zwei-Klick-Lösung)</h2>
                <p>Auf Spot-Detailseiten werden zu den von Nutzern eingereichten YouTube-Videos jeweils ein Vorschaubild (Thumbnail) sowie der Videotitel angezeigt. Die Thumbnails werden direkt von den Servern von YouTube/Google geladen (<code>img.youtube.com</code>, <code>i.ytimg.com</code>). Beim Aufruf einer Spot-Detailseite mit verlinkten Videos wird daher Ihre IP-Adresse an Google übermittelt.</p>
                <p><strong>Bewusste Datenschutzentscheidung (Zwei-Klick-Lösung):</strong> Beim bloßen Aufruf der Seite wird <strong>kein</strong> YouTube-Player geladen – es wird ausschließlich das statische Vorschaubild eingebunden. YouTube setzt in diesem Zustand <strong>keine Tracking-Cookies</strong> in Ihrem Browser.</p>
                <p><strong>Erst nach Ihrem Klick auf den Play-Button</strong> wird der Videoplayer nachgeladen – dabei verwenden wir die „erweiterte Datenschutzdomain" <code>youtube-nocookie.com</code>. Ab diesem Zeitpunkt werden Daten an Google übermittelt (u. a. Ihre IP-Adresse, Geräte- und Browserinformationen, aufgerufene URL); Google kann Einträge im lokalen Speicher Ihres Browsers anlegen und – sofern Sie in einem Google-Konto angemeldet sind – Ihr Sehverhalten Ihrem Konto zuordnen. Der Player wird nur für das jeweils angeklickte Video geladen; alle übrigen Videos der Seite bleiben unberührt.</p>
                <p>Alternativ können Sie über den Link „Auf YouTube ansehen" das Video direkt bei YouTube öffnen; dort gelten ausschließlich die Datenschutzbestimmungen von Google.</p>
                <p><strong>Anbieter:</strong> Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland (Niederlassung des US-Anbieters Google LLC).</p>
                <p><strong>Hinweis zur Drittlandübermittlung:</strong> Die Auslieferung der Vorschaubilder und des Players kann über Server in den USA erfolgen. Die Übermittlung der Vorschaubilder erfolgt auf Grundlage von Art. 49 Abs. 1 lit. b DSGVO, die Übermittlung beim aktiven Laden des Players auf Grundlage Ihrer Einwilligung nach Art. 49 Abs. 1 lit. a DSGVO – jeweils ergänzt durch die EU-Standardvertragsklauseln und das EU-US Data Privacy Framework, dem Google sich unterworfen hat.</p>
                <p><strong>Titelabruf beim Speichern:</strong> Beim Anlegen oder Bearbeiten eines Spots ruft unser Server (nicht Ihr Browser) einmalig die öffentliche oEmbed-Schnittstelle von YouTube auf, um den Videotitel zu ermitteln. Hierbei wird ausschließlich die Video-ID an YouTube übertragen – Ihre IP-Adresse oder weitere personenbezogene Daten werden dabei nicht an YouTube übermittelt.</p>
                <p><strong>Datenschutzrichtlinie Google/YouTube:</strong> <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-white">https://policies.google.com/privacy</a></p>
                <p><strong>Rechtsgrundlage:</strong> Für die Anzeige der Vorschaubilder berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Darstellung der von Nutzern eingereichten Videos als Bestandteil der Spot-Informationen. Für das Laden des Videoplayers Ihre Einwilligung (Art. 6 Abs. 1 lit. a DSGVO), die Sie durch den bewussten Klick auf den Play-Button erteilen und die Sie jederzeit durch Neuladen der Seite für die Zukunft widerrufen können.</p>
            </section>

            <!-- 16c. Geozonen (DIPUL) -->
            <section aria-labelledby="geozonen-datenschutz">
                <h2 id="geozonen-datenschutz" class="h5 mt-4">16c. Geozonen für Drohnen (DIPUL)</h2>
                <p>Auf der Karte lassen sich die amtlichen geografischen Gebiete für unbemannte Luftfahrtsysteme („Geozonen") einblenden. Die zugrunde liegenden Geodaten stammen aus dem <strong>digitalen Plattform Unbemannte Luftfahrt (dipul)</strong> der DFS Deutsche Flugsicherung GmbH.</p>
                <p><strong>Keine Übermittlung Ihrer IP-Adresse:</strong> Anders als bei den Kartendiensten in Abschnitt 16 rufen wir diese Kacheln <strong>nicht</strong> direkt aus Ihrem Browser ab. Die Anfrage stellt unser eigener Server; Ihr Browser kommuniziert ausschließlich mit <code>fpv-spots-germany.de</code>. Die DFS erfährt daher weder Ihre IP-Adresse noch, welchen Kartenausschnitt Sie betrachten. Es entsteht <strong>kein zusätzlicher Drittempfänger</strong> im Sinne der DSGVO.</p>
                <p>Die abgerufenen Kacheln werden auf unserem Server für maximal 24&nbsp;Stunden (bei temporären Betriebseinschränkungen für maximal 10&nbsp;Minuten) zwischengespeichert, um die Behördenschnittstelle zu entlasten. Der Zwischenspeicher enthält ausschließlich Kartenbilder, keine Nutzerdaten. Zur Missbrauchsvermeidung wird die Anzahl der Abrufe je IP-Adresse stundenweise gezählt; gespeichert wird dabei nur ein nicht rückrechenbarer Hashwert, der spätestens nach zwei Stunden gelöscht wird.</p>
                <p>Zusätzlich prüfen wir <strong>serverseitig und ohne Ihre Beteiligung</strong>, welche Geozonen einen eingetragenen Spot berühren, und speichern das Ergebnis zu dem jeweiligen Spot. Diese Prüfung bezieht sich auf die Spot-Koordinate, nicht auf Ihren Standort.</p>
                <p><strong>Anbieter der Geodaten:</strong> DFS Deutsche Flugsicherung GmbH, Am DFS-Campus 10, 63225 Langen. Quelle Geodaten: DFS, BKG. Lizenz: CC&nbsp;BY-ND&nbsp;4.0.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Hinweis auf luftrechtliche Beschränkungen als Kernfunktion unseres Dienstes.</p>
                <p><strong>Hinweis:</strong> Die Darstellung ist eine Orientierungshilfe und keine Rechtsberatung. Maßgeblich ist die amtliche Karte unter <a href="https://www.dipul.de/" target="_blank" rel="noopener noreferrer" class="text-white">dipul.de</a>.</p>
            </section>


            <!-- 16d. Wetterdaten (Open-Meteo, NOAA) -->
            <section aria-labelledby="wetter-datenschutz">
                <h2 id="wetter-datenschutz" class="h5 mt-4">16d. Wetterdaten (Open-Meteo, NOAA)</h2>
                <p>Auf der Detailseite eines Spots lässt sich ein Wetterbereich aufklappen, der aktuelle Bedingungen, eine Vorschau der nächsten 24&nbsp;Stunden und eine 7-Tage-Übersicht für den jeweiligen Spot anzeigt. Die Daten stammen von <strong>Open-Meteo</strong> (für Deutschland auf Grundlage des Modells ICON des Deutschen Wetterdienstes). Ergänzend zeigen wir den weltweit einheitlichen <strong>Kp-Index</strong> des <strong>NOAA Space Weather Prediction Center</strong>, ein Maß für die geomagnetische Aktivität, die sich auf die GPS-Genauigkeit auswirken kann.</p>
                <p><strong>Keine Übermittlung Ihrer IP-Adresse:</strong> Wie bei den Geozonen in Abschnitt 16c rufen wir diese Daten <strong>nicht</strong> aus Ihrem Browser ab. Die Anfrage stellt unser eigener Server; Ihr Browser kommuniziert ausschließlich mit <code>fpv-spots-germany.de</code>. Open-Meteo und die NOAA erfahren daher weder Ihre IP-Adresse noch, welchen Spot Sie betrachten. Es entsteht <strong>kein zusätzlicher Drittempfänger</strong> im Sinne der DSGVO.</p>
                <p><strong>Bezugspunkt ist der Spot, nicht Ihr Standort:</strong> Übermittelt wird ausschließlich die in unserer Datenbank hinterlegte Koordinate des aufgerufenen Spots, gerundet auf etwa 100&nbsp;Meter. Ihr eigener Standort wird dabei weder erhoben noch verwendet; die Standortfreigabe nach Abschnitt 17 ist davon unabhängig.</p>
                <p><strong>Abruf nur auf Ihre Aktion hin:</strong> Der Bereich ist beim Aufruf der Seite stets zugeklappt. Die Wetterdaten werden erst geladen, wenn Sie ihn öffnen.</p>
                <p>Die abgerufenen Wetterdaten werden auf unserem Server für maximal 15&nbsp;Minuten zwischengespeichert (der Kp-Index für maximal eine Stunde), um die kostenlos bereitgestellten Schnittstellen zu entlasten. Der Zwischenspeicher enthält ausschließlich Wetterwerte und Spot-Koordinaten, keine Nutzerdaten. Zur Missbrauchsvermeidung wird die Anzahl der tatsächlichen Abrufe je IP-Adresse stundenweise gezählt; gespeichert wird dabei nur ein nicht rückrechenbarer Hashwert, der spätestens nach zwei Stunden gelöscht wird.</p>
                <p><strong>Anbieter:</strong> Open-Meteo, Zürich, Schweiz (Lizenz der Daten: CC&nbsp;BY&nbsp;4.0) – <a href="https://open-meteo.com/en/terms" target="_blank" rel="noopener noreferrer" class="text-white">open-meteo.com/en/terms</a>. NOAA Space Weather Prediction Center, Boulder, USA – <a href="https://www.swpc.noaa.gov/" target="_blank" rel="noopener noreferrer" class="text-white">swpc.noaa.gov</a>. Beide erhalten keine personenbezogenen Daten von uns.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Unterstützung bei der Planung eines Flugs am jeweiligen Spot.</p>
                <p><strong>Hinweis:</strong> Die angezeigten Werte sind Messwerte und Modellprognosen. Sie enthalten <strong>keine Bewertung</strong>, ob ein Flug möglich, sinnvoll oder zulässig ist; diese Entscheidung treffen ausschließlich Sie als Pilot vor Ort.</p>
            </section>
            <!-- 17. Standortfreigabe -->
            <section aria-labelledby="standort">
                <h2 id="standort" class="h5 mt-4">17. Standortfreigabe (Geolocation)</h2>
                <p>Beim ersten Besuch unserer Website werden Sie gefragt, ob Sie Ihren aktuellen Standort auf der Karte anzeigen möchten. Die Abfrage erfolgt über die standardisierte Browser-API <code>navigator.geolocation</code> und nur nach Ihrer ausdrücklichen Zustimmung.</p>
                <p><strong>Verarbeitete Daten:</strong> Geografische Koordinaten (Breitengrad, Längengrad) Ihres Endgeräts zum Zeitpunkt der Freigabe.</p>
                <p><strong>Zweck:</strong> Visuelle Darstellung Ihres Standorts auf der interaktiven Karte, um nahegelegene FPV-Spots leichter zu finden.</p>
                <p><strong>Rechtsgrundlage:</strong> Einwilligung (Art. 6 Abs. 1 lit. a DSGVO).</p>
                <p><strong>Speicherung und Übertragung:</strong> Ihre Standortdaten werden <strong>ausschließlich lokal in Ihrem Browser</strong> verarbeitet und weder an unsere Server übermittelt noch gespeichert. Lediglich Ihre Entscheidung (Zustimmung oder Ablehnung) wird als Cookie (<code>location_consent</code>) für 365 Tage gespeichert, damit Sie nicht bei jedem Besuch erneut gefragt werden.</p>
                <p><strong>Widerruf:</strong> Sie können Ihre Einwilligung jederzeit widerrufen, indem Sie den Cookie <code>location_consent</code> in den Einstellungen Ihres Browsers löschen oder die Standortberechtigung in den Browser-Einstellungen entziehen.</p>
            </section>

            <!-- 18. Externe Ressourcen -->
            <section aria-labelledby="cdn">
                <h2 id="cdn" class="h5 mt-4">18. Einbindung externer Ressourcen (CDN)</h2>
                <p>Wir binden <strong>keine</strong> CSS- oder JavaScript-Bibliotheken über externe Content Delivery Networks (CDN) ein. Sämtliche verwendeten Bibliotheken – Bootstrap, Bootstrap Icons und Leaflet – werden von unserem eigenen Server ausgeliefert. Beim Laden dieser Dateien wird daher keine Verbindung zu Dritten hergestellt und Ihre IP-Adresse nicht an CDN-Betreiber übermittelt.</p>
                <p>Externe Verbindungen entstehen ausschließlich durch die in Abschnitt 16 beschriebenen Kartenanbieter sowie durch eingebettete YouTube-Videos.</p>
            </section>

            <!-- 19. PWA / Service Worker -->
            <section aria-labelledby="pwa">
                <h2 id="pwa" class="h5 mt-4">19. Progressive Web App (PWA) und Service Worker</h2>
                <p>Unsere Website kann als Progressive Web App (PWA) auf dem Gerät installiert werden. Zu diesem Zweck wird ein <strong>Service Worker</strong> im Browser registriert.</p>
                <p><strong>Funktionen des Service Workers:</strong></p>
                <ul>
                    <li>Statische Website-Ressourcen (CSS, JavaScript, Bilder) werden im lokalen Browser-Cache gespeichert, um die Ladegeschwindigkeit zu verbessern.</li>
                    <li>Kartenkacheln werden temporär im Browser-Cache zwischengespeichert (max. 200 Einträge), um wiederholte Ladevorgänge zu beschleunigen.</li>
                    <li>API-Anfragen werden über das Netzwerk abgerufen.</li>
                    <li>Eine Offline-Nutzung der Anwendung ist nicht vorgesehen. Besteht keine Internetverbindung, wird lediglich eine lokale Hinweisseite angezeigt.</li>
                </ul>
                <p><strong>Datenschutz:</strong> Der Service Worker speichert <strong>keine personenbezogenen Daten</strong> im Cache. Es werden ausschließlich öffentliche, nicht personenbezogene Ressourcen lokal vorgehalten.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Verbesserung der Nutzererfahrung und Betriebsbereitschaft der Anwendung.</p>
            </section>

            <!-- 20. Betroffenenrechte -->
            <section aria-labelledby="betroffenenrechte">
                <h2 id="betroffenenrechte" class="h5 mt-4">20. Ihre Rechte als betroffene Person</h2>
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

            <!-- 21. Datensicherheit -->
            <section aria-labelledby="datensicherheit">
                <h2 id="datensicherheit" class="h5 mt-4">21. Datensicherheit</h2>
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

            <!-- 21b. Open Source / GitHub -->
            <section aria-labelledby="open-source">
                <h2 id="open-source" class="h5 mt-4">21b. Open-Source-Veröffentlichung des Quellcodes</h2>
                <p>Der vollständige Quellcode dieser Plattform ist als Open Source auf GitHub veröffentlicht und unter <a href="https://github.com/philc1357/FPV-SPOTS-GERMANY" target="_blank" rel="noopener noreferrer" class="text-white">github.com/philc1357/FPV-SPOTS-GERMANY</a> einsehbar. Unsere Seite „GitHub" verlinkt lediglich auf dieses externe Repository.</p>
                <p>Bei Aufruf des externen GitHub-Links werden Daten an GitHub Inc. (88 Colin P. Kelly Jr. Street, San Francisco, CA 94107, USA) übermittelt. Die Datenschutzbestimmungen von GitHub finden Sie unter <a href="https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement" target="_blank" rel="noopener noreferrer" class="text-white">docs.github.com/site-policy/privacy-policies/github-general-privacy-statement</a>.</p>
                <p>Im veröffentlichten Quellcode sind <strong>keine</strong> personenbezogenen Daten von Nutzern oder Zugangsdaten enthalten – Datenbank- und SMTP-Zugangsdaten werden über eine lokale, nicht im Repository enthaltene <code>.env</code>-Datei geladen.</p>
                <p><strong>Rechtsgrundlage:</strong> Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO) – Transparenz über die Funktionsweise der Plattform und Nachvollziehbarkeit der Datenverarbeitung.</p>
            </section>

            <!-- 22. Aktualität -->
            <section aria-labelledby="aktualitaet">
                <h2 id="aktualitaet" class="h5 mt-4">22. Aktualität und Änderung dieser Datenschutzerklärung</h2>
                <p>Diese Datenschutzerklärung ist aktuell gültig und hat den Stand Mai 2026.</p>
                <p>Durch die Weiterentwicklung unserer Website oder aufgrund geänderter gesetzlicher Vorgaben kann es notwendig werden, diese Datenschutzerklärung zu ändern. Die jeweils aktuelle Fassung kann jederzeit auf dieser Seite abgerufen werden.</p>
            </section>

            <a href="/" class="btn btn-primary w-100 mt-4 mb-2">Zur Karte</a>
        </article>
    </main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
</body>
</html>

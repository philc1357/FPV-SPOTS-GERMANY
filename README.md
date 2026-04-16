# FPV Spots Germany

Community-Plattform zum Teilen und Bewerten von FPV-Drohnen-Flugspots in Deutschland. Nutzer können Spots auf einer interaktiven Karte eintragen, bewerten, kommentieren, Fotos hochladen und sich per Direktnachricht austauschen.

## Tech-Stack

| Komponente | Technologie |
|------------|-------------|
| Backend | PHP 7.4+ mit PDO (MySQL/MariaDB) |
| Frontend | Bootstrap 5.3, Leaflet 1.9 |
| Kartenkacheln | OpenStreetMap (Standard), Esri World Imagery (Satellit) |
| Abhängigkeiten | Composer (`vlucas/phpdotenv`, `phpmailer/phpmailer`) |
| E-Mail | PHPMailer 6 via SMTP/SSL (Kasserver) |
| PWA | Service Worker, Web App Manifest |

## Features

- Interaktive Vollbild-Karte mit Spot-Markern (Leaflet + OpenStreetMap / Esri Satellit)
- Spot-Kategorien: Bando, Feld, Gebirge, Park, Verein, Wasser, Sonstige
- Schwierigkeitsgrade: Anfänger, Mittel, Fortgeschritten, Profi
- Filterbare Kartenlegende (Typ und Schwierigkeit, persistiert per Cookie)
- Spot-Detailansicht mit Fotos, Bewertungen und Kommentaren
- Spot-Bearbeitung und -Löschung durch Eigentümer oder Admin
- Sternebewertungen (1–5) und Kommentarsystem (bearbeiten/löschen)
- Foto-Upload (JPG/PNG, max. 5 MB) pro Spot
- Community-pflegbare Parkinformationen je Spot
- Spot-Meldungen (Inhaltsverstöße an Admins melden)
- Direktnachrichten zwischen registrierten Nutzern (inkl. Benachrichtigungen)
- Öffentliche Nutzerprofile mit optionaler Bio und Spot-Übersicht
- Benutzerverwaltung (Registrierung, Login, Profil)
- Benutzerdaten ändern: Benutzername, E-Mail-Adresse, Passwort, Bio
- „Angemeldet bleiben" via sicherem Remember-Me-Token (30 Tage)
- Passwort-Reset per E-Mail (zeitlich begrenzte Tokens)
- Dashboard mit eigenen Spots
- Verbesserungsvorschläge mit Community-Voting
- Öffentlicher Changelog / Update-Feed
- Kontaktformular
- Impressum und Datenschutzerklärung
- Progressive Web App (installierbar, offline-fähig)
- Standortanzeige (nur lokal im Browser, nicht gespeichert)

## Projektstruktur

```
fpv-spots-germany.de/           ← Webroot
├── index.php                   ← Hauptseite (Vollbild-Karte)
├── manifest.json               ← PWA-Manifest
├── sw.js                       ← Service Worker
├── sitemap.xml
├── robots.txt
├── offline.html                ← PWA-Offline-Fallback
│
├── public/
│   ├── css/
│   │   ├── map.css
│   │   ├── dashboard.css
│   │   ├── spot_detail.css
│   │   ├── updates.css
│   │   └── kritik.css
│   ├── js/
│   │   ├── map.js              ← Karten- und AJAX-Logik
│   │   └── pwa.js              ← PWA Install-Banner, Update-Trigger
│   ├── php/
│   │   ├── api/
│   │   │   ├── spots.php       ← GET alle / POST neuen Spot
│   │   │   ├── spot.php        ← GET/PUT/DELETE Einzelspot
│   │   │   ├── messages.php    ← Direktnachrichten-API
│   │   │   ├── update_bio.php  ← Bio-Aktualisierung
│   │   │   └── save_legend.php ← Legende-Filter per Cookie speichern
│   │   ├── dashboard.php       ← Profil + Meine Spots
│   │   ├── profile.php         ← Öffentliches Nutzerprofil
│   │   ├── messages.php        ← Direktnachrichten-Oberfläche
│   │   ├── spot_detail.php     ← Spot-Detailseite (SSR)
│   │   ├── edit_spot.php
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── forgot_password.php
│   │   ├── reset_password.php
│   │   ├── change_username.php
│   │   ├── change_email.php
│   │   ├── change_password.php
│   │   ├── kontakt.php
│   │   ├── kritik.php          ← Verbesserungsvorschläge mit Voting
│   │   ├── updates.php         ← Changelog
│   │   ├── impressum.php
│   │   └── datenschutz.php
│   ├── uploads/imgs/           ← Nutzer-Uploads (randomisierte Dateinamen)
│   └── includes/
│       ├── header.php
│       ├── login_modal.php
│       ├── cookie_banner.php
│       └── update_banner.php
│
└── private/
    ├── php/
    │   ├── db.php              ← PDO-Verbindung via .env
    │   ├── auth_check.php
    │   ├── mailer.php          ← Kontaktformular-Mailer
    │   ├── mailer_info.php     ← Transaktionale Mailer (Passwort-Reset)
    │   ├── spot_submit.php
    │   ├── edit_spot_submit.php
    │   ├── delete_spot_submit.php
    │   ├── parking_info_submit.php
    │   ├── report_submit.php
    │   ├── comment_submit.php
    │   ├── comment_edit_submit.php
    │   ├── comment_delete_submit.php
    │   ├── rate_submit.php
    │   ├── upload_submit.php
    │   ├── login_submit.php
    │   ├── logout_submit.php
    │   ├── register_submit.php
    │   ├── forgot_password_submit.php
    │   ├── reset_password_submit.php
    │   ├── suggestion_submit.php
    │   ├── suggestion_vote_submit.php
    │   ├── suggestion_delete_submit.php
    │   ├── suggestion_comment_submit.php
    │   ├── suggestion_comment_delete_submit.php
    │   ├── kontakt_submit.php
    │   ├── update_submit.php
    │   └── data_changes/
    │       ├── change_username_submit.php
    │       ├── change_email_submit.php
    │       └── change_password_submit.php
    └── js/
        └── password_confirm.js
```

## Datenbankschema

Das Schema (`database.sql`) enthält folgende Tabellen:

| Tabelle | Beschreibung |
|---------|-------------|
| `users` | Benutzerkonten (username, email, password_hash, bio, admin-Flag) |
| `spots` | FPV-Spots mit Koordinaten, Typ, Schwierigkeit und Parkinformationen |
| `comments` | Kommentare zu Spots |
| `ratings` | Sternebewertungen (1 Bewertung pro Nutzer pro Spot) |
| `spot_images` | Hochgeladene Bilder, verknüpft mit Spot und Nutzer |
| `spot_reports` | Meldungen zu Spots (Inhaltsverstöße) |
| `conversations` | Konversationen zwischen je zwei Nutzern |
| `messages` | Einzelne Nachrichten einer Konversation |
| `user_notifications` | Interne Benachrichtigungen (z.&nbsp;B. neue Nachricht) |
| `remember_tokens` | Selector/Validator-Paare für „Angemeldet bleiben" |
| `password_reset_tokens` | Zeitlich begrenzte Tokens für Passwort-Reset per E-Mail |
| `suggestions` | Verbesserungsvorschläge der Community |
| `suggestion_votes` | Votes auf Vorschläge (1 Vote pro Nutzer pro Vorschlag) |
| `suggestion_comments` | Admin-Kommentare zu Vorschlägen |
| `contact_requests` | Eingehende Kontaktformular-Nachrichten |
| `audit_logs` | Sicherheitsrelevante Aktionen mit User-ID und IP |
| `updates` | Changelog-Einträge (nur Admin kann erstellen) |

Alle Fremdschlüsselbeziehungen sind mit `ON DELETE CASCADE` bzw. `ON DELETE SET NULL` definiert. Koordinaten werden als `DECIMAL(10,7)` gespeichert.

## API-Endpunkte

Alle API-Endpunkte liegen unter `public/php/api/` und antworten mit `application/json`.

| Methode | Endpunkt | Auth | Beschreibung |
|---------|----------|------|-------------|
| `GET` | `/public/php/api/spots.php` | nein | Alle Spots laden |
| `POST` | `/public/php/api/spots.php` | ja + CSRF | Neuen Spot erstellen |
| `GET` | `/public/php/api/spot.php?id=X` | nein | Einzelnen Spot laden |
| `POST` + `_method=PUT` | `/public/php/api/spot.php?id=X` | ja + CSRF | Spot bearbeiten (Eigentümer/Admin) |
| `POST` + `_method=DELETE` | `/public/php/api/spot.php?id=X` | ja + CSRF | Spot löschen (Eigentümer/Admin) |
| `GET` | `/public/php/api/messages.php` | ja | Konversationen / Nachrichten abrufen |
| `POST` | `/public/php/api/messages.php` | ja + CSRF | Nachricht senden, Konversation löschen |
| `POST` | `/public/php/api/update_bio.php` | ja + CSRF | Profil-Bio aktualisieren |
| `POST` | `/public/php/api/save_legend.php` | nein | Legendenfilter als Cookie speichern |

Da HTML-Formulare und die Fetch-API kein `PUT`/`DELETE` senden, wird das `_method`-Override-Muster über `POST` verwendet.

## Progressive Web App (PWA)

Die App ist vollständig als PWA ausgebaut:

- **Installierbar** auf Android, iOS (via „Zum Startbildschirm") und Desktop
- **Offline-fähig** dank Service Worker (`sw.js`) mit vier Caching-Strategien:
  - **Cache-first** – statische Assets und CDN-Ressourcen (Bootstrap, Leaflet)
  - **Network-first mit Cache-Fallback** – API-Aufrufe
  - **Stale-while-revalidate** – OpenStreetMap-Kartenkacheln (max. 200 Einträge)
  - **Navigation mit Offline-Fallback** – PHP-Seiten → `offline.html`
- **Automatische Update-Erkennung** mit Hinweis-Banner und `SKIP_WAITING`-Trigger
- **Icons** in 8 Größen (72px bis 512px), inkl. maskable Icons

## Architektur

Die Anwendung folgt einem klassischen PHP-MVC-nahen Muster ohne Framework:

- **Rendering:** Server-Side Rendering (SSR) für alle HTML-Seiten
- **Karte:** Die Hauptseite rendert eine Vollbild-Leaflet-Karte. Spot-Daten werden beim Seitenload asynchron per `Fetch API` aus `spots.php` geladen und als Marker eingetragen
- **Interaktion:** Karten-Klick öffnet ein Bootstrap-Offcanvas zum Spot erstellen. Marker-Klick öffnet ein weiteres Offcanvas mit der Spot-Vorschau
- **Direktnachrichten:** Polling-basiert via `messages.php`-API; Gelesen-Status und Benachrichtigungen werden serverseitig verwaltet
- **Authentifizierungszustand:** Wird per `<meta name="app-logged-in">` und weiteren Meta-Tags sicher an JavaScript übergeben – kein direktes JavaScript-Cookie-Parsing
- **Legende:** Filtereinstellungen werden serverseitig aus einem Cookie gelesen und per AJAX-Aufruf an `save_legend.php` persistiert

## Sicherheitskonzept

### CSRF-Schutz

Jede Session erhält ein kryptografisch sicheres Token (`bin2hex(random_bytes(32))`), das in allen Formularen als Hidden-Field eingebettet und serverseitig mit `hash_equals()` geprüft wird. Betroffen sind sämtliche schreibenden Endpunkte: Login, Registrierung, Spot-Erstellung, Kommentare, Bewertungen, Uploads, Nachrichten und Profiländerungen.

### SQL-Injection-Schutz

Ausschließlich Prepared Statements mit Parameterbindung (PDO) — an keiner Stelle wird Benutzereingabe per String-Konkatenation in Queries eingebaut.

```php
$stmt = $pdo->prepare("SELECT * FROM spots WHERE id = ?");
$stmt->execute([$id]);
```

### XSS-Prävention

- **Serverseitig:** Alle Benutzerdaten werden vor der Ausgabe mit `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` escaped.
- **Clientseitig:** Eine eigene `esc()`-Funktion in `map.js` erzeugt Textknoten statt `innerHTML`, um DOM-basiertes XSS zu verhindern.
- **CDN-Ressourcen:** Bootstrap und Leaflet werden mit Subresource Integrity (SRI) eingebunden.

### Authentifizierung und Sitzungsverwaltung

- Passwort-Hashing mit `password_hash()` / `password_verify()` (Argon2id)
- Session-ID-Regeneration nach erfolgreichem Login (`session_regenerate_id()`)
- Vollständige Session-Zerstörung nach Passwortänderung (erzwingt Re-Login)
- Passwortanforderungen: 8–50 Zeichen (Obergrenze verhindert BCrypt-Trunkierung)
- „Angemeldet bleiben": Selector/Validator-Token-Paar, Validator nur als Hash gespeichert, Token-Rotation bei jeder Verwendung

### Autorisierung

- Spot-Bearbeitung/-Löschung: nur Eigentümer oder Admin
- Kommentar-Bearbeitung: nur Eigentümer
- Kommentar-Löschung: Eigentümer oder Admin
- Verbesserungsvorschlag-Löschung: nur Admin
- Dashboard/Profilseiten: Login erforderlich

### Datei-Upload-Sicherheit

| Prüfung | Methode |
|---------|---------|
| MIME-Type | `finfo()` (liest tatsächlichen Dateiinhalt) |
| Dateiendung | Whitelist: jpg, jpeg, png |
| Bildvalidierung | `getimagesize()` |
| Dateigröße | Max. 5 MB |
| Dateiname | Randomisiert: `bin2hex(random_bytes(16))` |

### Eingabevalidierung

- Enum-Validierung für Spot-Typen und Schwierigkeitsgrade (Allowlist)
- Koordinaten-Prüfung: Latitude −90 bis 90, Longitude −180 bis 180
- Längenprüfung auf Strings (Name, Beschreibung, Kommentare, Vorschläge)
- Serverseitige Validierung auf allen Endpunkten (Client-Validierung wird nicht vertraut)

### Redirect-Validierung

Post-Login-Redirects werden gegen eine Whitelist-Regex geprüft, um Open-Redirect-Angriffe zu verhindern:

```php
preg_match('#^(\.\./)*public/php/[a-zA-Z0-9_]+\.php(\?[a-zA-Z0-9_=&]+)?$#', $redirect)
```

### Audit-Logging

Sicherheitsrelevante Aktionen werden mit User-ID, IP-Adresse und Zeitstempel protokolliert:

| Aktion | Auslöser |
|--------|---------|
| `REGISTER_SUCCESS` | Neue Registrierung |
| `LOGIN_SUCCESS` | Erfolgreiche Anmeldung |
| `LOGIN_FAILED` | Fehlgeschlagener Anmeldeversuch (ab 5 in 5 Min. → Rate-Limit 429) |
| `PASSWORD_RESET_REQUESTED` | Passwort-Reset angefordert |
| `PASSWORD_RESET_COMPLETED` | Passwort erfolgreich zurückgesetzt |
| `EMAIL_CHANGED` | E-Mail-Adresse geändert |
| `USERNAME_CHANGED` | Benutzername geändert |
| `PASSWORD_CHANGED` | Passwort geändert |
| `SPOT_CREATED` | Neuer Spot erstellt |
| `SPOT_EDITED` | Spot bearbeitet |
| `IMAGE_UPLOADED` | Bild hochgeladen |

### API-Sicherheit

- **GET:** Öffentlich, nur lesender Zugriff
- **POST/PUT/DELETE:** Authentifizierung und CSRF-Token erforderlich
- Korrekte HTTP-Statuscodes (400, 401, 403, 404, 405, 500)
- Generische Fehlermeldungen nach außen, Details nur intern geloggt

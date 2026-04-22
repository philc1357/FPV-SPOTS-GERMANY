# FPV Spots Germany

Community-Plattform zum Teilen und Bewerten von FPV-Drohnen-Flugspots in Deutschland. Nutzer können Spots auf einer interaktiven Karte eintragen, bewerten, kommentieren, Fotos hochladen und sich per Direktnachricht austauschen.

## Tech-Stack

| Komponente | Technologie |
|------------|-------------|
| Backend | PHP 7.4+ mit PDO (MySQL/MariaDB) |
| Frontend | Bootstrap 5.3, Leaflet 1.9 |
| Kartenkacheln | OpenStreetMap (Standard), Esri World Imagery (Satellit) |
| Abhängigkeiten | Composer (`vlucas/phpdotenv`) |
| E-Mail | PHPMailer 6 via SMTP/SSL |
| PWA | Service Worker, Web App Manifest |
| Webserver | Apache 2.4 mit mod_rewrite + mod_headers |

## Systemanforderungen

- PHP 7.4 oder neuer (empfohlen: 8.1+)
- MySQL 5.7+ oder MariaDB 10.3+
- Apache 2.4 mit aktivierten Modulen: `mod_rewrite`, `mod_headers`
- Composer (für `vlucas/phpdotenv`)
- HTTPS-Zertifikat (HTTPS-Redirect ist in `.htaccess` aktiv)

## Setup

### 1. Repository klonen

```bash
git clone https://github.com/philc1357/fpv-spots-germany.git
cd fpv-spots-germany
```

### 2. Composer-Abhängigkeiten installieren

```bash
composer install
```

### 3. `.env`-Datei anlegen

Im Projekt-Root (neben `composer.json`) eine `.env`-Datei mit folgenden Werten anlegen:

```env
DB_HOST=localhost
DB_NAME=fpv_spots
DB_USER=dbuser
DB_PASS=dbpassword
SMTP_HOST=mail.example.com
SMTP_USER=noreply@example.com
SMTP_PASS=smtppassword
```

### 4. Datenbank einrichten

```bash
mysql -u root -p fpv_spots < database.sql
```

### 5. Apache-Webroot

Den Apache-Webroot auf den Unterordner `fpv-spots-germany.de/` zeigen lassen:

```apache
DocumentRoot /pfad/zum/projekt/fpv-spots-germany.de
```

`AllowOverride All` muss für das Verzeichnis aktiv sein, damit `.htaccess` greift.

---

## Features

### Karte & Spots
- Interaktive Vollbild-Karte mit Spot-Markern (Leaflet + OpenStreetMap / Esri Satellit)
- Spot-Kategorien: Bando, Feld, Gebirge, Park, Wald, Windpark, Sonstige
- Schwierigkeitsgrade: Anfänger, Mittel, Fortgeschritten, Profi
- Coptergröße je Spot: Tinywhoop, 2–3 Zoll, 4–5 Zoll, 5+ Zoll
- Filterbare Kartenlegende (Typ, Schwierigkeit und Coptergröße, persistiert per Cookie)
- Standortanzeige (nur lokal im Browser, nicht gespeichert)
- Spot-Favoriten: Spots merken und im Dashboard abrufen

### Spot-Detailseite
- Spot-Detailansicht mit Fotos, Bewertungen und Kommentaren
- Spot-Bearbeitung und -Löschung durch Eigentümer oder Admin
- Sternebewertungen (1–5) und Kommentarsystem (bearbeiten/löschen)
- Foto-Upload (JPG/PNG, max. 5 MB, bis zu 30 Bilder pro Spot) pro Spot
- Community-pflegbare Parkinformationen je Spot (jeder angemeldete Nutzer kann bearbeiten)
- Spot-Meldungen (Inhaltsverstöße an Admins melden: Kommentar, Foto, Spot-Info, Spot-Allgemein)

### Benutzer & Profil
- Benutzerverwaltung (Registrierung, Login, Profil)
- Benutzerdaten ändern: Benutzername, E-Mail-Adresse, Passwort, Bio (jeweils mit Passwort-Bestätigung)
- Öffentliche Nutzerprofile mit optionaler Bio, Spot-Übersicht und `last_seen`-Anzeige
- Privates Profil (Profil für andere Nutzer verbergen)
- „Angemeldet bleiben" via sicherem Remember-Me-Token (30 Tage)
- Passwort-Reset per E-Mail (zeitlich begrenzte Tokens, 1 Stunde)
- Dashboard mit eigenen Spots und gemerkten Favoriten
- Direktnachrichten zwischen registrierten Nutzern (inkl. Benachrichtigungen)

### Community & Sonstiges
- Verbesserungsvorschläge mit Community-Voting (1 Vote pro Nutzer)
- Admin kann Vorschläge kommentieren – Autor erhält E-Mail- und In-App-Benachrichtigung
- Benachrichtigungs-System (neue Direktnachrichten, Vorschlag-Kommentare)
- Öffentlicher Changelog / Update-Feed (nur Admin kann Einträge erstellen)
- Kontaktformular
- Nutzungsbedingungen mit Acceptance-Wall (neue Nutzer müssen aktiv zustimmen)
- Impressum und Datenschutzerklärung
- Progressive Web App (installierbar, offline-fähig)

---

## Projektstruktur

```
fpv-spots-germany.de/           ← Webroot (Apache DocumentRoot)
├── index.php                   ← Hauptseite (Vollbild-Karte)
├── manifest.json               ← PWA-Manifest
├── sw.js                       ← Service Worker
├── sitemap.xml
├── robots.txt
├── favicon.ico
├── offline.html                ← PWA-Offline-Fallback
│
├── public/
│   ├── css/
│   │   ├── map.css
│   │   ├── dashboard.css
│   │   ├── messages.css
│   │   ├── spot_detail.css
│   │   ├── updates.css
│   │   └── kritik.css
│   ├── js/
│   │   ├── map.js              ← Karten- und AJAX-Logik
│   │   └── pwa.js              ← PWA Install-Banner, Update-Trigger
│   ├── imgs/
│   │   ├── icons/              ← PWA-Icons (72px–512px)
│   │   └── logo.png
│   ├── html/errors/            ← Statische Fehlerseiten (login_empty, login_failed)
│   ├── uploads/imgs/           ← Nutzer-Uploads (randomisierte Dateinamen)
│   ├── includes/
│   │   ├── header.php
│   │   ├── login_modal.php
│   │   ├── register_modal.php
│   │   ├── cookie_banner.php
│   │   └── update_banner.php
│   └── php/
│       ├── api/
│       │   ├── spots.php       ← GET alle / POST neuen Spot
│       │   ├── spot.php        ← GET/PUT/DELETE Einzelspot
│       │   ├── messages.php    ← Direktnachrichten-API
│       │   ├── update_bio.php  ← Bio-Aktualisierung
│       │   └── save_legend.php ← Legende-Filter per Cookie speichern
│       ├── auth/
│       │   ├── login.php
│       │   ├── register.php
│       │   ├── forgot_password.php
│       │   └── reset_password.php
│       ├── account/
│       │   ├── dashboard.php
│       │   ├── profile.php
│       │   ├── messages.php
│       │   ├── change_username.php
│       │   ├── change_email.php
│       │   └── change_password.php
│       ├── spots/
│       │   ├── spot_detail.php
│       │   └── edit_spot.php
│       ├── community/
│       │   ├── kritik.php      ← Verbesserungsvorschläge mit Voting
│       │   └── updates.php     ← Changelog
│       ├── contact/
│       │   ├── kontakt.php
│       │   ├── kontakt_erfolg.php
│       │   └── kontakt_error.php
│       └── legal/
│           ├── impressum.php
│           ├── datenschutz.php
│           ├── nutzungsbedingungen.php
│           └── terms_accept.php
│
└── private/
    ├── data/
    │   └── best1050.txt        ← Top-1050 Passwort-Blacklist
    ├── js/
    │   └── password_confirm.js ← Client-seitige Passwortbestätigung
    └── php/
        ├── core/
        │   ├── db.php          ← PDO-Verbindung via .env
        │   ├── session_init.php← Session-Cookie-Härtung
        │   ├── auth_check.php  ← Remember-Me Auto-Login, ToS-Wall
        │   ├── client_ip.php   ← Trusted-Proxy-fähige IP-Erkennung
        │   ├── password_blacklist.php ← Blacklist-Prüfung
        │   ├── mailer.php      ← Kontaktformular-Mailer
        │   └── mailer_info.php ← Transaktionale Mailer (Passwort-Reset)
        ├── auth/
        │   ├── login_submit.php
        │   ├── logout_submit.php
        │   ├── register_submit.php
        │   ├── forgot_password_submit.php
        │   └── reset_password_submit.php
        ├── account/
        │   ├── change_username_submit.php
        │   ├── change_email_submit.php
        │   └── change_password_submit.php
        ├── spots/
        │   ├── spot_submit.php
        │   ├── edit_spot_submit.php
        │   ├── delete_spot_submit.php
        │   ├── parking_info_submit.php
        │   ├── rate_submit.php
        │   ├── upload_submit.php
        │   └── favorite_submit.php
        ├── comments/
        │   ├── comment_submit.php
        │   ├── comment_edit_submit.php
        │   └── comment_delete_submit.php
        ├── contact/
        │   ├── kontakt_submit.php
        │   └── report_submit.php
        ├── suggestions/
        │   ├── suggestion_submit.php
        │   ├── suggestion_vote_submit.php
        │   ├── suggestion_delete_submit.php
        │   ├── suggestion_comment_submit.php
        │   └── suggestion_comment_delete_submit.php
        ├── legal/
        │   └── terms_accept_submit.php
        ├── maintenance/
        │   └── cleanup_orphan_images.php ← CLI-Wartungsskript
        └── admin/
            └── update_submit.php
```

---

## URL-Routing (.htaccess)

Apache mod_rewrite mappt saubere URLs intern auf `public/php/` – Unterordner nach Bereich:

| Öffentliche URL | Interner Pfad |
|---|---|
| `/` | `index.php` |
| `/login.php` | `public/php/auth/login.php` |
| `/register.php` | `public/php/auth/register.php` |
| `/forgot_password.php` | `public/php/auth/forgot_password.php` |
| `/reset_password.php` | `public/php/auth/reset_password.php` |
| `/dashboard.php` | `public/php/account/dashboard.php` |
| `/profile.php` | `public/php/account/profile.php` |
| `/messages.php` | `public/php/account/messages.php` |
| `/change_username.php` | `public/php/account/change_username.php` |
| `/change_email.php` | `public/php/account/change_email.php` |
| `/change_password.php` | `public/php/account/change_password.php` |
| `/spot_detail.php` | `public/php/spots/spot_detail.php` |
| `/edit_spot.php` | `public/php/spots/edit_spot.php` |
| `/kritik.php` | `public/php/community/kritik.php` |
| `/updates.php` | `public/php/community/updates.php` |
| `/kontakt.php` | `public/php/contact/kontakt.php` |
| `/impressum.php` | `public/php/legal/impressum.php` |
| `/datenschutz.php` | `public/php/legal/datenschutz.php` |
| `/nutzungsbedingungen.php` | `public/php/legal/nutzungsbedingungen.php` |
| `/terms_accept.php` | `public/php/legal/terms_accept.php` |
| `/api/spots.php` | `public/php/api/spots.php` |
| `/api/spot.php` | `public/php/api/spot.php` |
| `/api/messages.php` | `public/php/api/messages.php` |
| `/api/update_bio.php` | `public/php/api/update_bio.php` |

Direkte Aufrufe der internen Pfade (z. B. `/public/php/auth/login.php`) werden per 301 auf die saubere URL umgeleitet. HTTPS wird ebenfalls erzwungen.

---

## Datenbankschema

Das Schema (`database.sql`) enthält folgende Tabellen:

| Tabelle | Beschreibung |
|---------|-------------|
| `users` | Benutzerkonten (username, email, password_hash, bio, admin-Flag, private-Flag, last_seen, terms_accepted_at) |
| `spots` | FPV-Spots mit Koordinaten, Typ, Schwierigkeit, Coptergröße und Parkinformationen |
| `comments` | Kommentare zu Spots |
| `ratings` | Sternebewertungen (1 Bewertung pro Nutzer pro Spot) |
| `spot_images` | Hochgeladene Bilder, verknüpft mit Spot und Nutzer |
| `spot_reports` | Meldungen zu Spots (Inhaltsverstöße: Kommentar, Foto, Spot-Info, Spot-Allgemein) |
| `spot_favorites` | Gemerkte Spots je Nutzer (n:m, Composite PK) |
| `conversations` | Konversationen zwischen je zwei Nutzern (soft-delete per Nutzer) |
| `messages` | Einzelne Nachrichten einer Konversation (read_at-Timestamp) |
| `user_notifications` | Interne Benachrichtigungen (neue Nachricht, Vorschlag-Kommentar) |
| `remember_tokens` | Selector/Validator-Paare für „Angemeldet bleiben" |
| `password_reset_tokens` | Zeitlich begrenzte Tokens für Passwort-Reset per E-Mail (1 Stunde) |
| `suggestions` | Verbesserungsvorschläge der Community |
| `suggestion_votes` | Votes auf Vorschläge (1 Vote pro Nutzer pro Vorschlag, Composite PK) |
| `contact_requests` | Eingehende Kontaktformular-Nachrichten |
| `audit_logs` | Sicherheitsrelevante Aktionen mit User-ID und IP |
| `updates` | Changelog-Einträge (nur Admin kann erstellen) |

Alle Fremdschlüsselbeziehungen sind mit `ON DELETE CASCADE` bzw. `ON DELETE SET NULL` definiert. Koordinaten werden als `DECIMAL(10,7)` gespeichert. Alle Tabellen: `ENGINE=InnoDB`, `CHARSET=utf8mb4`, `COLLATE=utf8mb4_unicode_ci`.

---

## API-Endpunkte

Alle API-Endpunkte liegen unter `public/php/api/` und antworten mit `Content-Type: application/json`.

| Methode | Endpunkt | Auth | Beschreibung |
|---------|----------|------|-------------|
| `GET` | `/api/spots.php` | nein | Alle Spots laden |
| `POST` | `/api/spots.php` | ja + CSRF | Neuen Spot erstellen |
| `GET` | `/api/spot.php?id=X` | nein | Einzelnen Spot laden |
| `POST` + `_method=PUT` | `/api/spot.php?id=X` | ja + CSRF | Spot bearbeiten (Eigentümer/Admin) |
| `POST` + `_method=DELETE` | `/api/spot.php?id=X` | ja + CSRF | Spot löschen (Eigentümer/Admin) |
| `GET` | `/api/messages.php` | ja | Konversationen / Nachrichten abrufen, Polling |
| `POST` | `/api/messages.php` | ja + CSRF | Nachricht senden, Konversation löschen |
| `POST` | `/api/update_bio.php` | ja + CSRF | Profil-Bio aktualisieren |
| `POST` | `/api/save_legend.php` | nein | Legendenfilter als Cookie speichern |

Da HTML-Formulare kein `PUT`/`DELETE` senden können, wird das `_method`-Override-Muster über `POST` verwendet.

---

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
- **Service Worker** wird mit `Cache-Control: no-cache` ausgeliefert, sodass Updates sofort greifen

---

## Architektur

Die Anwendung folgt einem klassischen PHP-MVC-nahen Muster ohne Framework:

- **Rendering:** Server-Side Rendering (SSR) für alle HTML-Seiten
- **Karte:** Die Hauptseite rendert eine Vollbild-Leaflet-Karte. Spot-Daten werden beim Seitenload asynchron per `Fetch API` aus `/api/spots.php` geladen und als Marker eingetragen
- **Interaktion:** Karten-Klick öffnet ein Bootstrap-Offcanvas zum Spot erstellen. Marker-Klick öffnet ein weiteres Offcanvas mit der Spot-Vorschau
- **Direktnachrichten:** Polling-basiert via `/api/messages.php`; Gelesen-Status und Benachrichtigungen werden serverseitig verwaltet
- **Authentifizierungszustand:** Wird per `<meta name="app-logged-in">` und weiteren Meta-Tags sicher an JavaScript übergeben – kein direktes JavaScript-Cookie-Parsing
- **Legende:** Filtereinstellungen werden serverseitig aus einem Cookie gelesen und per AJAX-Aufruf an `save_legend.php` persistiert
- **Flash Messages:** Statusmeldungen nach Formular-Submissions werden als Session-Variable gesetzt und nach dem Redirect einmalig angezeigt (Post-Redirect-Get-Pattern)

### Seiten-Rendering-Muster

```
public/php/{bereich}/X.php
  └─ session_start()
  └─ require core/auth_check.php      (Remember-Me Auto-Login, ToS-Wall)
  └─ Daten via PDO laden
  └─ include header.php
  └─ HTML ausgeben mit htmlspecialchars()
```

### Formular-Submission-Muster

```
HTML-Formular (public/php/{bereich}/X.php)
  └─ POST an private/php/{bereich}/X_submit.php
      └─ CSRF prüfen → Auth prüfen → Input validieren
      └─ DB-Operation
      └─ Redirect mit Statuscode in URL
```

---

## Sicherheitskonzept

### HTTP Security Headers

Alle Antworten enthalten folgende Sicherheitsheader (gesetzt via `.htaccess`):

| Header | Wert |
|--------|------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` (HSTS, 1 Jahr) |
| `Content-Security-Policy` | `default-src 'self'`; erlaubt Scripts/Styles von `cdn.jsdelivr.net`, `unpkg.com`; Bilder von OSM und Esri |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(self), payment=()` |

CDN-Ressourcen (Bootstrap, Leaflet) werden zusätzlich mit **Subresource Integrity (SRI)** eingebunden.

### Session-Härtung

Session-Cookies werden via `session_init.php` und ergänzend per `.htaccess` gehärtet:

- `HttpOnly: true` – kein JavaScript-Zugriff auf Session-Cookie
- `Secure: true` – Cookie nur über HTTPS übertragen
- `SameSite: Lax` – CSRF-Mitigation auf Cookie-Ebene
- `session.use_strict_mode = On` – lehnt uninitalisierte Session-IDs ab
- `session.use_only_cookies = On` – kein `?PHPSESSID` in URLs

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
- „Angemeldet bleiben": Selector/Validator-Token-Paar, Validator nur als SHA-256-Hash gespeichert, Token-Rotation bei jeder Verwendung (atomar via Transaktion)

### Passwort-Blacklist

Beim Registrieren, beim Passwort-Reset und beim Passwort-Ändern wird das gewählte Passwort gegen eine Liste der 1050 häufigsten Passwörter geprüft (`private/data/best1050.txt`). Die Prüfung ist case-insensitiv.

### Re-Authentifizierung bei sensitiven Kontoänderungen

Änderungen von E-Mail-Adresse, Benutzername und Passwort erfordern die Eingabe des aktuellen Passworts. Fehlgeschlagene Re-Auth-Versuche werden im Audit-Log protokolliert.

### Autorisierung

- Spot-Bearbeitung/-Löschung: nur Eigentümer oder Admin
- Kommentar-Bearbeitung: nur Eigentümer
- Kommentar-Löschung: Eigentümer oder Admin
- Verbesserungsvorschlag-Löschung/-Kommentierung: nur Admin
- Changelog-Einträge erstellen: nur Admin
- Dashboard/Profilseiten: Login erforderlich
- Direktnachrichten: nur Gesprächsteilnehmer dürfen Konversation lesen

### Datei-Upload-Sicherheit

| Prüfung | Methode |
|---------|---------|
| MIME-Type | `finfo()` (liest tatsächlichen Dateiinhalt / Magic Bytes) |
| Dateiendung | Whitelist: jpg, jpeg, png |
| Bildvalidierung | `getimagesize()` |
| Dateigröße | Max. 5 MB |
| Dateiname | Randomisiert: `bin2hex(random_bytes(16))` |
| Upload-Rate | Max. 20 Uploads pro Nutzer pro Stunde |
| Bilder pro Spot | Hard-Cap: 30 Bilder |

### Eingabevalidierung

- Enum-Validierung für Spot-Typen, Schwierigkeitsgrade und Coptergröße (Allowlist, `in_array` strict)
- Koordinaten-Prüfung: Latitude −90 bis 90, Longitude −180 bis 180
- Längenprüfung auf Strings (Name, Beschreibung, Kommentare, Vorschläge, Bio, Nachrichten)
- Username-Regex: `/^[a-zA-Z0-9_\-]+$/`, 5–50 Zeichen
- E-Mail-Validierung via `FILTER_VALIDATE_EMAIL`
- Serverseitige Validierung auf allen Endpunkten (Client-Validierung wird nicht vertraut)

### Redirect-Validierung

Post-Login-Redirects werden gegen eine Whitelist-Regex geprüft, um Open-Redirect-Angriffe zu verhindern:

```php
preg_match('#^(\.\./)*public/php/[a-zA-Z0-9_]+\.php(\?[a-zA-Z0-9_=&]+)?$#', $redirect)
```

### Rate-Limiting

Alle Rate-Limits werden über Abfragen in der `audit_logs`- bzw. Aktions-Tabelle implementiert und antworten bei Überschreitung mit HTTP `429 Too Many Requests`.

| Aktion | Limit | Zeitfenster | Basis |
|--------|-------|-------------|-------|
| Login fehlgeschlagen | 5 Versuche | 5 Minuten | IP |
| Login fehlgeschlagen | 10 Versuche | 15 Minuten | Konto |
| Registrierung | 5 Versuche | 15 Minuten | IP |
| Passwort-Reset-Anfrage | 3 Anfragen | 5 Minuten | IP |
| Kontaktformular | 5 Einreichungen | 1 Stunde | IP + E-Mail |
| Foto-Upload | 20 Uploads | 1 Stunde | Nutzer |
| Nachrichten senden | 30 Nachrichten | 1 Minute | Nutzer |
| Spot-Meldungen | 10 Meldungen | 1 Stunde | Nutzer |
| Spot-Meldungen (Duplikat) | 1 Meldung | 1 Tag | Nutzer + Spot + Typ |

### User-Enumerierungs-Schutz

Beim Passwort-Reset antwortet der Server immer mit derselben Erfolgsmeldung, unabhängig davon ob die angegebene E-Mail-Adresse existiert.

### Soft-Delete bei Direktnachrichten

Konversationen werden nicht physisch gelöscht, sondern per Nutzer als gelöscht markiert (`deleted_by_user1/user2`). Trifft eine neue Nachricht ein, wird die Konversation wieder sichtbar.

### Audit-Logging

Sicherheitsrelevante Aktionen werden mit User-ID, IP-Adresse und Zeitstempel protokolliert:

| Aktion | Auslöser |
|--------|---------|
| `REGISTER_SUCCESS` | Neue Registrierung |
| `REGISTER_FAILED` | Fehlgeschlagener Registrierungsversuch |
| `LOGIN_SUCCESS` | Erfolgreiche Anmeldung |
| `LOGIN_FAILED` | Fehlgeschlagener Anmeldeversuch (IP + Konto-Rate-Limit) |
| `PASSWORD_RESET_REQUESTED` | Passwort-Reset angefordert |
| `PASSWORD_RESET_COMPLETED` | Passwort erfolgreich zurückgesetzt |
| `EMAIL_CHANGED` | E-Mail-Adresse geändert |
| `EMAIL_CHANGE_REAUTH_FAILED` | Re-Auth beim E-Mail-Ändern fehlgeschlagen |
| `USERNAME_CHANGED` | Benutzername geändert |
| `USERNAME_CHANGE_REAUTH_FAILED` | Re-Auth beim Benutzernamen-Ändern fehlgeschlagen |
| `PASSWORD_CHANGED` | Passwort geändert |
| `PASSWORD_CHANGE_REAUTH_FAILED` | Re-Auth beim Passwort-Ändern fehlgeschlagen |
| `SPOT_CREATED` | Neuer Spot erstellt |
| `SPOT_EDITED` | Spot bearbeitet |
| `IMAGE_UPLOADED` | Bild hochgeladen |
| `IMAGE_ORPHAN_CLEANED` | Verwaiste Bilddatei durch Wartungsskript gelöscht |
| `TERMS_ACCEPTED` | Nutzungsbedingungen akzeptiert |

### API-Sicherheit

- **GET:** Öffentlich, nur lesender Zugriff
- **POST/PUT/DELETE:** Authentifizierung und CSRF-Token erforderlich
- Korrekte HTTP-Statuscodes (400, 401, 403, 404, 405, 429, 500)
- Generische Fehlermeldungen nach außen, Details nur intern geloggt (kein Stack-Trace an den Client)

### Client-IP-Erkennung

`client_ip.php` extrahiert die echte Client-IP auch hinter Reverse-Proxies und CDNs (Cloudflare-ready). `X-Forwarded-For` wird nur von konfigurierten Vertrauens-IPs ausgewertet, um IP-Spoofing in Rate-Limits und Audit-Logs zu verhindern.

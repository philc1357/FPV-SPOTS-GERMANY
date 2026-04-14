# FPV Spots Germany

Community-Plattform zum Teilen und Bewerten von FPV-Drohnen-Flugspots in Deutschland. Nutzer können Spots auf einer interaktiven Karte eintragen, bewerten, kommentieren und Fotos hochladen.

## Tech-Stack

| Komponente | Technologie |
|------------|-------------|
| Backend | PHP 7.4+ mit PDO (MySQL/MariaDB) |
| Frontend | Bootstrap 5.3, Leaflet 1.9 (OpenStreetMap) |
| Abhängigkeiten | Composer (`vlucas/phpdotenv`) |
| Karte | Leaflet + OpenStreetMap-Tiles |

## Features

- Interaktive Karte mit Spot-Markern (Leaflet + OpenStreetMap)
- Spot-Kategorien: Bando, Feld, Gebirge, Park, Verein, Wasser, Sonstige
- Schwierigkeitsgrade: Anfänger, Mittel, Fortgeschritten, Profi
- Sternebewertungen (1–5) und Kommentarsystem
- Foto-Upload (JPG/PNG, max. 5 MB)
- Benutzerverwaltung (Registrierung, Login, Profil, Passwortänderung)
- Dashboard mit eigenen Spots


## Sicherheitskonzept

### CSRF-Schutz

Jede Session erhält ein kryptografisch sicheres Token (`bin2hex(random_bytes(32))`), das in allen Formularen als Hidden-Field eingebettet und serverseitig mit `hash_equals()` geprüft wird. Betroffen sind sämtliche schreibenden Endpunkte: Login, Registrierung, Spot-Erstellung, Kommentare, Bewertungen, Uploads und Profiländerungen.

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

### Autorisierung

- Spot-Bearbeitung/-Löschung: nur Eigentümer oder Admin
- Kommentar-Bearbeitung: nur Eigentümer
- Kommentar-Löschung: Eigentümer oder Admin
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
- Längenprüfung auf Strings (Name, Beschreibung, Kommentare)
- Serverseitige Validierung auf allen Endpunkten (Client-Validierung wird nicht vertraut)

### Redirect-Validierung

Post-Login-Redirects werden gegen eine Whitelist-Regex geprüft, um Open-Redirect-Angriffe zu verhindern:

```php
preg_match('#^(\.\./)*public/php/[a-zA-Z0-9_]+\.php(\?[a-zA-Z0-9_=&]+)?$#', $redirect)
```

### Audit-Logging

Sicherheitsrelevante Aktionen werden mit User-ID und IP-Adresse protokolliert:

- `LOGIN_SUCCESS` / `LOGIN_FAILED`
- `REGISTER_SUCCESS`
- `SPOT_CREATED`
- `PASSWORD_CHANGED`
- `IMAGE_UPLOADED`
- Kommentar-Operationen

### API-Sicherheit

- **GET:** Öffentlich, nur lesender Zugriff
- **POST/PUT/DELETE:** Authentifizierung und CSRF-Token erforderlich
- Korrekte HTTP-Statuscodes (400, 401, 403, 404, 405, 500)
- Generische Fehlermeldungen nach außen, Details nur intern geloggt

## Empfohlene Härtung für Produktion

Folgende Maßnahmen sind empfohlen, aber noch nicht implementiert:

| Maßnahme | Beschreibung |
|----------|--------------|
| Content Security Policy | CSP-Header setzen, um Inline-Skripte und externe Ressourcen einzuschränken |
| HSTS | `Strict-Transport-Security`-Header für HTTPS-Erzwingung |
| Clickjacking-Schutz | `X-Frame-Options: DENY` |
| MIME-Sniffing | `X-Content-Type-Options: nosniff` |
| Referrer-Policy | `Referrer-Policy: strict-origin-when-cross-origin` |
| Rate Limiting | Brute-Force-Schutz für Login und API-Endpunkte |
| Account Lockout | Kontosperrung nach N fehlgeschlagenen Login-Versuchen |
| 2FA/MFA | Zwei-Faktor-Authentifizierung |

## Lizenz

Dieses Projekt ist nicht öffentlich lizenziert. Alle Rechte vorbehalten.

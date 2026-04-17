# Wichtige Regeln

- Lege besonderen Fokus auf Sicherheit.
- Ignoriere, wenn nicht anders gewollt, immer den Ordner: `x.fpv-spots-germany.de`
- Überprüfe deine Änderungen immer noch einmal bevor du sie final übernimmst.
- Nutze immer Bootstrap 5 für Frontend-Komponenten.
- Achte auf sinnvolle HTML-Tags für SEO.

## Projektübersicht

FPV Spots Germany ist eine Community-Plattform zur Erfassung und Teilen von FPV-Drohnen-Spots in Deutschland. Die App ist eine PHP/MySQL-Anwendung ohne Framework, mit Leaflet-Karte als zentralem UI-Element, und als PWA (Progressive Web App) installierbar.

**Tech-Stack:** PHP (kein Framework), MySQL/MariaDB via PDO, Bootstrap 5.3.8, Leaflet 1.9 (OpenStreetMap + Esri), PHPMailer 6, Composer (vlucas/phpdotenv), Apache mod_rewrite, Service Worker


# URL-Routing (.htaccess)

Apache mod_rewrite mappt saubere URLs intern auf `public/php/`:
- `/dashboard.php` → intern `public/php/dashboard.php`
- `/api/spots.php` → intern `public/php/api/spots.php`

# Architektur-Muster

**Seiten-Rendering:**
```
public/php/X.php
  └─ session_start()
  └─ require auth_check.php      (Remember-Me Auto-Login)
  └─ Daten via PDO laden
  └─ include header.php
  └─ HTML ausgeben mit htmlspecialchars()
```

**Formular-Submission:**
```
HTML-Formular (public/php/X.php)
  └─ POST an /private/php/X_submit.php
      └─ CSRF prüfen → Auth prüfen → Input validieren
      └─ DB-Operation
      └─ Redirect mit Statuscode in URL
```

**API-Endpunkt:**
```
public/php/api/X.php
  └─ session_start() + db.php
  └─ Content-Type: application/json
  └─ REQUEST_METHOD prüfen
  └─ Auth + CSRF prüfen (bei POST/PUT/DELETE)
  └─ json_encode() + exit
```

**Method-Override:** HTML-Forms nutzen `_method=PUT/DELETE` POST-Parameter (REST-Pattern).

# Datenbank (16 Tabellen)

Wichtigste Tabellen: `users`, `spots`, `comments`, `ratings`, `spot_images`, `conversations`, `messages`, `user_notifications`, `remember_tokens`, `password_reset_tokens`, `suggestions`, `suggestion_votes`, `audit_logs`

Alle Verbindungen: `charset=utf8mb4`, `JSON_UNESCAPED_UNICODE` für Ausgabe.

# Authentifizierung & Sessions

- **auth_check.php** wird auf jeder Seite early included → prüft Session, dann Remember-Me-Token
- Remember-Me: `selector:validator`-Muster, validator wird gehasht in DB gespeichert, Token rotiert bei jeder Nutzung
- Nach Login: `session_regenerate_id(true)` (Session-Fixation-Schutz)
- Session-Variablen: `user_id`, `username`, `is_admin`, `csrf_token`
- Rate-Limiting: 5 Fehlversuche in 5 Min → HTTP 429 (IP-basiert, Login)

# Sicherheits-Konventionen (immer einhalten)

- **SQL:** Ausschließlich Prepared Statements (`$pdo->prepare()` + `->execute([])`), niemals String-Interpolation mit User-Input
- **XSS:** Alle Ausgaben mit `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` escapen
- **CSRF:** Jedes state-änderndes Formular braucht `$_SESSION['csrf_token']`-Prüfung via `hash_equals()`
- **Uploads:** MIME-Prüfung via `finfo_file()` (Magic Bytes), `getimagesize()`, Whitelist `[jpg, jpeg, png]`, max 5 MB, Dateinamen randomisieren mit `bin2hex(random_bytes(16))`
- **Cookies:** Immer `httponly=true, secure=true, samesite=Lax`
- **Redirects:** Nur gegen Whitelist-Regex validieren (kein Open Redirect)
- **Fehler:** Generische Meldung an User, Details ins Server-Error-Log, nie Stack-Traces ausgeben
- **Audit-Log:** Sicherheitsrelevante Aktionen in `audit_logs`-Tabelle schreiben (action, user_id, ip_address)
- **Passwörter:** `password_hash($pass, PASSWORD_DEFAULT)` (Argon2id), max 50 Zeichen Input (BCrypt-Truncation-Schutz)

# API-Endpunkte

| Endpunkt | Methode | Auth | Zweck |
|----------|---------|------|-------|
| `api/spots.php` | GET | nein | Alle Spots (JSON) |
| `api/spots.php` | POST | ja+CSRF | Spot erstellen |
| `api/spot.php?id=X` | GET | nein | Einzelner Spot |
| `api/spot.php?id=X` | POST `_method=PUT` | ja+CSRF | Spot bearbeiten |
| `api/spot.php?id=X` | POST `_method=DELETE` | ja+CSRF | Spot löschen |
| `api/messages.php` | GET/POST | ja | Nachrichten |
| `api/update_bio.php` | POST | ja+CSRF | Bio aktualisieren |
| `api/save_legend.php` | POST | nein | Kartenfilter (Cookie) |

HTTP-Status: 200, 400, 401, 403, 404, 429, 500

# Abhängigkeiten (Composer)

```bash
composer install   # Installiert vlucas/phpdotenv
```

`.env`-Datei im Projekt-Root (neben `composer.json`) mit DB_HOST, DB_NAME, DB_USER, DB_PASS, SMTP-Werten – wird von `db.php` und `mailer.php` geladen.

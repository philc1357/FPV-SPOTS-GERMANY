# Wichtige Regeln

- Lege besonderen Fokus auf Sicherheit.
- Ignoriere, wenn nicht anders gewollt, immer den Ordner: `x.fpv-spots-germany.de`
- Nutze immer Bootstrap 5 für Frontend-Komponenten.
- Achte auf sinnvolle HTML-Tags für SEO.

# Projektübersicht

FPV Spots Germany ist eine Community-Plattform zur Erfassung und Teilen von FPV-Drohnen-Spots in Deutschland. Die App ist eine PHP/MySQL-Anwendung ohne Framework, mit Leaflet-Karte als zentralem UI-Element, und als PWA (Progressive Web App) installierbar.

**Tech-Stack:** PHP (kein Framework), MySQL/MariaDB via PDO, Bootstrap 5.3.8, Leaflet 1.9 (OpenStreetMap + Esri), PHPMailer 6, Composer (vlucas/phpdotenv), Apache mod_rewrite, Service Worker

# Setup

`.env`-Datei im Projekt-Root (neben `composer.json`) mit folgenden Werten anlegen:

```
DB_HOST=...
DB_NAME=...
DB_USER=...
DB_PASS=...
SMTP_HOST=...
SMTP_USER=...
SMTP_PASS=...
```

Diese Werte werden von `private/php/core/db.php` und `private/php/core/mailer.php` geladen.

# URL-Routing (.htaccess)

Apache mod_rewrite mappt saubere URLs intern auf `public/php/` – Unterordner nach Bereich:

| Öffentliche URL | Interner Pfad |
|---|---|
| `/login.php` | `public/php/auth/login.php` |
| `/register.php` | `public/php/auth/register.php` |
| `/dashboard.php` | `public/php/account/dashboard.php` |
| `/spot_detail.php` | `public/php/spots/spot_detail.php` |
| `/kritik.php` | `public/php/community/kritik.php` |
| `/kontakt.php` | `public/php/contact/kontakt.php` |
| `/api/spots.php` | `public/php/api/spots.php` |

Unterordner-Routing: `/auth/`, `/account/`, `/spots/`, `/community/`, `/contact/`, `/legal/` werden analog gemappt. Direkte Aufrufe der internen Pfade werden per 301 auf die saubere URL umgeleitet.

# Architektur-Muster

**Seiten-Rendering:**
```
public/php/{bereich}/X.php
  └─ session_start()
  └─ require auth_check.php      (Remember-Me Auto-Login)
  └─ Daten via PDO laden
  └─ include header.php
  └─ HTML ausgeben mit htmlspecialchars()
```

**Formular-Submission:**
```
HTML-Formular (public/php/{bereich}/X.php)
  └─ POST an /private/php/{bereich}/X_submit.php
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


Vollständiges Datenbank-Schema: `database.sql` im Projekt-Root.

Alle Verbindungen: `charset=utf8mb4`, `JSON_UNESCAPED_UNICODE` für Ausgabe.


# Sicherheits-Konventionen (immer einhalten)

- **SQL:** Ausschließlich Prepared Statements (`$pdo->prepare()` + `->execute([])`)
- **XSS:** Alle Ausgaben mit `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` escapen
- **CSRF:** Jedes state-änderndes Formular braucht `$_SESSION['csrf_token']`-Prüfung via `hash_equals()`
- **Uploads:** MIME-Prüfung via `finfo_file()` (Magic Bytes), `getimagesize()`, Whitelist `[jpg, jpeg, png]`, max 5 MB, Dateinamen randomisieren mit `bin2hex(random_bytes(16))`
- **Cookies:** Immer `httponly=true, secure=true, samesite=Lax`
- **Redirects:** Nur gegen Whitelist-Regex validieren (kein Open Redirect)
- **Fehler:** Generische Meldung an User, Details ins Server-Error-Log, nie Stack-Traces ausgeben
- **Audit-Log:** Sicherheitsrelevante Aktionen in `audit_logs`-Tabelle schreiben (action, user_id, ip_address)
- **Passwörter:** `password_hash($pass, PASSWORD_DEFAULT)` (Argon2id), max 50 Zeichen Input (BCrypt-Truncation-Schutz)


PHPMailer 6 ist direkt im Projekt enthalten (nicht über Composer).
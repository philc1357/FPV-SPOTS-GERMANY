# Security Audit Report – FPV Spots Germany

_Datum: 2026-04-19 · Scope: statischer Code-Review, OWASP Top 10 + Auth/Session · Ignoriert: `x.fpv-spots-germany.de`_

## Zusammenfassung

| Schweregrad   | Anzahl |
|---------------|--------|
| 🔴 KRITISCH    | 1      |
| 🟠 HOCH        | 5      |
| 🟡 MITTEL      | 6      |
| 🟢 NIEDRIG     | 4      |
| ℹ️ INFO        | 2      |

**Geprüfte Kategorien:** OWASP A01–A10, Authentifizierung & Session-Management, Upload-Sicherheit, XSS, CSRF, Open Redirect
**Geprüfte Dateien:** 62 PHP-Dateien (alle Submit-Handler, Core, API, Views-Stichprobe)

---

## Befunde

### 🔴 KRITISCH-1 – Passwortänderung ohne aktuelles Passwort (fehlende Re-Authentifizierung)

**Kategorie:** Authentifizierungsschwäche (OWASP A07)
**Datei:** `fpv-spots-germany.de/private/php/account/change_password_submit.php`, Zeilen 20–77

**Code:**
```php
$userId    = $_SESSION['user_id'];
$newPass1  = $_POST['password_field1'] ?? '';
$newPass2  = $_POST['password']        ?? '';

// ... nur Länge + Match-Check, KEIN current-password-Check ...
$newHash = password_hash($newPass1, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->execute([$newHash, $userId]);
```

**Problem:** Das aktuelle Passwort wird nicht abgefragt. Wer auch nur kurzzeitigen Zugriff auf eine aktive Session hat (unbeaufsichtigter Browser, XSS, CSRF-Token-Leak, fremdes Endgerät), kann das Konto mit einem einzigen POST **dauerhaft übernehmen** und den legitimen Nutzer aussperren. Die E-Mail-Adresse lässt sich ebenfalls ohne Re-Auth ändern (Finding HOCH-4), sodass selbst Passwort-Reset per Mail nicht mehr greift.

**Empfehlung:** Aktuelles Passwort als Pflichtfeld einfordern und verifizieren:
```php
$currentPass = $_POST['current_password'] ?? '';
if (empty($currentPass)) {
    $error = 'Bitte dein aktuelles Passwort eingeben.';
}
if (!isset($error)) {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    if (!$userData || !password_verify($currentPass, $userData['password_hash'])) {
        // gleiche Meldung wie bei "Passwörter stimmen nicht überein" um Enumeration zu vermeiden
        $error = 'Aktuelles Passwort ist falsch.';
        // zusätzlich loggen
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'PASSWORD_CHANGE_FAILED', ?)")
            ->execute([$userId, $_SERVER['REMOTE_ADDR']]);
    }
}
```
Im HTML-Formular (`public/php/account/change_password.php`) ein zusätzliches Feld `name="current_password"` ergänzen.

---

### 🟠 HOCH-1 – Open Redirect über `FILTER_SANITIZE_URL`

**Kategorie:** Open Redirect (OWASP A01 / Insecure Design)
**Datei:** `fpv-spots-germany.de/private/php/spots/favorite_submit.php`, Zeilen 9 + 46

**Code:**
```php
$redirect = filter_var($_POST['redirect'] ?? '', FILTER_SANITIZE_URL) ?: '/';
// ...
header('Location: ' . $redirect);
```

**Problem:** `FILTER_SANITIZE_URL` entfernt nur ungültige URL-Zeichen – es validiert **nichts**. Werte wie `https://evil.com/phish` oder `//attacker.example/` werden unverändert durchgereicht. Ein Angreifer kann so Phishing-Links bauen: `…/favorite_submit.php` mit `redirect=https://evil-fpv.de`. Der Login- und Logout-Handler (`login_submit.php`, `logout_submit.php`) validieren das `redirect`-Feld bereits mit einer Regex – hier wurde der Schutz vergessen.

**Empfehlung:** Gleichen Regex-Gate wie in login_submit.php verwenden:
```php
$redirect = $_POST['redirect'] ?? '';
if ($redirect !== '' && !preg_match('#^/[a-zA-Z0-9_/]+\.php(\?[a-zA-Z0-9_=&]+)?$#', $redirect)) {
    $redirect = '/';
}
if ($redirect === '') { $redirect = '/'; }
```

---

### 🟠 HOCH-2 – Registrierung akzeptiert Passwörter beliebiger Länge

**Kategorie:** Auth Failures / Insecure Design (OWASP A07/A04)
**Datei:** `fpv-spots-germany.de/private/php/auth/register_submit.php`, Zeilen 16–32

**Code:**
```php
$pass1 = $_POST['password_field1'] ?? '';
$pass2 = $_POST['password'] ?? '';
// ...
if (empty($username) || empty($email) || empty($pass1) || empty($pass2)) { ... }
elseif ($pass1 !== $pass2) { ... }
elseif (($_POST['terms'] ?? '') !== '1') { ... }
else {
    $passwordHash = password_hash($pass1, PASSWORD_DEFAULT);
```

**Problem:** Keine serverseitige Längenprüfung. Das HTML-Attribut `minlength="8"` ist clientseitig und trivial umgehbar (curl, Devtools). Es lassen sich Konten mit 1-Zeichen-Passwörtern anlegen. Gleichzeitig gibt es keine Obergrenze – bei `PASSWORD_DEFAULT` intern bcrypt (72 Byte Limit) drohen BCrypt-Truncation-Unklarheiten. In `change_password_submit.php` und `reset_password_submit.php` ist das bereits korrekt (≥8, ≤50).

**Empfehlung:** Gleiche Policy wie in change_password_submit.php:
```php
} elseif (strlen($pass1) < 8) {
    $message = "Das Passwort muss mindestens 8 Zeichen lang sein.";
} elseif (strlen($pass1) > 50) {
    $message = "Das Passwort darf maximal 50 Zeichen lang sein.";
} elseif (!hash_equals($pass1, $pass2)) {
    $message = "Die Passwörter stimmen nicht überein.";
```
`!==` durch `hash_equals()` ersetzen (timing-safe).

---

### 🟠 HOCH-3 – Registrierung ohne E-Mail-Format-Validierung & ohne Rate-Limit

**Kategorie:** Insecure Design (OWASP A04)
**Datei:** `fpv-spots-germany.de/private/php/auth/register_submit.php`, Zeilen 14–45

**Code:**
```php
$email = trim($_POST['email'] ?? '');
// ... keine filter_var(..., FILTER_VALIDATE_EMAIL)
$sql = "INSERT INTO users (username, email, password_hash, terms_accepted_at) VALUES (?, ?, ?, NOW())";
```

**Problem:** (1) Jede beliebige Zeichenkette wird als E-Mail akzeptiert – spätere Passwort-Reset-Mails gehen ins Leere, User können sich sperren. (2) Kein Rate-Limit auf Registrierung – ein Botnetz kann die Tabelle `users` fluten (Storage-DoS, Audit-Log-Verunreinigung).

**Empfehlung:**
```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $message = "Bitte eine gültige E-Mail-Adresse eingeben.";
} else {
    // Rate-Limit: max 3 Registrierungen pro IP in 1h
    $rl = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'REGISTER_SUCCESS' AND ip_address = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $rl->execute([$_SERVER['REMOTE_ADDR']]);
    if ((int)$rl->fetchColumn() >= 3) {
        http_response_code(429);
        $message = 'Zu viele Registrierungen von dieser IP. Bitte später erneut versuchen.';
    }
}
```
Zusätzlich Username-Format validieren (`/^[a-zA-Z0-9_-]{5,50}$/`), wie es `change_username_submit.php` bereits macht.

---

### 🟠 HOCH-4 – E-Mail- und Benutzername-Änderung ohne Re-Authentifizierung

**Kategorie:** Auth Failures (OWASP A07)
**Dateien:**
- `fpv-spots-germany.de/private/php/account/change_email_submit.php`, Zeilen 19–57
- `fpv-spots-germany.de/private/php/account/change_username_submit.php`, Zeilen 20–53

**Code (change_email_submit.php):**
```php
$userId   = $_SESSION['user_id'];
$newEmail = trim($_POST['new_email'] ?? '');
// ... CSRF + Format-Check ...
$stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
$stmt->execute([$newEmail, $userId]);
```

**Problem:** In Kombination mit KRITISCH-1 ergibt das einen vollständigen Account-Takeover: Angreifer mit Session-Zugriff ändert E-Mail auf sich selbst → fordert Passwort-Reset an → landet selbst. Auch der Username (möglicherweise für öffentliche Identität kritisch) lässt sich ohne Bestätigung übernehmen. Zusätzlich fehlt bei E-Mail-Änderung eine Double-Opt-In-Bestätigung an die **neue** Adresse, sodass Tippfehler den Account vom Reset-Flow abschneiden können.

**Empfehlung:**
1. In beiden Handlern Re-Auth über aktuelles Passwort einziehen (gleiches Pattern wie bei KRITISCH-1).
2. Für E-Mail-Wechsel Double-Opt-In einführen: neue Adresse zusammen mit Selector/Validator-Token (wie Password-Reset) in `email_change_tokens` speichern; Änderung erst nach Klick auf Bestätigungslink durchführen.

---

### 🟠 HOCH-5 – Session-Cookie ohne explizite `Secure`/`HttpOnly`/`SameSite`-Flags

**Kategorie:** Security Misconfiguration / Cryptographic Failures (OWASP A02/A05)
**Dateien:** alle Einstiegspunkte, die `session_start()` ohne vorheriges `session_set_cookie_params()` aufrufen (u. a. `index.php:5`, alle `public/php/**/*.php`, alle Submit-Handler)

**Code (index.php Zeile 5):**
```php
session_start();
// ... kein session_set_cookie_params() irgendwo im Projekt
```

**Problem:** Das `PHPSESSID`-Cookie hängt vollständig von den `php.ini`-Defaults ab. Viele Distributionen liefern `session.cookie_secure=0`, `session.cookie_samesite=""` aus. Der Remember-Me-Cookie ist korrekt gesetzt, aber die eigentliche Session-ID ist potenziell per HTTP auslesbar (wenn kein HSTS greift) und ohne `SameSite` CSRF-exponierter (der CSRF-Token schützt POSTs, nicht aber GET-Side-Effects wie in `messages.php`).

**Empfehlung:** In einer zentralen Bootstrap-Datei (neu: `private/php/core/session_init.php`) vor jedem `session_start()`:
```php
<?php
// private/php/core/session_init.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('FPVSESSID');
    session_start();
}
```
Alle `session_start();`-Aufrufe durch `require_once __DIR__ . '/.../private/php/core/session_init.php';` ersetzen.

---

### 🟡 MITTEL-1 – Kontaktformular ohne Rate-Limit (Spam/Mail-Flood)

**Kategorie:** Insecure Design (OWASP A04)
**Datei:** `fpv-spots-germany.de/private/php/contact/kontakt_submit.php`, Zeilen 14–58

**Problem:** CSRF ist implementiert, aber ein Angreifer kann legitim eine Token-Session aufbauen und dann in Schleife POSTs schicken. Pro Submit geht eine E-Mail raus → DoS-Risiko auf dem SMTP-Quotas-Budget und auf `contact_requests`.

**Empfehlung:**
```php
$ip = $_SERVER['REMOTE_ADDR'];
$rl = $pdo->prepare(
    "SELECT COUNT(*) FROM contact_requests
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
       AND (user_id = ? OR email = ?)"
);
$rl->execute([$userId, $email]);
if ((int)$rl->fetchColumn() >= 5) {
    http_response_code(429);
    header('Location: /public/php/kontakt_error.php?rl=1');
    exit;
}
```
Oder zusätzlich hCaptcha/Turnstile einbinden.

---

### 🟡 MITTEL-2 – GET-Endpunkte in `messages.php` verändern Zustand ohne CSRF-Schutz

**Kategorie:** CSRF / Data Integrity (OWASP A08)
**Datei:** `fpv-spots-germany.de/public/php/api/messages.php`, Zeilen 131–194, 327–392

**Code:**
```php
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'messages':       getMessages($pdo, $userId);      break; // markiert read_at
        case 'poll':           pollMessages($pdo, $userId);     break; // markiert read_at
```
In `getMessages()`/`pollMessages()`:
```php
$markStmt = $pdo->prepare("
    UPDATE messages SET read_at = NOW()
    WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL
");
$markStmt->execute([$conversationId, $userId]);
```

**Problem:** GET wird als state-ändernd verwendet (Read-Marker). Ein Angreifer kann über ein `<img src="/api/messages.php?action=messages&conversation_id=42">` im eigenen Web-Inhalt Nachrichten des Opfers als gelesen markieren lassen (CORS-Read schlägt zwar fehl, aber der Seiteneffekt tritt ein). Kein Datenabfluss, nur Integrität der Lesemarken.

**Empfehlung:** `UPDATE messages SET read_at = …` in eine **separate POST-Aktion** (`action=mark_read`) mit CSRF-Check verschieben. Der GET-Handler liefert nur die Daten.

---

### 🟡 MITTEL-3 – `spot.php` DELETE entfernt Bilddateien nicht vom Dateisystem

**Kategorie:** Data Integrity / Insecure Design
**Datei:** `fpv-spots-germany.de/public/php/api/spot.php`, Zeilen 177–180

**Code:**
```php
$stmt = $pdo->prepare("DELETE FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
echo json_encode(['success' => true]);
```

**Problem:** Im Gegensatz zum Form-Handler `delete_spot_submit.php` (Zeilen 42–53) werden hier nur DB-Zeilen via `CASCADE` gelöscht. Die physischen Dateien in `public/uploads/imgs/` bleiben verwaist (Storage-Leck, DSGVO-Löschpflicht-Thema, ggf. öffentliche URL lebt weiter wenn jemand sie abgespeichert hatte).

**Empfehlung:** Gleiche Cleanup-Logik wie im Form-Handler vor dem DELETE einziehen:
```php
$imgStmt = $pdo->prepare("SELECT filename FROM spot_images WHERE spot_id = ?");
$imgStmt->execute([$spotId]);
$uploadDir = __DIR__ . '/../../uploads/imgs/';
foreach ($imgStmt->fetchAll() as $img) {
    $path = $uploadDir . $img['filename'];
    if (is_file($path)) @unlink($path);
}
$pdo->prepare("DELETE FROM spots WHERE id = ?")->execute([$spotId]);
```

---

### 🟡 MITTEL-4 – CSP erlaubt `'unsafe-inline'` für Scripts und Styles

**Kategorie:** Security Misconfiguration (OWASP A05)
**Datei:** `fpv-spots-germany.de/.htaccess`, Zeile 3

**Code:**
```
Header set Content-Security-Policy "default-src 'self'; script-src 'self' cdn.jsdelivr.net unpkg.com 'unsafe-inline'; style-src 'self' cdn.jsdelivr.net unpkg.com 'unsafe-inline'; …"
```

**Problem:** `'unsafe-inline'` entwertet den wichtigsten XSS-Schutz der CSP erheblich. In `index.php`, `spot_detail.php` u.a. gibt es inline-JS (`<script>…</script>`) und inline-Styles (`style="background:..."` aus dem Typ-/Schwierigkeits-Mapping). Im Projekt wird überall sauber `htmlspecialchars(..., ENT_QUOTES)` verwendet – sollte aber eine einzelne Stelle durchrutschen, fängt die CSP es aktuell nicht ab.

**Empfehlung:** Mittelfristig auf Nonce-basierte CSP umsteigen:
```apache
# .htaccess – dynamisch schwierig via mod_headers; besser in index.php:
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net unpkg.com; style-src 'self' 'nonce-{$nonce}' cdn.jsdelivr.net unpkg.com; img-src 'self' data: *.tile.openstreetmap.org server.arcgisonline.com; font-src 'self' cdn.jsdelivr.net; connect-src 'self'");
```
Jedes `<script>` / `<style>` mit `nonce="<?= $nonce ?>"` versehen, inline-`style="..."` auf CSS-Klassen migrieren.

---

### 🟡 MITTEL-5 – Brute-Force-Rate-Limit ausschließlich IP-basiert

**Kategorie:** Auth Failures (OWASP A07)
**Datei:** `fpv-spots-germany.de/private/php/auth/login_submit.php`, Zeilen 22–34

**Code:**
```php
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action = 'LOGIN_FAILED' AND ip_address = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
);
$stmt->execute([$ip]);
if ((int)$stmt->fetchColumn() >= 5) { http_response_code(429); die('Zu viele Fehlversuche. Bitte warte 5 Minuten.'); }
```

**Problem:** (a) Ein Botnetz mit 1000 IPs kann Passwort-Spraying ungebremst betreiben (5 Versuche pro IP × 1000 = 5000 Versuche in 5 Minuten). (b) Ein Angreifer hinter Carrier-Grade-NAT kann legitime Nutzer derselben IP aussperren (Self-DoS). (c) Der IP-Log speichert auch fehlgeschlagene Versuche **ohne** User-Bezug → keine Kontosperre möglich.

**Empfehlung:** Zweite Zählebene pro Zielkonto:
```php
// nach $user = $stmt->fetch();
if ($user) {
    $acctStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'LOGIN_FAILED' AND user_id = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $acctStmt->execute([$user['id']]);
    if ((int)$acctStmt->fetchColumn() >= 10) {
        http_response_code(429);
        die('Konto wegen zu vieler Fehlversuche temporär gesperrt.');
    }
}
// und beim LOGIN_FAILED auch user_id mitloggen, wenn der User existiert
$pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'LOGIN_FAILED', ?)")
    ->execute([$user ? $user['id'] : null, $_SERVER['REMOTE_ADDR']]);
```

---

### 🟡 MITTEL-6 – Upload erlaubt Fotos an jeden Spot ohne Rate-Limit

**Kategorie:** Insecure Design (OWASP A04)
**Datei:** `fpv-spots-germany.de/private/php/spots/upload_submit.php`, Zeilen 26–103

**Problem:** Jeder authentifizierte Nutzer kann beliebig viele Fotos an beliebige Spots hochladen. MIME- und Größencheck sind korrekt (5 MB, finfo, getimagesize, randomisierter Name, Upload-Dir `.htaccess` blockiert PHP – sehr gut). Aber: Storage-DoS möglich (100 × 5 MB = 500 MB in Minuten), Spot-Vandalismus (unpassende Bilder fluten einen fremden Spot).

**Empfehlung:**
```php
$rl = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action = 'IMAGE_UPLOADED' AND user_id = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rl->execute([$userId]);
if ((int)$rl->fetchColumn() >= 20) {
    $_SESSION['upload_error'] = 'Upload-Limit erreicht (20/Stunde).';
    header("Location: /spot_detail.php?id=$spotId");
    exit;
}
```
Zusätzlich eine DB-Obergrenze pro Spot (z. B. max. 30 Bilder).

---

### 🟢 NIEDRIG-1 – `hash_equals` ohne Null-Guard auf Session-Seite

**Kategorie:** Robustness
**Dateien:** u. a.
- `private/php/auth/login_submit.php:13`
- `private/php/auth/register_submit.php:20`
- `private/php/account/change_password_submit.php:25`
- `private/php/account/change_email_submit.php:24`
- `private/php/account/change_username_submit.php:24`

**Code:**
```php
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) { die('CSRF-Fehler'); }
```

**Problem:** Wenn `$_SESSION['csrf_token']` nicht existiert (z. B. erste Anfrage ohne vorigen GET auf eine Seite, die das Token setzt), wirft PHP 8 `TypeError: hash_equals(): Argument #1 ($known_string) must be of type string, null given`. Fehlfunktion statt sauberer Ablehnung; der Benutzer sieht eine hässliche 500er-Seite statt „CSRF-Fehler". Die `logout_submit.php` und `terms_accept_submit.php` machen es bereits richtig (`$_SESSION['csrf_token'] ?? ''`).

**Empfehlung:** überall einheitlich:
```php
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('CSRF-Fehler');
}
```

---

### 🟢 NIEDRIG-2 – Spot melden ohne Rate-Limit

**Kategorie:** Insecure Design
**Datei:** `fpv-spots-germany.de/private/php/contact/report_submit.php`, Zeilen 23–52

**Problem:** Ein Nutzer kann einen fremden Spot unbegrenzt oft melden → Admin-Postfach/Tabellen-Flood. Kein UNIQUE-Index (spot_id, user_id) auf `spot_reports`.

**Empfehlung:** Rate-Limit pro User (max. 10 Meldungen/h) plus DB-Index `UNIQUE(spot_id, user_id, report_type)` oder Cooldown-Prüfung (eine Meldung pro Spot pro Nutzer pro Tag).

---

### 🟢 NIEDRIG-3 – `register_submit.php` zeigt kombinierte Enumeration-Meldung

**Kategorie:** Information Disclosure
**Datei:** `fpv-spots-germany.de/private/php/auth/register_submit.php`, Zeile 48

**Code:**
```php
if ($e->getCode() == 23000) {
    $message = "Benutzername oder E-Mail bereits vergeben.";
}
```

**Problem:** Die Meldung ist generisch (gut), aber die Fehlermeldung für ungültiges Passwort/ungültige Daten lautet identisch nicht – ein Angreifer kann über Timing und Status unterscheiden. Da aktuell keine Passwort-Policy läuft (HOCH-2), ist das Fenster klein. Nach Umsetzung von HOCH-2 sollte auch hier konsequent eine einheitliche Meldung kommen.

**Empfehlung:** Kein Code-Fix nötig, sobald HOCH-2/HOCH-3 umgesetzt sind. Ggf. Reihenfolge der Prüfungen so wählen, dass Format-Fehler vor DB-Unique-Checks laufen.

---

### 🟢 NIEDRIG-4 – `$_SERVER['REMOTE_ADDR']` ohne Reverse-Proxy-Berücksichtigung

**Kategorie:** Logging-Integrität
**Dateien:** alle Audit-Log-Einträge und Rate-Limit-Checks (u. a. `login_submit.php:23`, `forgot_password_submit.php:19`, `upload_submit.php`)

**Problem:** Wenn die Seite hinter einem Reverse-Proxy/CDN läuft, sieht `$_SERVER['REMOTE_ADDR']` nur die Proxy-IP. Dann gilt der Rate-Limit global für alle Besucher und alle Audit-Logs zeigen dieselbe IP. Keine Sicherheitslücke per se, aber verhindert, dass IP-Limits wirksam sind.

**Empfehlung:** Nur wenn ein vertrauter Proxy davor sitzt:
```php
// private/php/core/client_ip.php
function client_ip(): string {
    $trustedProxies = ['127.0.0.1', '::1']; // anpassen
    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $trustedProxies, true)
        && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach ($parts as $p) {
            if (filter_var($p, FILTER_VALIDATE_IP)) return $p;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
```
**Wichtig:** niemals `HTTP_X_FORWARDED_FOR` ungefiltert vertrauen – sonst kann der Client die IP für Logs und Rate-Limits selbst setzen (Log-Spoofing / Rate-Limit-Umgehung).

---

### ℹ️ INFO-1 – Keine Passwort-Strength-Prüfung über Länge hinaus

**Kategorie:** Auth Failures
**Dateien:** register_submit.php, change_password_submit.php, reset_password_submit.php

**Problem:** Passwörter wie `12345678` oder `password` werden akzeptiert. Kein Abgleich gegen gängige Breach-Listen (HaveIBeenPwned k-anonymity).

**Empfehlung (optional):** Offline-Top-1000-Liste in `private/data/common_passwords.txt` ablegen und prüfen, oder zur Build-Zeit HIBP-k-anonymity-API anbinden (k-anonymity leakt nur 5 SHA1-Hex-Zeichen, keine Volldaten).

---

### ℹ️ INFO-2 – `db.php` gibt bei Connect-Fehler nur `die("Datenbankfehler")` aus, ohne zu loggen

**Kategorie:** Logging & Monitoring
**Datei:** `fpv-spots-germany.de/private/php/core/db.php`, Zeilen 14–16

**Code:**
```php
} catch (PDOException $e) {
    die("Datenbankfehler");  // Nie die echte Fehlermeldung ausgeben!
}
```

**Problem:** Der eigentliche Fehler geht verloren – Diagnose später schwierig. Restlicher Projektcode macht `error_log($e->getMessage())` konsequent richtig.

**Empfehlung:**
```php
} catch (PDOException $e) {
    error_log('db.php connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Datenbankfehler');
}
```

---

## Positiv-Feststellungen

- **Prepared Statements durchgängig**: Alle SQL-Zugriffe nutzen `$pdo->prepare()` + `execute([…])`. Keine String-Konkatenation gefunden.
- **CSRF-Schutz konsistent**: `hash_equals()` wird an allen POST-Handlern eingesetzt (nicht `===`).
- **Output-Escaping konsistent**: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` in allen Views; `nl2br(htmlspecialchars(...))` für mehrzeilige Nutzertexte (Kommentare, Beschreibungen, Bio).
- **Upload-Hardening exzellent**: MIME via `finfo_file()`, Extension-Whitelist, `getimagesize()`, randomisierter Name (`bin2hex(random_bytes(16))`), `5 MB`-Limit, `public/uploads/imgs/.htaccess` deaktiviert PHP-Ausführung + `-Indexes`.
- **Remember-Me korrekt**: Selector/Validator-Pattern, Validator nur als SHA-256 in DB, Token-Rotation bei jeder Verwendung, atomar in Transaktion, Cookie mit `HttpOnly`+`Secure`+`SameSite=Lax`.
- **Session-Fixation-Schutz**: `session_regenerate_id(true)` direkt nach Login und nach Passwortwechsel.
- **Password-Reset solide**: Selector/Validator, 1 h Gültigkeit, einmalig verwendbar, anschließend alle Remember-Tokens invalidiert. Einheitliche Meldung → keine User-Enumeration.
- **Ownership-Checks vorhanden**: `delete_spot_submit.php`, `edit_spot_submit.php`, `spot.php` PUT/DELETE, `comment_delete_submit.php`, `comment_edit_submit.php` prüfen User-ID bzw. Admin-Flag korrekt.
- **Admin-Endpunkte**: `admin/update_submit.php`, `suggestion_delete_submit.php`, `suggestion_comment_*_submit.php` haben `empty($_SESSION['is_admin'])`-Gate.
- **CLI-only-Maintenance**: `cleanup_orphan_images.php` hat `PHP_SAPI !== 'cli'`-Guard.
- **HTTPS erzwungen** via `.htaccess`.
- **SRI-Integrity** für alle CDN-Ressourcen (Bootstrap, Leaflet).
- **PHPMailer aktuell** (6.12.0, Stand Anfang 2026 keine offenen CVEs).
- **`x.fpv-spots-germany.de` nicht in Git**: keine `.env` / Credentials im Repo gefunden.
- **Strong Randomness**: durchgängig `random_bytes()` für CSRF-Token, Selectoren, Validatoren, Dateinamen.

---

## Priorisierung (Fix-Reihenfolge)

1. **KRITISCH-1** (Re-Auth bei Passwortänderung) + **HOCH-4** (Re-Auth bei E-Mail/Username). Das ist ein zusammenhängender Fix-Block, der gemeinsam zuerst umgesetzt werden sollte – ohne beide ist der Account-Takeover bei Session-Zugriff trivial.
2. **HOCH-1** (Open Redirect favorite_submit): 10-Minuten-Fix mit Regex-Whitelist analog zu login_submit.
3. **HOCH-2** (Passwort-Policy bei Registrierung): trivial, aber jedes neu angelegte Konto bis dahin ist potenziell schwach.
4. **HOCH-3** (E-Mail-Validierung + Register-Rate-Limit): verhindert Spam-Accounts und kaputte Reset-Flows.
5. **HOCH-5** (Session-Cookie-Flags): zentrale Bootstrap-Datei, einmaliger Aufwand, breite Wirkung.
6. **MITTEL-1/5/6** (Rate-Limits Kontakt, Login pro Account, Upload): reduzieren Missbrauchspotenzial, keine akuten Exploits.
7. **MITTEL-2** (GET mit Side-Effect in messages.php) und **MITTEL-3** (Bilder-Orphans in spot.php DELETE): Code-Hygiene und Datenintegrität.
8. **MITTEL-4** (CSP ohne `unsafe-inline`): größerer Refactor, da alle inline-Scripts/Styles Nonces brauchen – planen, aber nicht blockierend.
9. **NIEDRIG / INFO**: Quality-of-Life-Verbesserungen.

Nach Umsetzung bitte **jede geänderte Datei** mit `php -l` syntaxprüfen und die Verifikations-Szenarien aus dem Plan (`/home/boss/.claude/plans/ich-bin-der-eigent-mer-zazzy-tulip.md`, Abschnitt „Verifikation") durchspielen.

---

## Schweregradskala

| Schweregrad   | Kriterium |
|---------------|-----------|
| **KRITISCH**  | Direkter Angriff möglich, Datenverlust / RCE / Auth-Bypass ohne besondere Vorbedingungen |
| **HOCH**      | Erhebliches Risiko, Ausnutzung mit überschaubarem Aufwand möglich |
| **MITTEL**    | Risiko vorhanden, Ausnutzung erfordert Umstände oder Kombination mehrerer Faktoren |
| **NIEDRIG**   | Geringes Risiko, Defense-in-Depth oder Best-Practice-Abweichung |
| **INFO**      | Kein direktes Risiko, aber Verbesserungspotenzial |

<?php
declare(strict_types=1);
// =============================================================
// Geräte-Cookie (bekannte Geräte)
//
// Schutz gegen Missbrauch der Kontosperre: Benutzernamen sind
// öffentlich sichtbar, ohne Ausnahme könnte jeder mit wenigen
// Fehlversuchen ein fremdes Konto dauerhaft sperren. Browser, die
// sich schon einmal erfolgreich eingeloggt haben, erhalten deshalb
// ein Geräte-Cookie und sind von der Kontosperre ausgenommen
// (OWASP "Device Cookies"). Das IP-Limit gilt weiterhin für alle.
//
// Das Cookie authentifiziert NICHT – es ersetzt kein Passwort.
// Format wie bei Remember-Me: "selector:validator", in der DB liegt
// nur der SHA-256-Hash des Validators (Tabelle login_devices).
// =============================================================

const DEVICE_COOKIE_NAME = 'fpv_device';
const DEVICE_COOKIE_DAYS = 180;

// -------------------------------------------------------------
// Cookie-Wert in Selector + Validator zerlegen (Format prüfen)
// -------------------------------------------------------------
if (!function_exists('parse_device_cookie')) {
    function parse_device_cookie(): ?array
    {
        $raw = $_COOKIE[DEVICE_COOKIE_NAME] ?? null;
        if (!is_string($raw) || !preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/', $raw, $m)) {
            return null;
        }
        return [$m[1], $m[2]];
    }
}

// -------------------------------------------------------------
// Geräte-Cookie im Browser löschen (Attribute wie beim Setzen)
// -------------------------------------------------------------
if (!function_exists('clear_device_cookie')) {
    function clear_device_cookie(): void
    {
        setcookie(DEVICE_COOKIE_NAME, '', [
            'expires'  => 1,
            'path'     => '/',
            'httponly' => true,
            'secure'   => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[DEVICE_COOKIE_NAME]);
    }
}

// -------------------------------------------------------------
// Gehört das vorhandene Geräte-Cookie gültig zu diesem Konto?
// -------------------------------------------------------------
if (!function_exists('has_valid_device_cookie')) {
    function has_valid_device_cookie(PDO $pdo, int $userId): bool
    {
        $parts = parse_device_cookie();
        if ($parts === null) {
            return false;
        }
        [$selector, $validator] = $parts;

        $stmt = $pdo->prepare(
            "SELECT validator_hash FROM login_devices
             WHERE selector = ? AND user_id = ? AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$selector, $userId]);
        $hash = $stmt->fetchColumn();

        return is_string($hash) && hash_equals($hash, hash('sha256', $validator));
    }
}

// -------------------------------------------------------------
// Neues Geräte-Cookie für ein Konto ausstellen.
// Ein evtl. vorhandenes (fremdes/abgelaufenes) Cookie dieses
// Browsers wird dabei ersetzt und sein DB-Eintrag entfernt.
// -------------------------------------------------------------
if (!function_exists('issue_device_cookie')) {
    function issue_device_cookie(PDO $pdo, int $userId): void
    {
        $old = parse_device_cookie();
        if ($old !== null) {
            $pdo->prepare("DELETE FROM login_devices WHERE selector = ?")->execute([$old[0]]);
        }

        $selector  = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $expiresTs = time() + DEVICE_COOKIE_DAYS * 86400;

        // Ablaufzeit in SQL berechnen: DB läuft in UTC, PHP in
        // Europe/Berlin – ein PHP-date() wäre gegen NOW() verschoben.
        $pdo->prepare(
            "INSERT INTO login_devices (selector, validator_hash, user_id, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))"
        )->execute([
            $selector,
            hash('sha256', $validator),
            $userId,
            DEVICE_COOKIE_DAYS,
        ]);

        setcookie(DEVICE_COOKIE_NAME, "$selector:$validator", [
            'expires'  => $expiresTs,
            'path'     => '/',
            'httponly' => true,
            'secure'   => true,
            'samesite' => 'Lax',
        ]);
    }
}

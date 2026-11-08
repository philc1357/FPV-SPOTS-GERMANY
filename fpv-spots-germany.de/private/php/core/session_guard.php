<?php
declare(strict_types=1);
// =============================================================
// Session-Guard
//
// Zentrale Helfer rund um das Beenden und Pruefen von Sessions.
//
// Hintergrund: PHP-Sessions liegen als Dateien auf dem Server und
// koennen nicht gezielt fuer einen bestimmten Benutzer geloescht
// werden. Deshalb wird der Zeitpunkt der letzten Passwortaenderung
// (users.password_changed_at) mit dem Login-Zeitpunkt der Session
// ($_SESSION['auth_time']) verglichen. Aeltere Sessions gelten als
// ungueltig und werden beim naechsten Request verworfen – damit
// wirkt eine Passwortaenderung auf allen Geraeten.
//
// Voraussetzung: session_start() wurde bereits aufgerufen
// (private/php/core/session_init.php).
// =============================================================

// -------------------------------------------------------------
// Remember-Me-Cookie im Browser loeschen
// Attribute muessen identisch zum Setzen sein, sonst ignoriert
// der Browser das Loeschen.
// -------------------------------------------------------------
if (!function_exists('clear_remember_cookie')) {
    function clear_remember_cookie(): void
    {
        setcookie('remember_me', '', [
            'expires'  => 1,
            'path'     => '/',
            'httponly' => true,
            'secure'   => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['remember_me']);
    }
}

// -------------------------------------------------------------
// Erwartet der Aufrufer JSON statt eines Redirects?
// API-Endpunkte unter /api/ und AJAX-Uploads (Feld ajax=1)
// bekommen Fehler als JSON, damit das Frontend sie anzeigen kann.
// -------------------------------------------------------------
if (!function_exists('wants_json_response')) {
    function wants_json_response(): bool
    {
        return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
            || ($_POST['ajax'] ?? '') === '1';
    }
}

// -------------------------------------------------------------
// Aktuelle Session sauber beenden
// 1. Session-Daten leeren
// 2. Session-Cookie im Browser loeschen (macht session_destroy nicht)
// 3. Session serverseitig zerstoeren
// -------------------------------------------------------------
if (!function_exists('destroy_current_session')) {
    function destroy_current_session(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => 1,
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool)($params['secure'] ?? true),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?: 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

// -------------------------------------------------------------
// Alle Sessions eines Benutzers entwerten
// Wird nach jeder Passwortaenderung aufgerufen (Aendern im Konto
// und Reset per Mail):
//  - password_changed_at setzen -> alle bestehenden Sessions gelten
//    ab sofort als veraltet (siehe enforce_session_freshness)
//  - alle Remember-Me-Tokens loeschen -> kein Auto-Login mehr
// -------------------------------------------------------------
if (!function_exists('invalidate_all_user_sessions')) {
    function invalidate_all_user_sessions(PDO $pdo, int $userId): void
    {
        $pdo->prepare("UPDATE users SET password_changed_at = NOW() WHERE id = ?")
            ->execute([$userId]);

        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")
            ->execute([$userId]);
    }
}

// -------------------------------------------------------------
// Wachtposten: veraltete Sessions aussperren
//
// Ist die Session aelter als die letzte Passwortaenderung (oder
// fehlt der Login-Zeitstempel ganz), wird sie beendet und der
// Benutzer zum Login geschickt. API-Endpunkte bekommen stattdessen
// eine JSON-Antwort mit Status 401.
// -------------------------------------------------------------
if (!function_exists('enforce_session_freshness')) {
    function enforce_session_freshness(PDO $pdo): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        // UNIX_TIMESTAMP() statt des Datetime-Strings: der Datenbankserver
        // laeuft in UTC, PHP in Europe/Berlin – ein strtotime() auf den
        // rohen DB-Wert waere um den Zeitzonen-Versatz falsch. Der Epoch-Wert
        // ist dagegen zeitzonenunabhaengig.
        //
        // In derselben Abfrage wird der Admin-Status frisch gelesen: er
        // stand bisher nur einmal beim Login in der Session, entzogene
        // Rechte wirkten dadurch bis zum Logout weiter.
        $stmt = $pdo->prepare(
            "SELECT UNIX_TIMESTAMP(password_changed_at) AS changed_ts, admin
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([(int)$_SESSION['user_id']]);
        $row = $stmt->fetch();

        // Konto existiert nicht mehr -> Session sofort beenden
        $sessionValid = $row !== false;

        if ($sessionValid) {
            $_SESSION['is_admin'] = (int)$row['admin'] === 1;

            // Nie geaendert -> gueltig (Bestandsnutzer bleiben eingeloggt).
            // Sonst muss die Session juenger als die Passwortaenderung sein
            // (authTime === 0 -> Session ohne Zeitstempel, gilt als veraltet).
            if (!empty($row['changed_ts'])) {
                $authTime     = isset($_SESSION['auth_time']) ? (int)$_SESSION['auth_time'] : 0;
                $sessionValid = $authTime >= (int)$row['changed_ts'];
            }
        }

        if ($sessionValid) {
            return;
        }

        clear_remember_cookie();
        destroy_current_session();

        if (wants_json_response()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['error' => 'session_expired', 'redirect' => '/login.php']);
            exit;
        }

        header('Location: /login.php');
        exit;
    }
}

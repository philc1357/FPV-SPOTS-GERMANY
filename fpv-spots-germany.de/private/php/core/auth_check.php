<?php
declare(strict_types=1);
// =============================================================
// Auto-Login über Remember-Me-Cookie
// Wird VOR session_start()-abhängiger Logik eingebunden.
// Voraussetzung: session_start() wurde bereits aufgerufen.
// =============================================================
require_once __DIR__ . '/session_guard.php';

// -------------------------------------------------------------
// Ablauf:
//  1. Selector nachschlagen, Validator zeitkonstant vergleichen
//  2. Session aufbauen (inkl. neuem CSRF-Token, Audit-Log)
//  3. Token rotieren – mit 60 s Kulanzfenster: der alte Token wird
//     nicht sofort gelöscht, sondern läuft in 60 s ab. Parallele
//     Requests mit demselben Cookie (mehrere Tabs nach Browserstart,
//     PWA) loggen sich so ebenfalls ein, statt das Cookie zu löschen.
//     Nur der Request, der den Token per UPDATE "beansprucht",
//     stellt einen neuen aus (rowCount-Prüfung).
//  4. Validator falsch bei bekanntem Selector -> Manipulation,
//     Token wird in der DB gelöscht.
// Ablaufzeiten werden in SQL berechnet (DB = UTC, PHP = Berlin).
// -------------------------------------------------------------
if (!isset($_SESSION['user_id']) && is_string($_COOKIE['remember_me'] ?? null) && $_COOKIE['remember_me'] !== '') {
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) === 2) {
        [$selector, $validator] = $parts;

        // DB-Verbindung holen (nur wenn noch nicht geladen)
        if (!isset($pdo)) {
            require_once __DIR__ . '/db.php';
        }

        $stmt = $pdo->prepare(
            "SELECT rt.id, rt.validator_hash, rt.user_id,
                    u.username, u.admin, u.terms_accepted_at
             FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.id
             WHERE rt.selector = ? AND rt.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if ($token && hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            // Auto-Login erfolgreich
            session_regenerate_id(true);
            $_SESSION['user_id']    = (int)$token['user_id'];
            $_SESSION['username']   = $token['username'];
            $_SESSION['is_admin']   = (int)$token['admin'] === 1;
            $_SESSION['terms_ok']   = !empty($token['terms_accepted_at']);
            $_SESSION['auth_time']  = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$token['user_id']]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'LOGIN_REMEMBER_ME', ?)")
                ->execute([$token['user_id'], client_ip()]);

            // Token rotieren (atomar via Transaktion)
            $pdo->beginTransaction();
            try {
                // Alten Token "beanspruchen": Restlaufzeit auf 60 s kürzen.
                // Liegt er schon im Kulanzfenster, hat ein paralleler Request
                // bereits rotiert -> rowCount 0, kein neuer Token.
                $claim = $pdo->prepare(
                    "UPDATE remember_tokens
                     SET expires_at = DATE_ADD(NOW(), INTERVAL 60 SECOND)
                     WHERE id = ? AND expires_at > DATE_ADD(NOW(), INTERVAL 60 SECOND)"
                );
                $claim->execute([$token['id']]);

                if ($claim->rowCount() === 1) {
                    $newSelector  = bin2hex(random_bytes(16));
                    $newValidator = bin2hex(random_bytes(32));

                    $pdo->prepare(
                        "INSERT INTO remember_tokens (selector, validator_hash, user_id, expires_at)
                         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))"
                    )->execute([
                        $newSelector,
                        hash('sha256', $newValidator),
                        $token['user_id'],
                    ]);

                    $pdo->commit();

                    // Cookie erst nach erfolgreichem DB-Commit setzen
                    setcookie('remember_me', "$newSelector:$newValidator", [
                        'expires'  => time() + 30 * 86400,
                        'path'     => '/',
                        'httponly' => true,
                        'secure'   => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    $pdo->commit();
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                // Alter Cookie bleibt erhalten; Fehler intern loggen
                error_log('remember_me token rotation failed: ' . $e->getMessage());
            }
        } else {
            // Bekannter Selector, aber falscher Validator -> Token ist
            // kompromittiert oder manipuliert: in der DB entwerten
            if ($token) {
                $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$token['id']]);
            }
            // Ungültiges Cookie entfernen
            clear_remember_cookie();
        }
    } else {
        clear_remember_cookie();
    }
}

// =============================================================
// Session-Freshness prüfen
// Sessions, die älter als die letzte Passwortänderung sind, werden
// hier beendet – so wirkt eine Passwortänderung auf allen Geräten.
// =============================================================
if (isset($_SESSION['user_id'])) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/db.php';
    }
    enforce_session_freshness($pdo);
}

// =============================================================
// Terms-of-Service-Wall
// Eingeloggte Nutzer ohne akzeptierte Nutzungsbedingungen werden
// auf die Akzeptanz-Seite weitergeleitet. Ergebnis wird in der
// Session gecacht, um wiederholte DB-Abfragen zu vermeiden.
// =============================================================
if (isset($_SESSION['user_id'])) {
    // Exemptions: Akzeptanz-Seite, Submit-Handler, Logout
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isExempt = (
        str_contains($requestUri, '/terms_accept')  ||
        str_contains($requestUri, '/logout_submit') ||
        str_contains($requestUri, '/terms_accept_submit')
    );

    if (!$isExempt) {
        // terms_ok wurde noch nicht in dieser Session gesetzt → einmalig DB prüfen
        if (!isset($_SESSION['terms_ok'])) {
            if (!isset($pdo)) {
                require_once __DIR__ . '/db.php';
            }
            $tStmt = $pdo->prepare("SELECT terms_accepted_at FROM users WHERE id = ? LIMIT 1");
            $tStmt->execute([(int)$_SESSION['user_id']]);
            $tRow = $tStmt->fetchColumn();
            $_SESSION['terms_ok'] = !empty($tRow);
        }

        if ($_SESSION['terms_ok'] === false) {
            // API-Endpunkte: JSON-Fehler statt Redirect
            if (wants_json_response()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
                echo json_encode(['error' => 'terms_not_accepted', 'redirect' => '/terms_accept.php']);
                exit;
            }
            header('Location: /terms_accept.php');
            exit;
        }
    }
}

<?php
declare(strict_types=1);
    require_once __DIR__ . "/../core/session_init.php";

    //Datenbankverbindungsdatei holen
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/redirect.php';
    require_once __DIR__ . '/../core/login_devices.php';

    // =========================================================
    // Redirect-Ziel + Fehler-Rückweg
    // Erfolg und Fehler führen auf die Seite zurück, von der aus
    // eingeloggt wurde; Fehler öffnen dort das Login-Banner.
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /');
        exit;
    }
    $redirect = strip_login_params(safe_redirect_target(post_str('redirect')));

    function login_fail(string $msg, string $redirect): void {
        $_SESSION['login_flash'] = ['type' => 'danger', 'msg' => $msg];
        $hash = '';
        if (($pos = strpos($redirect, '#')) !== false) {
            $hash = substr($redirect, $pos);
            $redirect = substr($redirect, 0, $pos);
        }
        $sep = str_contains($redirect, '?') ? '&' : '?';
        header('Location: ' . $redirect . $sep . 'showLogin=1' . $hash);
        exit;
    }

    // =========================================================
    // Eingaben holen + Grundprüfung
    // Das Login-Feld akzeptiert Benutzername ODER E-Mail-Adresse.
    // Benutzernamen dürfen kein "@" enthalten (siehe register_submit),
    // daher ist die Unterscheidung eindeutig.
    // =========================================================
    $loginInput    = trim(post_str('username'));
    $passwordInput = post_str('password');

    if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
        login_fail('Sicherheits-Token ungültig. Bitte Seite neu laden.', $redirect);
    }

    if ($loginInput === '' || $passwordInput === '' || strlen($loginInput) > 255) {
        login_fail('Bitte Benutzername bzw. E-Mail und Passwort eingeben.', $redirect);
    }

    // Brute-Force-Schutz: IP nach 5 Fehlversuchen in 5 Minuten sperren
    $ip = client_ip();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'LOGIN_FAILED'
           AND ip_address = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    $stmt->execute([$ip]);
    if ((int)$stmt->fetchColumn() >= 5) {
        login_fail('Zu viele Fehlversuche. Bitte warte 5 Minuten.', $redirect);
    }

    // Fester Dummy-Hash: wird geprüft, wenn das Konto nicht existiert,
    // damit die Antwortzeit nicht verrät, ob ein Konto existiert.
    // Echter bcrypt-Hash (Kosten 10 = PASSWORD_DEFAULT bis PHP 8.3) eines
    // Zufallswerts – ein ungültiger Hash würde sofort abbrechen.
    const LOGIN_DUMMY_HASH = '$2y$10$Qnd0BcC3Wo0fKQnjw3WNsuNWXupbThgWlxL63iLxPkq0GytaPl8zy';

    try {
        // =====================================================
        // User suchen (per E-Mail, falls "@" enthalten)
        // =====================================================
        $column = str_contains($loginInput, '@') ? 'email' : 'username';
        $sql = "SELECT id, username, password_hash, admin, terms_accepted_at
                FROM users WHERE $column = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$loginInput]);
        $user = $stmt->fetch();

        // =====================================================
        // Account-basiertes Rate-Limit
        // 10 Fehlversuche / 15 Min sperren das Konto IP-übergreifend
        // (Schutz gegen Password-Spraying via Botnetz). Browser mit
        // gültigem Geräte-Cookie für dieses Konto sind ausgenommen –
        // sonst könnte jeder ein fremdes Konto dauerhaft aussperren.
        // =====================================================
        if ($user && !has_valid_device_cookie($pdo, (int)$user['id'])) {
            $acctStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM audit_logs
                 WHERE action = 'LOGIN_FAILED'
                   AND user_id = ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );
            $acctStmt->execute([$user['id']]);
            if ((int)$acctStmt->fetchColumn() >= 10) {
                login_fail('Zu viele Fehlversuche für dieses Konto. Bitte warte 15 Minuten.', $redirect);
            }
        }

        // =====================================================
        // Passwort-Check (bei unbekanntem Konto gegen Dummy-Hash)
        // =====================================================
        $passwordOk = password_verify($passwordInput, $user ? $user['password_hash'] : LOGIN_DUMMY_HASH);

        if ($user && $passwordOk) {
            $userId = (int)$user['id'];

            // LOGIN ERFOLGREICH
            session_regenerate_id(true); // Schutz gegen Session-Fixation
            $_SESSION['user_id']  = $userId;
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (int)$user['admin'] === 1;
            $_SESSION['terms_ok'] = !empty($user['terms_accepted_at']);
            // Login-Zeitpunkt merken – wird gegen users.password_changed_at
            // geprüft, um alte Sessions nach einer Passwortänderung zu kippen
            $_SESSION['auth_time'] = time();
            // Neuer CSRF-Token für die eingeloggte Session
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Hash auf aktuelle Algorithmus-/Kosten-Einstellung anheben
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($passwordInput, PASSWORD_DEFAULT), $userId]);
            }

            // Remember-Me-Token erstellen wenn Checkbox aktiv
            // (Ablaufzeit in SQL: DB läuft in UTC, PHP in Europe/Berlin)
            if (post_str('remember_me') === '1') {
                $selector  = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));

                $rmStmt = $pdo->prepare(
                    "INSERT INTO remember_tokens (selector, validator_hash, user_id, expires_at)
                     VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))"
                );
                $rmStmt->execute([$selector, hash('sha256', $validator), $userId]);

                setcookie('remember_me', "$selector:$validator", [
                    'expires'  => time() + 30 * 86400,
                    'path'     => '/',
                    'httponly' => true,
                    'secure'   => true,
                    'samesite' => 'Lax',
                ]);
            }

            // Dieses Gerät als bekannt markieren (Ausnahme von der Kontosperre)
            if (!has_valid_device_cookie($pdo, $userId)) {
                issue_device_cookie($pdo, $userId);
            }

            // Audit Log schreiben
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'LOGIN_SUCCESS', ?)";
            $pdo->prepare($logSql)->execute([$userId, $ip]);

            $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);

            header('Location: ' . $redirect);
            exit;

        } else {
            // LOGIN FEHLGESCHLAGEN
            // Audit Log für Fehlversuch – inkl. user_id falls Konto existiert,
            // damit das Account-basierte Rate-Limit greifen kann.
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'LOGIN_FAILED', ?)";
            $pdo->prepare($logSql)->execute([$user ? $user['id'] : null, $ip]);

            login_fail('Benutzername/E-Mail oder Passwort falsch.', $redirect);
        }

    } catch (PDOException $e) {
        error_log($e->getMessage()); // intern loggen, sonst wird evtl Datenbankstruktur direkt im Browser ausgegeben.
        login_fail('Ein interner Fehler ist aufgetreten. Bitte später erneut versuchen.', $redirect);
    }

<?php
    session_start();

    //Datenbankverbindungsdatei holen
    require_once __DIR__ . '/../core/db.php';

    //Formulardaten holen
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    // 3. Validierung
    // Im Submit-Handler prüfen:
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die("CSRF-Fehler");
    }
    
    if (empty($usernameInput) || empty($passwordInput)) {
        header("Location: /public/html/errors/login_empty.html");
        exit;
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
        http_response_code(429);
        die("Zu viele Fehlversuche. Bitte warte 5 Minuten.");
    }

    // 4. User in der Datenbank suchen
    try {
        $sql = "SELECT id, username, password_hash, admin, terms_accepted_at FROM users WHERE username = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usernameInput]);
        $user = $stmt->fetch();

        // 4a. Account-basiertes Rate-Limit: 10 Fehlversuche / 15 Min sperren das
        //     Konto IP-übergreifend (Schutz gegen Password-Spraying via Botnetz).
        if ($user) {
            $acctStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM audit_logs
                 WHERE action = 'LOGIN_FAILED'
                   AND user_id = ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );
            $acctStmt->execute([$user['id']]);
            if ((int)$acctStmt->fetchColumn() >= 10) {
                http_response_code(429);
                die("Zu viele Fehlversuche. Bitte warte 15 Minuten.");
            }
        }

        // 5. Passwort-Check
        // password_verify vergleicht das Klartext-Passwort mit dem Hash aus der DB
        if ($user && password_verify($passwordInput, $user['password_hash'])) {
            
            // LOGIN ERFOLGREICH
            session_regenerate_id(true); // Schutz gegen Session-Fixation
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (int)$user['admin'] === 1;
            $_SESSION['terms_ok'] = !empty($user['terms_accepted_at']);

            // Remember-Me-Token erstellen wenn Checkbox aktiv
            if (!empty($_POST['remember_me'])) {
                $selector  = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 30 * 86400);

                $rmStmt = $pdo->prepare(
                    "INSERT INTO remember_tokens (selector, validator_hash, user_id, expires_at)
                     VALUES (?, ?, ?, ?)"
                );
                $rmStmt->execute([
                    $selector,
                    hash('sha256', $validator),
                    $user['id'],
                    $expiresAt,
                ]);

                setcookie('remember_me', "$selector:$validator", [
                    'expires'  => time() + 30 * 86400,
                    'path'     => '/',
                    'httponly'  => true,
                    'secure'   => true,
                    'samesite' => 'Lax',
                ]);
            }

            // Audit Log schreiben
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'LOGIN_SUCCESS', ?)";
            $pdo->prepare($logSql)->execute([$user['id'], client_ip()]);

            $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$user['id']]);

            // Redirect-Ziel: nur relative Pfade innerhalb der eigenen Seite erlauben
            $redirect = $_POST['redirect'] ?? '';
            if ($redirect !== '' && !preg_match('#^/[a-zA-Z0-9_/]+\.php(\?[a-zA-Z0-9_=&]+)?$#', $redirect)) {
                $redirect = '';
            }

            header('Location: ' . ($redirect !== '' ? $redirect : '/'));
            exit;

        } else {
            // LOGIN FEHLGESCHLAGEN
            // Audit Log für Fehlversuch – inkl. user_id falls Konto existiert,
            // damit das Account-basierte Rate-Limit (4a) greifen kann.
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'LOGIN_FAILED', ?)";
            $pdo->prepare($logSql)->execute([$user ? $user['id'] : null, client_ip()]);

            header("Location: /public/html/errors/login_failed.html");
            exit;
        }

    } catch (PDOException $e) {
        error_log($e->getMessage()); // intern loggen, sonst wird evtl Datenbankstruktur direkt im Browser ausgegeben.
        die("Ein interner Fehler ist aufgetreten.");
    }
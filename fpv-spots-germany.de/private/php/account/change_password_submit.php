<?php
declare(strict_types=1);
    // 1. Session starten & Authentifizierung prüfen
    require_once __DIR__ . "/../core/session_init.php";
    require_once __DIR__ . "/../core/auth_check.php"; // Remember-Me, Session-Freshness, Admin-Status, ToS-Wall

    //Datenbankverbindungsdatei holen
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/password_blacklist.php';
    require_once __DIR__ . '/../core/session_guard.php';
    require_once __DIR__ . '/../core/account_mails.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: /");
        exit;
    }

    // 2. Nur POST akzeptieren
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: /change_password.php");
        exit;
    }

    // 3. Eingaben holen (KEIN trim() bei Passwörtern – Leerzeichen können gewollt sein)
    $userId    = $_SESSION['user_id'];
    $currentPw = post_str('current_password');
    $newPass1  = post_str('password_field1');
    $newPass2  = post_str('password');

    // 4. Server-seitige Validierung
    if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
        die("CSRF-Fehler");
    }

    // 4a. Felder dürfen nicht leer sein
    if ($currentPw === '' || $newPass1 === '' || $newPass2 === '') {
        $error = "Bitte alle Passwortfelder ausfüllen.";
    }

    // 4b. Mindestlänge (minlength="8" im HTML ist bypassbar)
    if (!isset($error) && strlen($newPass1) < 8) {
        $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
    }

    // 4c. Maximale Länge – bcrypt verarbeitet nur die ersten 72 Bytes,
    //     alles danach würde stillschweigend ignoriert. Das Limit liegt
    //     daher genau bei 72 Bytes (strlen zählt Bytes, nicht Zeichen).
    if (!isset($error) && strlen($newPass1) > 72) {
        $error = "Das Passwort darf maximal 72 Zeichen lang sein (Umlaute und Sonderzeichen zählen doppelt).";
    }

    // 4c2. Schutz gegen häufig verwendete Passwörter (Top-1000-Blacklist)
    if (!isset($error) && is_blacklisted_password($newPass1)) {
        $error = "Dieses Passwort ist zu häufig verwendet. Bitte wähle ein sichereres.";
    }

    // 4d. Passwörter müssen übereinstimmen
    //     (password_confirm.js ist nur clientseitig – serverseitig zwingend nötig)
    if (!isset($error) && !hash_equals($newPass1, $newPass2)) {
        $error = "Die Passwörter stimmen nicht überein.";
    }

    // 5. Rate-Limit für die Re-Authentifizierung: Mit einer gekaperten
    //    Session ließe sich das aktuelle Passwort sonst beliebig oft raten.
    //    5 Fehlversuche pro Konto in 15 Minuten.
    if (!isset($error)) {
        $rlStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE action = 'PASSWORD_CHANGE_REAUTH_FAILED'
               AND user_id = ?
               AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $rlStmt->execute([$userId]);
        if ((int)$rlStmt->fetchColumn() >= 5) {
            $error = "Zu viele Fehlversuche. Bitte warte 15 Minuten.";
        }
    }

    // 6. Aktuelles Passwort aus der DB holen, um "same password"-Check zu ermöglichen
    if (!isset($error)) {
        $stmt = $pdo->prepare("SELECT username, email, password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();

        if (!$userData) {
            // Sollte nie passieren bei gültiger Session – trotzdem absichern
            $error = "Benutzer nicht gefunden.";
        }
    }

    // 6a. Aktuelles Passwort verifizieren (Re-Auth gegen Session-Hijacking / CSRF-Defense-in-Depth)
    if (!isset($error) && !password_verify($currentPw, $userData['password_hash'])) {
        // Audit-Log: Fehlversuch
        $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'PASSWORD_CHANGE_REAUTH_FAILED', ?)";
        $pdo->prepare($logSql)->execute([$userId, client_ip()]);
        $error = "Das aktuelle Passwort ist falsch.";
    }

    // 7. Sicherstellen, dass das neue Passwort nicht identisch zum alten ist
    if (!isset($error) && password_verify($newPass1, $userData['password_hash'])) {
        $error = "Das neue Passwort muss sich vom aktuellen Passwort unterscheiden.";
    }

    // 8. Passwort hashen & in DB schreiben
    if (!isset($error)) {
        try {
            // PASSWORD_DEFAULT = bcrypt (konsistent mit dem Rest des Projekts)
            $newHash = password_hash($newPass1, PASSWORD_DEFAULT);

            // Passwort setzen und alle bestehenden Anmeldungen entwerten –
            // beides atomar, damit kein Zwischenzustand entstehen kann.
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $userId]);

                // Setzt password_changed_at und löscht alle Remember-Me-Tokens
                // → Sessions auf ALLEN Geräten werden ungültig
                invalidate_all_user_sessions($pdo, (int)$userId);

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                throw $e;
            }

            // 9. Audit-Log schreiben
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'PASSWORD_CHANGED', ?)";
            $pdo->prepare($logSql)->execute([$userId, client_ip()]);

            // 9a. Inhaber per Mail informieren (fällt bei Kontoübernahme auf)
            send_password_changed_mail((string)$userData['email'], (string)$userData['username'], 'change');

            // 10. Aktuelles Gerät abmelden – Remember-Me-Cookie und
            //     Session-Cookie entfernen, Session serverseitig zerstören.
            //     Muss vor der HTML-Ausgabe passieren (setcookie sendet Header).
            clear_remember_cookie();
            destroy_current_session();

            $success = true;

        } catch (PDOException $e) {
            error_log('change_password_submit.php error: ' . $e->getMessage());
            $error = "Fehler beim Speichern. Bitte versuche es erneut.";
        }
    }
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System</title>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
</head>
<body class="bg-dark text-light">
    <div class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    Passwort erfolgreich geändert.<br>
                    <small>Aus Sicherheitsgründen wurdest du auf allen Geräten ausgeloggt. Bitte melde dich erneut an.</small>
                </div>
                <a href="/?showLogin=1" class="btn btn-primary mt-2">Zum Login</a>
            <?php else: ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <a href="/change_password.php" class="btn btn-warning mt-2">Zurück</a>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
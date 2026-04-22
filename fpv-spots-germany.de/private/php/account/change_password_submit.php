<?php
declare(strict_types=1);
    // 1. Session starten & Authentifizierung prüfen
    require_once __DIR__ . "/../core/session_init.php";

    //Datenbankverbindungsdatei holen
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/password_blacklist.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: /");
        exit;
    }

    // 2. Nur POST akzeptieren
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: /public/php/change_password.php");
        exit;
    }

    // 3. Eingaben holen (KEIN trim() bei Passwörtern – Leerzeichen können gewollt sein)
    $userId    = $_SESSION['user_id'];
    $currentPw = $_POST['current_password'] ?? '';
    $newPass1  = $_POST['password_field1']  ?? '';
    $newPass2  = $_POST['password']         ?? '';

    // 4. Server-seitige Validierung
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die("CSRF-Fehler");
    }

    // 4a. Felder dürfen nicht leer sein
    if (empty($currentPw) || empty($newPass1) || empty($newPass2)) {
        $error = "Bitte alle Passwortfelder ausfüllen.";
    }

    // 4b. Mindestlänge (minlength="8" im HTML ist bypassbar)
    if (!isset($error) && strlen($newPass1) < 8) {
        $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
    }

    // 4c. Maximale Länge – Schutz vor BCrypt-Truncation-Angriff
    //     BCrypt verarbeitet maximal 72 Bytes. Ein Angreifer könnte
    //     gezielt sehr lange Passwörter schicken, die nach Byte 72
    //     identisch sind. Hard-Limit hier verhindert das.
    if (!isset($error) && strlen($newPass1) > 50) {
        $error = "Das Passwort darf maximal 50 Zeichen lang sein.";
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

    // 6. Aktuelles Passwort aus der DB holen, um "same password"-Check zu ermöglichen
    if (!isset($error)) {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
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
            // PASSWORD_DEFAULT verwendet aktuell Argon2id (konsistent mit dem Rest des Projekts)
            $newHash = password_hash($newPass1, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $userId]);

            // 9. Audit-Log schreiben
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'PASSWORD_CHANGED', ?)";
            $pdo->prepare($logSql)->execute([$userId, client_ip()]);

            // 10. Session invalidieren – nach Passwortänderung neu einloggen erzwingen
            //     Verhindert, dass gestohlene Sessions weiterhin gültig sind
            session_regenerate_id(true);
            session_destroy();

            $success = true;

        } catch (PDOException $e) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body class="bg-dark text-light">
    <div class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    Passwort erfolgreich geändert.<br>
                    <small>Aus Sicherheitsgründen wurdest du ausgeloggt. Bitte melde dich erneut an.</small>
                </div>
                <a href="/" class="btn btn-primary mt-2">Zum Login</a>
            <?php else: ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <a href="/public/php/change_password.php" class="btn btn-warning mt-2">Zurück</a>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
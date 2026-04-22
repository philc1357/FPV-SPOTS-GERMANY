<?php
declare(strict_types=1);
    // 1. Session starten & Authentifizierung prüfen
    require_once __DIR__ . "/../core/session_init.php";

    //Datenbankverbindungsdatei holen
    require_once __DIR__ . '/../core/db.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: /");
        exit;
    }

    // 2. Nur POST akzeptieren
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: /public/php/change_username.php");
        exit;
    }

    // 3. Eingabe holen & bereinigen
    $userId      = $_SESSION['user_id'];
    $newUsername = trim($_POST['new_username'] ?? '');
    $currentPw   = $_POST['current_password'] ?? '';

    // 4. Server-seitige Validierung
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die("CSRF-Fehler");
    }

    // 4a. Re-Authentifizierung: aktuelles Passwort prüfen (Defense-in-Depth gegen
    //     Session-Hijacking; verhindert, dass ein Angreifer die Account-Identität
    //     übernimmt, ohne das Passwort zu kennen).
    if (empty($currentPw)) {
        $error = "Bitte aktuelles Passwort eingeben.";
    }
    if (!isset($error)) {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($currentPw, $row['password_hash'])) {
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'USERNAME_CHANGE_REAUTH_FAILED', ?)";
            $pdo->prepare($logSql)->execute([$userId, client_ip()]);
            $error = "Das aktuelle Passwort ist falsch.";
        }
    }

    //    (minlength="5" im HTML ist kein Schutz – client-side validation ist bypassbar)
    if (!isset($error) && (strlen($newUsername) < 5 || strlen($newUsername) > 50)) {
        $error = "Der Benutzername muss zwischen 5 und 50 Zeichen lang sein.";
    }

    // Nur erlaubte Zeichen: Buchstaben, Ziffern, Unterstriche, Bindestriche
    if (!isset($error) && !preg_match('/^[a-zA-Z0-9_\-]+$/', $newUsername)) {
        $error = "Der Benutzername darf nur Buchstaben, Zahlen, _ und - enthalten.";
    }

    // 6. Prüfen ob der neue Benutzername bereits vergeben ist
    //    (verhindert Enumeration: gleiche Fehlermeldung bei DB-Fehler)
    if (!isset($error)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $stmt->execute([$newUsername, $userId]);

        if ($stmt->fetch()) {
            $error = "Dieser Benutzername ist bereits vergeben.";
        }
    }

    // 7. Update durchführen
    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$newUsername, $userId]);

            // Session-Wert aktualisieren
            $_SESSION['username'] = $newUsername;

            // Audit-Log schreiben
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'USERNAME_CHANGED', ?)";
            $pdo->prepare($logSql)->execute([$userId, client_ip()]);

            $success = "Benutzername erfolgreich geändert.";

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
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php else: ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <a href="/public/php/dashboard.php" class="btn btn-primary mt-2">Zum Dashboard</a>
        </div>
    </div>
</body>
</html>
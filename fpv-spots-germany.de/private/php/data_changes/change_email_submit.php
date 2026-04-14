<?php
    // 1. Session starten & Authentifizierung prüfen
    session_start();

    //Datenbankverbindungsdatei holen
    require_once __DIR__ . '/../db.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: /");
        exit;
    }

    // 2. Nur POST akzeptieren
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: /public/php/change_email.php");
        exit;
    }

    // 3. Eingabe holen & bereinigen
    $userId   = $_SESSION['user_id'];
    $newEmail = trim($_POST['new_email'] ?? '');

    // 4. Server-seitige Validierung
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF-Fehler");
    }
    
    if (empty($newEmail)) {
        $error = "Bitte eine E-Mail-Adresse eingeben.";
    }

    // PHP-native E-Mail-Validierung (RFC-konform)
    if (!isset($error) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Bitte eine gültige E-Mail-Adresse eingeben.";
    }

    // Maximale Länge prüfen (DB-Feld-Schutz)
    if (!isset($error) && strlen($newEmail) > 255) {
        $error = "Die E-Mail-Adresse ist zu lang.";
    }

    // 6. Prüfen ob die neue E-Mail bereits vergeben ist
    //    (AND id != ? → User kann seine eigene aktuelle Mail nicht als "vergeben" geblockt bekommen)
    if (!isset($error)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $stmt->execute([$newEmail, $userId]);

        if ($stmt->fetch()) {
            $error = "Diese E-Mail-Adresse ist bereits vergeben.";
        }
    }

    // 7. Update durchführen
    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$newEmail, $userId]);

            // Audit-Log schreiben
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'EMAIL_CHANGED', ?)";
            $pdo->prepare($logSql)->execute([$userId, $_SERVER['REMOTE_ADDR']]);

            $success = "E-Mail-Adresse erfolgreich geändert.";

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
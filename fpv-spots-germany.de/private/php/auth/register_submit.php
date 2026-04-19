<?php
    session_start();

    //Datenbankverbindungsdatei holen
    require_once __DIR__ . '/../core/db.php';
    require_once __DIR__ . '/../core/password_blacklist.php';

// 2. Nur POST akzeptieren
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /public/php/register.php");
    exit;
}

// 3. Daten aus dem Formular entgegennehmen
$username  = trim($_POST['username'] ?? '');
$email     = trim($_POST['email'] ?? '');
$pass1 = $_POST['password_field1'] ?? '';
$pass2 = $_POST['password'] ?? '';

// Validierung
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die("CSRF-Fehler");
}

// Rate-Limit: max. 5 Registrierungs-Attempts pro IP in 15 Minuten
$ip = client_ip();
$rlStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action IN ('REGISTER_SUCCESS','REGISTER_FAILED')
       AND ip_address = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
);
$rlStmt->execute([$ip]);
if ((int)$rlStmt->fetchColumn() >= 5) {
    http_response_code(429);
    die("Zu viele Registrierungsversuche. Bitte warte 15 Minuten.");
}

if (empty($username) || empty($email) || empty($pass1) || empty($pass2)) {
    $message = "Bitte alle Felder ausfüllen.";
} elseif (strlen($username) < 5 || strlen($username) > 50
       || !preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
    $message = "Benutzername: 5–50 Zeichen, nur Buchstaben, Zahlen, _ und -.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $message = "Bitte eine gültige E-Mail-Adresse eingeben.";
} elseif (!hash_equals($pass1, $pass2)) {
    $message = "Die Passwörter stimmen nicht überein.";
} elseif (strlen($pass1) < 8) {
    $message = "Das Passwort muss mindestens 8 Zeichen lang sein.";
} elseif (strlen($pass1) > 50) {
    // BCrypt-Truncation-Schutz: ab 72 Bytes werden Passwörter abgeschnitten.
    $message = "Das Passwort darf maximal 50 Zeichen lang sein.";
} elseif (is_blacklisted_password($pass1)) {
    $message = "Dieses Passwort ist zu häufig verwendet. Bitte wähle ein sichereres.";
} elseif (($_POST['terms'] ?? '') !== '1') {
    $message = "Bitte akzeptiere die Nutzungsbedingungen.";
} else {
    // 4. Passwort sicher hashen
    $passwordHash = password_hash($pass1, PASSWORD_DEFAULT);

    try {
        // 5. Daten mit Prepared Statements einfügen
        $sql = "INSERT INTO users (username, email, password_hash, terms_accepted_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$username, $email, $passwordHash])) {
            $message = "Registrierung erfolgreich!";

            $userId = $pdo->lastInsertId();
            $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'REGISTER_SUCCESS', ?)";
            $pdo->prepare($logSql)->execute([$userId, client_ip()]);
        }
    } catch (PDOException $e) {
        error_log('register_submit.php: ' . $e->getMessage());
        if ($e->getCode() == 23000) {
            $message = "Benutzername oder E-Mail bereits vergeben.";
        } else {
            $message = "Fehler beim Speichern";
        }
        $pdo->prepare("INSERT INTO audit_logs (action, ip_address) VALUES ('REGISTER_FAILED', ?)")
            ->execute([$ip]);
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
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="d-flex">
                <a href="/public/php/register.php"><button class="btn btn-primary me-5">Zurück</button></a>
                <a href="/public/php/login.php"><button class="btn btn-success">Hier einloggen</button></a>
            </div>
        </div>
    </div>
</body>
</html>
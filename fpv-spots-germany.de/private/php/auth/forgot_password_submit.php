<?php
// =============================================================
// Passwort vergessen – Form-Submit
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/forgot_password.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

// Rate-Limit: max 3 Anfragen pro IP in 5 Minuten
$ip = client_ip();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM audit_logs
     WHERE action = 'PASSWORD_RESET_REQUESTED'
       AND ip_address = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
);
$stmt->execute([$ip]);
if ((int)$stmt->fetchColumn() >= 3) {
    http_response_code(429);
    $rateLimited = true;
}

$email = trim($_POST['email'] ?? '');

if (!isset($rateLimited) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // User suchen
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Alte Tokens löschen
        $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$user['id']]);

        // Abgelaufene Tokens aller User bereinigen
        $pdo->exec("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");

        // Neuen Token erstellen (Selector/Validator-Pattern)
        $selector  = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 Stunde

        $stmt = $pdo->prepare(
            "INSERT INTO password_reset_tokens (selector, validator_hash, user_id, expires_at)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $selector,
            hash('sha256', $validator),
            $user['id'],
            $expiresAt,
        ]);

        // Reset-Link erstellen
        $resetUrl = "https://fpv-spots-germany.de/public/php/reset_password.php?selector="
                  . urlencode($selector) . "&validator=" . urlencode($validator);

        // E-Mail senden
        try {
            require_once __DIR__ . '/../core/mailer_info.php';

            $mailer->addAddress($email);
            $mailer->isHTML(true);
            $mailer->Subject = 'Passwort zurücksetzen – FPV Spots Germany';
            $mailer->Body = '
                <div style="font-family:Arial,sans-serif; max-width:500px; margin:auto; padding:20px;">
                    <h2 style="color:#198754;">FPV Spots Germany</h2>
                    <p>Du hast eine Anfrage zum Zurücksetzen deines Passworts gestellt.</p>
                    <p>Klicke auf den folgenden Button, um ein neues Passwort zu setzen:</p>
                    <p style="text-align:center; margin:30px 0;">
                        <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '"
                           style="background:#198754; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px; font-weight:bold;">
                            Passwort zurücksetzen
                        </a>
                    </p>
                    <p style="color:#666; font-size:13px;">Dieser Link ist 1 Stunde gültig. Falls du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.</p>
                </div>';
            $mailer->AltBody = "Passwort zurücksetzen – FPV Spots Germany\n\n"
                             . "Klicke auf den folgenden Link, um dein Passwort zurückzusetzen:\n"
                             . $resetUrl . "\n\n"
                             . "Dieser Link ist 1 Stunde gültig.";

            $mailer->send();
        } catch (\Exception $e) {
            error_log('forgot_password_submit.php mail error: ' . $e->getMessage());
        }

        // Audit-Log
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'PASSWORD_RESET_REQUESTED', ?)")
            ->execute([$user['id'], $ip]);
    } else {
        // Auch bei nicht-existierender E-Mail loggen (für Rate-Limiting)
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (NULL, 'PASSWORD_RESET_REQUESTED', ?)")
            ->execute([$ip]);
    }
}

// Immer gleiche Erfolgsmeldung (kein User-Enumeration)
$csrfToken = $_SESSION['csrf_token'];
$isLoggedIn = isset($_SESSION['user_id']);
$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen – FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../../public/includes/header.php'; ?>

    <main class="container mt-5">
        <div class="card bg-secondary text-white p-4" style="max-width: 400px; margin: auto;">
            <?php if (isset($rateLimited)): ?>
                <div class="alert alert-danger">Zu viele Anfragen. Bitte warte 5 Minuten.</div>
            <?php else: ?>
                <div class="alert alert-success">
                    Falls ein Konto mit dieser E-Mail-Adresse existiert, wurde ein Link zum Zurücksetzen gesendet.
                </div>
            <?php endif; ?>
            <a href="/" class="btn btn-primary w-100">Zur Startseite</a>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>
</html>

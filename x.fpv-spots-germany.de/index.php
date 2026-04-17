<?php
// =============================================================
// FPV Spots Germany – Admin Login (x.fpv-spots-germany.de)
// Zugang nur für Nutzer mit admin = 1 in der users-Tabelle.
// =============================================================
session_start();

// CSRF-Token einmalig pro Session generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// --- POST: Login verarbeiten ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {

    // CSRF prüfen
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('CSRF-Fehler');
    }

    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    if ($usernameInput === '' || $passwordInput === '') {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        require_once __DIR__ . '/../vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die('Datenbankfehler');
        }

        // Brute-Force-Schutz: IP nach 5 Fehlversuchen in 5 Minuten sperren
        $ip   = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE action = 'ADMIN_LOGIN_FAILED'
               AND ip_address = ?
               AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 5) {
            http_response_code(429);
            die('Zu viele Fehlversuche. Bitte warte 5 Minuten.');
        }

        // User laden – nur Admins (admin = 1)
        $stmt = $pdo->prepare(
            "SELECT id, username, password_hash FROM users
             WHERE username = ? AND admin = 1 LIMIT 1"
        );
        $stmt->execute([$usernameInput]);
        $user = $stmt->fetch();

        if ($user && password_verify($passwordInput, $user['password_hash'])) {
            // Login erfolgreich
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int)$user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['is_admin']       = true;

            $pdo->prepare(
                "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'ADMIN_LOGIN_SUCCESS', ?)"
            )->execute([$user['id'], $ip]);

            header('Location: /admintool/');
            exit;
        } else {
            // Login fehlgeschlagen – keine genaue Fehlermeldung (Sicherheit)
            $pdo->prepare(
                "INSERT INTO audit_logs (action, ip_address) VALUES ('ADMIN_LOGIN_FAILED', ?)"
            )->execute([$ip]);

            $error = 'Benutzername oder Passwort falsch, oder kein Admin-Zugang.';
        }
    }
}

// --- POST: Logout ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    if (hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        session_unset();
        session_destroy();
        header('Location: /');
        exit;
    }
}

$isLoggedIn = isset($_SESSION['admin_id']);
$adminName  = htmlspecialchars($_SESSION['admin_username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <style>
        body {
            background-color: #1a1a2e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background-color: #16213e;
            border: 1px solid #0f3460;
            border-radius: 0.75rem;
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .login-card h1 {
            font-size: 1.5rem;
            color: #e94560;
            margin-bottom: 0.25rem;
        }
        .login-card .subtitle {
            color: #8892b0;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<main>
    <div class="login-card text-light">

        <?php if ($isLoggedIn): ?>

            <h1>Admin-Bereich</h1>
            <p class="subtitle">Eingeloggt als <strong><?= $adminName ?></strong></p>

            <p class="text-success mb-3">Du bist erfolgreich eingeloggt.</p>

            <form method="POST" action="/">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-danger w-100">Abmelden</button>
            </form>

        <?php else: ?>

            <h1>Admin Login</h1>
            <p class="subtitle">Zugang nur für Administratoren</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="login">

                <div class="mb-3">
                    <label for="username" class="form-label small text-secondary">Benutzername</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control bg-dark text-light border-secondary"
                        placeholder="Benutzername"
                        minlength="3"
                        maxlength="50"
                        required
                        autofocus>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small text-secondary">Passwort</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control bg-dark text-light border-secondary"
                        placeholder="Passwort"
                        minlength="8"
                        required>
                </div>

                <button type="submit" class="btn btn-danger w-100">Anmelden</button>
            </form>

            <a href="https://www.fpv-spots-germany.de"
               class="btn btn-outline-secondary w-100 mt-3">
                Zur Hauptseite
            </a>

        <?php endif; ?>

    </div>
</main>

</body>
</html>

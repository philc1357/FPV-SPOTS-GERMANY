<?php
declare(strict_types=1);
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/password_blacklist.php';

function safe_redirect_target(?string $raw): string {
    if (!$raw) return '/';
    if (!preg_match('#^/[A-Za-z0-9_\-./?&=]*$#', $raw)) return '/';
    if (str_starts_with($raw, '//')) return '/';
    return $raw;
}

function register_flash_redirect(string $type, string $msg, string $redirect, bool $reopen): void {
    $_SESSION['register_flash'] = ['type' => $type, 'msg' => $msg];
    $target = safe_redirect_target($redirect);
    if ($reopen) {
        $sep = (strpos($target, '?') === false) ? '?' : '&';
        $target .= $sep . 'showRegister=1';
    }
    header('Location: ' . $target);
    exit;
}

function register_success_redirect(string $msg): void {
    $_SESSION['login_flash'] = ['type' => 'success', 'msg' => $msg];
    header('Location: /?showLogin=1');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /");
    exit;
}

$redirectRaw = $_POST['redirect'] ?? '/';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    register_flash_redirect('danger', 'Sicherheits-Token ungültig. Bitte Seite neu laden.', $redirectRaw, true);
}

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$pass1    = $_POST['password_field1'] ?? '';
$pass2    = $_POST['password'] ?? '';

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
    register_flash_redirect('danger', 'Zu viele Registrierungsversuche. Bitte warte 15 Minuten.', $redirectRaw, true);
}

if (empty($username) || empty($email) || empty($pass1) || empty($pass2)) {
    register_flash_redirect('danger', 'Bitte alle Felder ausfüllen.', $redirectRaw, true);
} elseif (strlen($username) < 5 || strlen($username) > 50
       || !preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
    register_flash_redirect('danger', 'Benutzername: 5–50 Zeichen, nur Buchstaben, Zahlen, _ und -.', $redirectRaw, true);
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    register_flash_redirect('danger', 'Bitte eine gültige E-Mail-Adresse eingeben.', $redirectRaw, true);
} elseif (!hash_equals($pass1, $pass2)) {
    register_flash_redirect('danger', 'Die Passwörter stimmen nicht überein.', $redirectRaw, true);
} elseif (strlen($pass1) < 8) {
    register_flash_redirect('danger', 'Das Passwort muss mindestens 8 Zeichen lang sein.', $redirectRaw, true);
} elseif (strlen($pass1) > 50) {
    register_flash_redirect('danger', 'Das Passwort darf maximal 50 Zeichen lang sein.', $redirectRaw, true);
} elseif (is_blacklisted_password($pass1)) {
    register_flash_redirect('danger', 'Dieses Passwort ist zu häufig verwendet. Bitte wähle ein sichereres.', $redirectRaw, true);
} elseif (($_POST['terms'] ?? '') !== '1') {
    register_flash_redirect('danger', 'Bitte akzeptiere die Nutzungsbedingungen.', $redirectRaw, true);
}

$passwordHash = password_hash($pass1, PASSWORD_DEFAULT);

try {
    $sql = "INSERT INTO users (username, email, password_hash, terms_accepted_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$username, $email, $passwordHash])) {
        $userId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'REGISTER_SUCCESS', ?)")
            ->execute([$userId, $ip]);
        register_success_redirect('Registrierung erfolgreich! Du kannst dich jetzt einloggen.');
    }
    register_flash_redirect('danger', 'Fehler beim Speichern.', $redirectRaw, true);
} catch (PDOException $e) {
    error_log('register_submit.php: ' . $e->getMessage());
    $pdo->prepare("INSERT INTO audit_logs (action, ip_address) VALUES ('REGISTER_FAILED', ?)")
        ->execute([$ip]);
    if ($e->getCode() == 23000) {
        register_flash_redirect('danger', 'Benutzername oder E-Mail bereits vergeben.', $redirectRaw, true);
    }
    register_flash_redirect('danger', 'Fehler beim Speichern.', $redirectRaw, true);
}

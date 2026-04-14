<?php
// =============================================================
// Kontaktanfrage speichern
// =============================================================
session_start();

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/kontakt.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['contact_field'] ?? '');
$userId  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message) || strlen($message) > 5000) {
    header('Location: /public/php/kontakt_error.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO contact_requests (user_id, email, message) VALUES (?, ?, ?)"
    );
    $stmt->execute([$userId, $email, $message]);
} catch (PDOException $e) {
    error_log('kontakt_submit.php DB error: ' . $e->getMessage());
    header('Location: /public/php/kontakt_error.php');
    exit;
}

// E-Mail-Benachrichtigung senden
try {
    require_once __DIR__ . '/mailer.php';

    $mailer->addAddress('kontakt@fpv-spots-germany.de', 'FPV Spots Germany');
    $mailer->addReplyTo($email);
    $mailer->Subject = 'Neue Kontaktanfrage – FPV Spots Germany';
    $mailer->isHTML(false);
    $mailer->Body =
        "Neue Kontaktanfrage\n" .
        "===================\n\n" .
        "Von: {$email}\n\n" .
        "Nachricht:\n" . $message;

    $mailer->send();
} catch (Exception $e) {
    error_log('kontakt_submit.php mailer error: ' . $e->getMessage());
    // E-Mail-Fehler soll den Nutzer nicht blockieren – Anfrage wurde bereits gespeichert
}

header('Location: /public/php/kontakt_erfolg.php');
exit;

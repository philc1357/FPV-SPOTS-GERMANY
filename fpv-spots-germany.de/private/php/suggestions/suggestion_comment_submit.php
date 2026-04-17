<?php
// =============================================================
// Admin-Kommentar zu Verbesserungsvorschlag erstellen
// =============================================================
session_start();

require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /public/php/kritik.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

$suggestionId = (int)($_POST['suggestion_id'] ?? 0);
$body         = trim($_POST['body'] ?? '');
$userId       = (int)$_SESSION['user_id'];

if ($suggestionId <= 0 || strlen($body) < 3 || strlen($body) > 1000) {
    header('Location: /public/php/kritik.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO suggestion_comments (suggestion_id, user_id, body) VALUES (?, ?, ?)"
    );
    $stmt->execute([$suggestionId, $userId, $body]);
} catch (PDOException $e) {
    error_log('suggestion_comment_submit.php error: ' . $e->getMessage());
}

// In-App-Benachrichtigung für den Vorschlag-Autor speichern
try {
    $ownerIdStmt = $pdo->prepare("SELECT user_id FROM suggestions WHERE id = ?");
    $ownerIdStmt->execute([$suggestionId]);
    $ownerId = (int)$ownerIdStmt->fetchColumn();

    // Nicht den Admin selbst benachrichtigen, falls er seinen eigenen Vorschlag kommentiert
    if ($ownerId > 0 && $ownerId !== $userId) {
        $nStmt = $pdo->prepare(
            "INSERT INTO user_notifications (user_id, type, reference_id) VALUES (?, 'suggestion_comment', ?)"
        );
        $nStmt->execute([$ownerId, $suggestionId]);
    }
} catch (PDOException $e) {
    error_log('suggestion_comment_submit.php notification error: ' . $e->getMessage());
}

// E-Mail-Benachrichtigung an den Vorschlag-Autor senden
try {
    $ownerStmt = $pdo->prepare(
        "SELECT u.email, u.username
         FROM suggestions s
         JOIN users u ON s.user_id = u.id
         WHERE s.id = ?"
    );
    $ownerStmt->execute([$suggestionId]);
    $owner = $ownerStmt->fetch();

    if ($owner && filter_var($owner['email'], FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/../core/mailer.php';

        $recipientName = htmlspecialchars($owner['username'], ENT_QUOTES, 'UTF-8');
        $snippet       = mb_substr($body, 0, 200);
        $url           = 'https://fpv-spots-germany.de/public/php/kritik.php#suggestion-' . $suggestionId;

        $mailer->addAddress($owner['email'], $recipientName);
        $mailer->Subject = 'Dein Vorschlag wurde kommentiert – FPV Spots Germany';
        $mailer->isHTML(false);
        $mailer->Body =
            "Hallo {$recipientName},\n\n" .
            "dein Verbesserungsvorschlag auf FPV Spots Germany wurde soeben kommentiert.\n\n" .
            "Kommentar:\n" .
            "----------\n" .
            $snippet . (mb_strlen($body) > 200 ? ' …' : '') . "\n\n" .
            "Zum Vorschlag:\n" .
            $url . "\n\n" .
            "Viele Grüße\n" .
            "FPV Spots Germany";

        $mailer->send();
    }
} catch (Exception $e) {
    error_log('suggestion_comment_submit.php mailer error: ' . $e->getMessage());
}

header('Location: /public/php/kritik.php#suggestion-' . $suggestionId);
exit;

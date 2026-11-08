<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Nachrichten
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../../private/php/core/db.php';

$isLoggedIn = true;
$userId     = (int)$_SESSION['user_id'];
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

// Nachrichten-Benachrichtigungen als gelesen markieren
try {
    $stmt = $pdo->prepare(
        "UPDATE user_notifications SET read_at = NOW()
         WHERE user_id = ? AND type = 'new_message' AND read_at IS NULL"
    );
    $stmt->execute([$userId]);
} catch (PDOException $e) {
    error_log('messages.php notification read error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nachrichten – FPV Spots Germany</title>
    <meta name="description" content="Deine privaten Nachrichten auf FPV Spots Germany.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="app-csrf-token" content="<?= $csrfToken ?>">
    <meta name="app-user-id" content="<?= $userId ?>">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('/public/css/messages.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container py-4">
    <h1 class="h4 mb-3"><i class="bi bi-envelope-fill me-1"></i>Nachrichten</h1>

    <div class="row g-3">
        <!-- Sidebar: Konversationsliste -->
        <div class="col-12 col-md-4" id="sidebarCol">
            <div class="card card-dark text-light p-0">
                <div class="conversation-list" id="conversationList">
                    <div class="chat-empty p-4">
                        <span class="text-secondary"><i class="bi bi-hourglass-split me-1"></i>Lade Konversationen...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat-Bereich -->
        <div class="col-12 col-md-8 d-none d-md-block" id="chatCol">
            <div class="card card-dark text-light p-0">
                <div class="chat-container" id="chatContainer">
                    <div class="chat-empty" id="chatEmpty">
                        <span><i class="bi bi-chat-dots me-2"></i>Waehle eine Konversation</span>
                    </div>

                    <div class="d-none" id="chatActive">
                        <div class="chat-header">
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-sm btn-outline-secondary d-md-none" id="btnBack" title="Zurueck">
                                    <i class="bi bi-arrow-left"></i>
                                </button>
                                <a id="chatUsername" class="fw-semibold text-light text-decoration-none" href="#"></a>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" id="btnDelete" title="Konversation loeschen">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="chat-messages" id="chatMessages"></div>
                        <div class="chat-input-area">
                            <div class="d-flex gap-2">
                                <textarea id="msgInput"
                                          class="form-control bg-secondary text-light border-0"
                                          rows="1" maxlength="2000"
                                          placeholder="Nachricht schreiben..."></textarea>
                                <button class="btn btn-success flex-shrink-0" id="btnSend">
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

<script src="<?= asset('/public/js/pages/messages.js') ?>"></script>
</body>
</html>

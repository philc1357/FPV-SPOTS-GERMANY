<?php
// =============================================================
// FPV Spots Germany – Nachrichten-API
// =============================================================
session_start();
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt.']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$method   = $_SERVER['REQUEST_METHOD'];

// -- Routing -------------------------------------------------------
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'conversations':  getConversations($pdo, $userId); break;
        case 'messages':       getMessages($pdo, $userId);      break;
        case 'poll':           pollMessages($pdo, $userId);      break;
        case 'unread_count':   getUnreadCount($pdo, $userId);   break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unbekannte Aktion.']);
    }
} elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    // CSRF-Validierung
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $body['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiger CSRF-Token.']);
        exit;
    }

    $action = $body['action'] ?? '';
    switch ($action) {
        case 'send':                sendMessage($pdo, $userId, $body);          break;
        case 'delete_conversation': deleteConversation($pdo, $userId, $body);   break;
        case 'mark_read':           markRead($pdo, $userId, $body);             break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unbekannte Aktion.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt.']);
}

// ==================================================================
// GET: Konversationsliste
// ==================================================================
function getConversations(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("
        SELECT
            c.id              AS conversation_id,
            c.last_message_at,
            CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END AS other_user_id,
            u.username AS other_username,
            (
                SELECT m2.body FROM messages m2
                WHERE m2.conversation_id = c.id
                ORDER BY m2.created_at DESC LIMIT 1
            ) AS last_message_preview,
            (
                SELECT COUNT(*) FROM messages m3
                WHERE m3.conversation_id = c.id
                  AND m3.sender_id != ?
                  AND m3.read_at IS NULL
                  AND m3.created_at > COALESCE(
                      CASE WHEN c.user1_id = ? THEN c.deleted_by_user1 ELSE c.deleted_by_user2 END,
                      '1970-01-01'
                  )
            ) AS unread_count
        FROM conversations c
        JOIN users u ON u.id = CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END
        WHERE (c.user1_id = ? OR c.user2_id = ?)
        HAVING last_message_preview IS NOT NULL
        ORDER BY c.last_message_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
    $conversations = $stmt->fetchAll();

    // Soft-Delete filtern: Konversationen ausblenden, wenn alle Nachrichten
    // vor dem Loesch-Zeitstempel liegen
    $deleteCol = null;
    $filtered  = [];
    foreach ($conversations as $conv) {
        // Pruefen ob der User diese Konversation geloescht hat
        $delStmt = $pdo->prepare("
            SELECT CASE WHEN user1_id = ? THEN deleted_by_user1 ELSE deleted_by_user2 END AS deleted_at
            FROM conversations WHERE id = ?
        ");
        $delStmt->execute([$userId, $conv['conversation_id']]);
        $deletedAt = $delStmt->fetchColumn();

        if ($deletedAt !== null && $deletedAt !== false) {
            // Pruefen ob es Nachrichten nach dem Loesch-Zeitstempel gibt
            $afterStmt = $pdo->prepare("
                SELECT COUNT(*) FROM messages
                WHERE conversation_id = ? AND created_at > ?
            ");
            $afterStmt->execute([$conv['conversation_id'], $deletedAt]);
            if ((int)$afterStmt->fetchColumn() === 0) {
                continue; // Alle Nachrichten vor dem Loeschen -> ausblenden
            }
        }

        // Vorschau kuerzen
        $preview = $conv['last_message_preview'] ?? '';
        if (mb_strlen($preview, 'UTF-8') > 80) {
            $preview = mb_substr($preview, 0, 80, 'UTF-8') . '...';
        }
        $conv['last_message_preview'] = $preview;
        $filtered[] = $conv;
    }

    echo json_encode(['conversations' => $filtered]);
}

// ==================================================================
// GET: Nachrichten einer Konversation
// ==================================================================
function getMessages(PDO $pdo, int $userId): void
{
    $conversationId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['error' => 'conversation_id fehlt oder ungueltig.']);
        return;
    }

    // Teilnehmer-Validierung
    $conv = getConversationForUser($pdo, $conversationId, $userId);
    if (!$conv) {
        http_response_code(403);
        echo json_encode(['error' => 'Kein Zugriff auf diese Konversation.']);
        return;
    }

    // Soft-Delete-Zeitstempel ermitteln
    $deletedAt = ($conv['user1_id'] === $userId) ? $conv['deleted_by_user1'] : $conv['deleted_by_user2'];
    $sinceDate = $deletedAt ?? '1970-01-01';

    // Nachrichten laden
    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, u.username AS sender_username, m.body, m.created_at
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = ? AND m.created_at > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversationId, $sinceDate]);
    $messages = $stmt->fetchAll();

    // is_own Flag setzen
    foreach ($messages as &$msg) {
        $msg['is_own'] = ((int)$msg['sender_id'] === $userId);
    }
    unset($msg);

    // Read-Marker werden NICHT mehr hier gesetzt (würde GET zur State-Change-Operation
    // machen → CSRF-Vector via <img src="…?action=messages&conversation_id=X">).
    // Frontend muss explizit POST action=mark_read aufrufen.

    $otherId = ($conv['user1_id'] === $userId) ? $conv['user2_id'] : $conv['user1_id'];
    $otherStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $otherStmt->execute([$otherId]);
    $otherUsername = $otherStmt->fetchColumn();

    echo json_encode([
        'messages'       => $messages,
        'other_username' => $otherUsername,
        'other_user_id'  => $otherId,
    ]);
}

// ==================================================================
// POST: Nachricht senden
// ==================================================================
function sendMessage(PDO $pdo, int $userId, array $body): void
{
    $recipientId = filter_var($body['recipient_id'] ?? 0, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$recipientId) {
        http_response_code(400);
        echo json_encode(['error' => 'Empfaenger ungueltig.']);
        return;
    }

    if ($recipientId === $userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Du kannst dir nicht selbst schreiben.']);
        return;
    }

    // Empfaenger existiert?
    $recipientStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $recipientStmt->execute([$recipientId]);
    if (!$recipientStmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'Benutzer nicht gefunden.']);
        return;
    }

    $msgBody = trim($body['body'] ?? '');
    $len = mb_strlen($msgBody, 'UTF-8');
    if ($len < 1 || $len > 2000) {
        http_response_code(422);
        echo json_encode(['error' => 'Nachricht muss 1-2000 Zeichen lang sein.']);
        return;
    }

    // Rate-Limiting: max 30 Nachrichten pro Minute
    $rateStmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages
        WHERE sender_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $rateStmt->execute([$userId]);
    if ((int)$rateStmt->fetchColumn() >= 30) {
        http_response_code(429);
        echo json_encode(['error' => 'Zu viele Nachrichten. Bitte warte einen Moment.']);
        return;
    }

    // Konversation finden oder erstellen (LEAST/GREATEST fuer konsistente Reihenfolge)
    $u1 = min($userId, $recipientId);
    $u2 = max($userId, $recipientId);

    $convStmt = $pdo->prepare("SELECT id, deleted_by_user1, deleted_by_user2 FROM conversations WHERE user1_id = ? AND user2_id = ?");
    $convStmt->execute([$u1, $u2]);
    $conv = $convStmt->fetch();

    if ($conv) {
        $conversationId = (int)$conv['id'];

        // Soft-Delete des Empfaengers aufheben, damit die Konversation wieder erscheint
        if ($recipientId === $u1 && $conv['deleted_by_user1'] !== null) {
            $pdo->prepare("UPDATE conversations SET deleted_by_user1 = NULL WHERE id = ?")->execute([$conversationId]);
        } elseif ($recipientId === $u2 && $conv['deleted_by_user2'] !== null) {
            $pdo->prepare("UPDATE conversations SET deleted_by_user2 = NULL WHERE id = ?")->execute([$conversationId]);
        }
    } else {
        $insertConv = $pdo->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)");
        $insertConv->execute([$u1, $u2]);
        $conversationId = (int)$pdo->lastInsertId();
    }

    // Nachricht einfuegen
    $insertMsg = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)");
    $insertMsg->execute([$conversationId, $userId, $msgBody]);
    $messageId = (int)$pdo->lastInsertId();

    // last_message_at aktualisieren
    $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")->execute([$conversationId]);

    // Benachrichtigung erstellen (nur wenn noch keine ungelesene fuer diese Konversation existiert)
    $existsStmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_notifications
        WHERE user_id = ? AND type = 'new_message' AND reference_id = ? AND read_at IS NULL
    ");
    $existsStmt->execute([$recipientId, $conversationId]);
    if ((int)$existsStmt->fetchColumn() === 0) {
        $notifStmt = $pdo->prepare("INSERT INTO user_notifications (user_id, type, reference_id) VALUES (?, 'new_message', ?)");
        $notifStmt->execute([$recipientId, $conversationId]);
    }

    http_response_code(201);
    echo json_encode([
        'success'         => true,
        'conversation_id' => $conversationId,
        'message_id'      => $messageId,
    ]);
}

// ==================================================================
// POST: Konversation loeschen (Soft-Delete)
// ==================================================================
function deleteConversation(PDO $pdo, int $userId, array $body): void
{
    $conversationId = filter_var($body['conversation_id'] ?? 0, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['error' => 'conversation_id fehlt oder ungueltig.']);
        return;
    }

    $conv = getConversationForUser($pdo, $conversationId, $userId);
    if (!$conv) {
        http_response_code(403);
        echo json_encode(['error' => 'Kein Zugriff auf diese Konversation.']);
        return;
    }

    $col = ($conv['user1_id'] === $userId) ? 'deleted_by_user1' : 'deleted_by_user2';
    $stmt = $pdo->prepare("UPDATE conversations SET {$col} = NOW() WHERE id = ?");
    $stmt->execute([$conversationId]);

    echo json_encode(['success' => true]);
}

// ==================================================================
// GET: Polling – neue Nachrichten seit Zeitstempel
// ==================================================================
function pollMessages(PDO $pdo, int $userId): void
{
    $conversationId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $since = $_GET['since'] ?? '';

    if (!$conversationId || $since === '') {
        http_response_code(400);
        echo json_encode(['error' => 'conversation_id und since sind erforderlich.']);
        return;
    }

    $conv = getConversationForUser($pdo, $conversationId, $userId);
    if (!$conv) {
        http_response_code(403);
        echo json_encode(['error' => 'Kein Zugriff auf diese Konversation.']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, u.username AS sender_username, m.body, m.created_at
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = ? AND m.created_at > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversationId, $since]);
    $newMessages = $stmt->fetchAll();

    foreach ($newMessages as &$msg) {
        $msg['is_own'] = ((int)$msg['sender_id'] === $userId);
    }
    unset($msg);

    // Read-Marker NICHT in GET-Pfad setzen – Frontend nutzt POST action=mark_read.

    // Gesamte ungelesene Nachrichten (fuer Header-Badge)
    $unreadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.sender_id != ? AND m.read_at IS NULL
          AND ((c.user1_id = ? AND (c.deleted_by_user1 IS NULL OR m.created_at > c.deleted_by_user1))
            OR (c.user2_id = ? AND (c.deleted_by_user2 IS NULL OR m.created_at > c.deleted_by_user2)))
    ");
    $unreadStmt->execute([$userId, $userId, $userId]);
    $totalUnread = (int)$unreadStmt->fetchColumn();

    echo json_encode([
        'new_messages' => $newMessages,
        'total_unread' => $totalUnread,
    ]);
}

// ==================================================================
// GET: Ungelesene Nachrichten zaehlen (fuer Header)
// ==================================================================
function getUnreadCount(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.sender_id != ? AND m.read_at IS NULL
          AND ((c.user1_id = ? AND (c.deleted_by_user1 IS NULL OR m.created_at > c.deleted_by_user1))
            OR (c.user2_id = ? AND (c.deleted_by_user2 IS NULL OR m.created_at > c.deleted_by_user2)))
    ");
    $stmt->execute([$userId, $userId, $userId]);
    echo json_encode(['unread_count' => (int)$stmt->fetchColumn()]);
}

// ==================================================================
// POST: Nachrichten einer Konversation als gelesen markieren
// (zustandsändernd, läuft über CSRF-Gate des POST-Branches)
// ==================================================================
function markRead(PDO $pdo, int $userId, array $body): void
{
    $conversationId = filter_var($body['conversation_id'] ?? 0, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['error' => 'conversation_id fehlt oder ungueltig.']);
        return;
    }

    $conv = getConversationForUser($pdo, $conversationId, $userId);
    if (!$conv) {
        http_response_code(403);
        echo json_encode(['error' => 'Kein Zugriff auf diese Konversation.']);
        return;
    }

    $markStmt = $pdo->prepare("
        UPDATE messages SET read_at = NOW()
        WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL
    ");
    $markStmt->execute([$conversationId, $userId]);

    $notifStmt = $pdo->prepare("
        UPDATE user_notifications SET read_at = NOW()
        WHERE user_id = ? AND type = 'new_message' AND reference_id = ? AND read_at IS NULL
    ");
    $notifStmt->execute([$userId, $conversationId]);

    echo json_encode(['success' => true]);
}

// ==================================================================
// Helper: Konversation laden + Teilnehmer-Pruefung
// ==================================================================
function getConversationForUser(PDO $pdo, int $conversationId, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, user1_id, user2_id, deleted_by_user1, deleted_by_user2
        FROM conversations
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $stmt->execute([$conversationId, $userId, $userId]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $row['user1_id'] = (int)$row['user1_id'];
    $row['user2_id'] = (int)$row['user2_id'];
    return $row;
}

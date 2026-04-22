<?php
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="/public/css/messages.css">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

<script>
'use strict';
(function () {
    const CSRF   = document.querySelector('meta[name="app-csrf-token"]').content;
    const API    = '/public/php/api/messages.php';

    const sidebarCol       = document.getElementById('sidebarCol');
    const chatCol          = document.getElementById('chatCol');
    const conversationList = document.getElementById('conversationList');
    const chatContainer    = document.getElementById('chatContainer');
    const chatEmpty        = document.getElementById('chatEmpty');
    const chatActive       = document.getElementById('chatActive');
    const chatMessages     = document.getElementById('chatMessages');
    const chatUsername      = document.getElementById('chatUsername');
    const msgInput         = document.getElementById('msgInput');
    const btnSend          = document.getElementById('btnSend');
    const btnDelete        = document.getElementById('btnDelete');
    const btnBack          = document.getElementById('btnBack');

    let activeConvId    = null;
    let activeOtherUser = null;
    let lastMessageTime = null;
    let pollTimer       = null;

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str ?? ''));
        return d.innerHTML;
    }

    function formatTime(dateStr) {
        const d = new Date(dateStr);
        const now = new Date();
        const isToday = d.toDateString() === now.toDateString();
        if (isToday) {
            return d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' })
             + ' ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    // ==============================================================
    // Konversationsliste laden
    // ==============================================================
    async function loadConversations() {
        try {
            const res = await fetch(API + '?action=conversations');
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            renderConversationList(data.conversations);
        } catch (e) {
            conversationList.innerHTML = '<div class="p-3 text-danger small">Fehler beim Laden.</div>';
        }
    }

    function renderConversationList(conversations) {
        if (conversations.length === 0) {
            conversationList.innerHTML = '<div class="chat-empty p-4"><span class="text-secondary">Noch keine Nachrichten.</span></div>';
            return;
        }
        let html = '';
        conversations.forEach(c => {
            const isActive = c.conversation_id == activeConvId;
            const unread   = parseInt(c.unread_count) || 0;
            html += `
                <div class="conversation-item${isActive ? ' active' : ''}"
                     data-conv-id="${c.conversation_id}"
                     data-other-id="${c.other_user_id}"
                     data-other-name="${esc(c.other_username)}">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="conv-username text-light">${esc(c.other_username)}</span>
                        <span class="conv-time">${formatTime(c.last_message_at)}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="conv-preview">${esc(c.last_message_preview)}</span>
                        ${unread > 0 ? `<span class="badge bg-success ms-2">${unread}</span>` : ''}
                    </div>
                </div>`;
        });
        conversationList.innerHTML = html;

        conversationList.querySelectorAll('.conversation-item').forEach(el => {
            el.addEventListener('click', () => {
                const convId   = parseInt(el.dataset.convId);
                const otherId  = parseInt(el.dataset.otherId);
                const otherName = el.dataset.otherName;
                openConversation(convId, otherId, otherName);
            });
        });
    }

    // ==============================================================
    // Konversation oeffnen
    // ==============================================================
    async function openConversation(convId, otherId, otherName) {
        activeConvId    = convId;
        activeOtherUser = { id: otherId, name: otherName };
        lastMessageTime = null;

        // UI umschalten
        chatEmpty.classList.add('d-none');
        chatActive.classList.remove('d-none');
        chatCol.classList.remove('d-none');
        chatUsername.textContent = otherName;
        chatUsername.href = '/profile.php?id=' + otherId;
        chatMessages.innerHTML = '';

        // Aktiven Eintrag markieren
        conversationList.querySelectorAll('.conversation-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.convId) === convId);
            // Unread-Badge entfernen fuer diese Konversation
            if (parseInt(el.dataset.convId) === convId) {
                const badge = el.querySelector('.badge');
                if (badge) badge.remove();
            }
        });

        // Mobil: Sidebar ausblenden, Chat anzeigen
        if (window.innerWidth < 768) {
            sidebarCol.classList.add('d-none');
            chatCol.classList.remove('d-none');
            chatCol.classList.remove('d-md-block');
        }

        // Nachrichten laden
        try {
            const res = await fetch(API + '?action=messages&conversation_id=' + convId);
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            renderMessages(data.messages);
            // Read-Marker via POST setzen (CSRF-geschützt; nicht im GET-Pfad)
            markRead(convId);
        } catch (e) {
            chatMessages.innerHTML = '<div class="text-danger small p-2">Fehler beim Laden der Nachrichten.</div>';
        }

        // Polling starten
        startPolling();
    }

    async function markRead(convId) {
        try {
            await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_read',
                    conversation_id: convId,
                    csrf_token: CSRF,
                }),
            });
        } catch (_) {}
    }

    function renderMessages(messages) {
        let html = '';
        messages.forEach(m => {
            const cls = m.is_own ? 'own' : 'other';
            html += `
                <div>
                    <div class="msg-bubble ${cls}">${esc(m.body).replace(/\n/g, '<br>')}</div>
                    <div class="msg-time ${cls}">${formatTime(m.created_at)}</div>
                </div>`;
            lastMessageTime = m.created_at;
        });
        chatMessages.innerHTML = html;
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function appendMessages(messages) {
        messages.forEach(m => {
            const cls = m.is_own ? 'own' : 'other';
            const div = document.createElement('div');
            div.innerHTML = `
                <div class="msg-bubble ${cls}">${esc(m.body).replace(/\n/g, '<br>')}</div>
                <div class="msg-time ${cls}">${formatTime(m.created_at)}</div>`;
            chatMessages.appendChild(div);
            lastMessageTime = m.created_at;
        });
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // ==============================================================
    // Nachricht senden
    // ==============================================================
    async function sendMessage() {
        const body = msgInput.value.trim();
        if (!body || !activeOtherUser) return;

        btnSend.disabled = true;
        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send',
                    recipient_id: activeOtherUser.id,
                    body: body,
                    csrf_token: CSRF,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                alert(data.error || 'Fehler beim Senden.');
                return;
            }

            msgInput.value = '';
            msgInput.style.height = 'auto';

            // Neue Konversation? Dann convId aktualisieren
            if (!activeConvId) {
                activeConvId = data.conversation_id;
            }

            // Nachricht direkt anhaengen
            const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
            appendMessages([{ body: body, is_own: true, created_at: now }]);

            // Sidebar aktualisieren
            loadConversations();
        } catch (e) {
            alert('Netzwerkfehler beim Senden.');
        } finally {
            btnSend.disabled = false;
        }
    }

    btnSend.addEventListener('click', sendMessage);
    msgInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-Resize Textarea
    msgInput.addEventListener('input', () => {
        msgInput.style.height = 'auto';
        msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
    });

    // ==============================================================
    // Konversation loeschen
    // ==============================================================
    btnDelete.addEventListener('click', async () => {
        if (!activeConvId) return;
        if (!confirm('Konversation wirklich loeschen? Die Nachrichten werden fuer dich ausgeblendet.')) return;

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_conversation',
                    conversation_id: activeConvId,
                    csrf_token: CSRF,
                }),
            });
            if (!res.ok) {
                const data = await res.json();
                alert(data.error || 'Fehler beim Loeschen.');
                return;
            }

            // Zurueck zur leeren Ansicht
            activeConvId    = null;
            activeOtherUser = null;
            stopPolling();
            chatActive.classList.add('d-none');
            chatEmpty.classList.remove('d-none');

            // Mobil: Sidebar anzeigen
            if (window.innerWidth < 768) {
                sidebarCol.classList.remove('d-none');
                chatCol.classList.add('d-none');
            }

            loadConversations();
        } catch (e) {
            alert('Netzwerkfehler.');
        }
    });

    // ==============================================================
    // Zurueck-Button (Mobil)
    // ==============================================================
    btnBack.addEventListener('click', () => {
        stopPolling();
        activeConvId    = null;
        activeOtherUser = null;
        chatActive.classList.add('d-none');
        chatEmpty.classList.remove('d-none');
        sidebarCol.classList.remove('d-none');
        chatCol.classList.add('d-none');
        chatCol.classList.add('d-md-block');
        loadConversations();
    });

    // ==============================================================
    // Polling
    // ==============================================================
    function startPolling() {
        stopPolling();
        pollTimer = setInterval(pollNewMessages, 15000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollNewMessages() {
        if (!activeConvId || !lastMessageTime) return;
        try {
            const res = await fetch(API + '?action=poll&conversation_id=' + activeConvId + '&since=' + encodeURIComponent(lastMessageTime));
            if (!res.ok) return;
            const data = await res.json();
            if (data.new_messages && data.new_messages.length > 0) {
                appendMessages(data.new_messages);
                markRead(activeConvId);
                loadConversations(); // Sidebar aktualisieren
            }
        } catch (_) {}
    }

    // ==============================================================
    // Deep-Link: ?conversation_id=X
    // ==============================================================
    async function init() {
        await loadConversations();

        const params = new URLSearchParams(window.location.search);
        const convId = parseInt(params.get('conversation_id'));
        if (convId > 0) {
            // Konversation aus der Liste suchen oder direkt oeffnen
            const item = conversationList.querySelector(`[data-conv-id="${convId}"]`);
            if (item) {
                item.click();
            } else {
                // Konversation existiert, aber nicht in der Liste (z.B. neu erstellt)
                try {
                    const res = await fetch(API + '?action=messages&conversation_id=' + convId);
                    if (res.ok) {
                        const data = await res.json();
                        openConversation(convId, data.other_user_id, data.other_username);
                        // openConversation lädt erneut + ruft markRead selbst auf
                    }
                } catch (_) {}
            }
        }
    }

    init();
})();
</script>
</body>
</html>

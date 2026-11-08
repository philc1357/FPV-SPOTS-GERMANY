// =============================================================
// Nachrichten
// Ausgelagert aus public/php/account/messages.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
'use strict';
(function () {
    const CSRF   = document.querySelector('meta[name="app-csrf-token"]').content;
    const API    = '/api/messages.php';

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

            // Nachricht direkt anhaengen (mit server-seitigem Timestamp zur Vermeidung von Duplikaten beim Poll)
            appendMessages([{ body: body, is_own: true, created_at: data.created_at }]);

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

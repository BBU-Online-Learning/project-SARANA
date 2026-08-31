//public\js\chat\chat.js

document.addEventListener("DOMContentLoaded", () => {
    /*
    |--------------------------------------------------------------------------
    | GLOBAL CHAT STATE
    |--------------------------------------------------------------------------
    */
    window.chat = window.chat || {};

    window.chat.activeRoomId = window.chat.activeRoomId || null;
    if (window.Echo) {
        subscribeSidebarUpdates();
        subscribePresence();
    } else {
        showChatLoadStatus('Live updates are unavailable. Reload to reconnect.', true);
    }
    startPresencePing();
    /*
    |--------------------------------------------------------------------------
    | EVENT DELEGATION
    |--------------------------------------------------------------------------
    |
    | Prevents losing click events after dynamic DOM rendering.
    |
    */
    document.body.addEventListener("click", async (event) => {
        const roomItem = event.target.closest(".room-item");

        if (!roomItem) {
            return;
        }

        const roomId = roomItem.dataset.roomId;

        /*
        |--------------------------------------------------------------------------
        | PREVENT DUPLICATE REQUESTS
        |--------------------------------------------------------------------------
        */

        if (window.chat.activeRoomId == roomId && document.getElementById('chat-load-status')?.hidden !== false) {
            return;
        }

        await loadRoom(roomId, roomItem);
    });
});

/*
|--------------------------------------------------------------------------
| LOAD ROOM
|--------------------------------------------------------------------------
*/
let roomRequestController = null;
let currentLoadToken = 0;
let checkingMembership = false;

function showChatLoadStatus(message, failed = false) {
    const status = document.getElementById('chat-load-status');
    if (!status) return;
    status.textContent = message;
    status.hidden = !message;
    status.classList.toggle('alert-danger', failed);
    status.classList.toggle('alert-info', !failed);
}

function revokeRoom(roomId) {
    document.querySelector(`.room-item[data-room-id="${roomId}"]`)?.remove();
    if (String(window.chat.activeRoomId) !== String(roomId)) return;
    currentLoadToken++;
    window.Echo?.leave(window.chat.roomTopic);
    window.ChatVoice?.reset();
    document.getElementById('image-preview-close')?.click();
    document.querySelectorAll('#chat-room-container audio').forEach(audio => audio.pause());
    window.chat.activeRoomId = null;
    window.chat.channel = null;
    window.chat.voiceDraftFile = null;
    const warning = document.createElement('div');
    warning.className = 'alert alert-warning m-3';
    warning.textContent = 'You no longer have access to this conversation.';
    document.getElementById('chat-room-container').replaceChildren(warning);
}

async function refreshRoomMembership() {
    const roomId = window.chat?.activeRoomId;
    if (!roomId || checkingMembership) return;
    checkingMembership = true;
    try {
        const response = await axios.get(`/chat/rooms/${roomId}/access`, { headers: { Accept: 'application/json' }, timeout: 10000 });
        if (String(window.chat.activeRoomId) !== String(roomId)) return;
        if (!response.data.membership_id) { revokeRoom(roomId); return; }
        if (window.chat.membershipId !== response.data.membership_id) {
            window.chat.membershipId = response.data.membership_id;
            subscribeToRoom(roomId, roomId);
        }
        if (response.data.type === 'group') {
            const title = document.querySelector('#chat-room-container .teams-chat-title-group h1');
            if (title) title.textContent = response.data.name;
            const sidebar = document.querySelector(`.room-item[data-room-id="${roomId}"] .room-name`);
            if (sidebar) sidebar.textContent = response.data.name;
        }
    } catch (error) {
        if ([401, 403, 404, 419].includes(error.response?.status)) revokeRoom(roomId);
    } finally {
        checkingMembership = false;
    }
}

setInterval(refreshRoomMembership, 15000);
window.addEventListener('online', refreshRoomMembership);
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
async function loadRoom(roomId, roomItem = null) {
    const token = ++currentLoadToken;
    showChatLoadStatus('Loading conversation...');
    try {
        //REQUEST ROOM DATA

        if (roomRequestController) {
            roomRequestController.abort();
        }

        roomRequestController = new AbortController();

        const response = await axios.get(`/chat/rooms/${roomId}`, {
            signal: roomRequestController.signal, //if .abort is call Axios will cancels request
        }); //show route room.show

        if (token !== currentLoadToken) return;

        if (!response.data.membership_id || !response.data.room_type) {
            revokeRoom(roomId);
            showChatLoadStatus('Your session changed. Reload the page and sign in again.', true);
            return;
        }

        window.chat.nextCursor = response.data.next_cursor;
        window.chat.loadingOlderMessages = false;

        if (token !== currentLoadToken) return;

        // STORE ACTIVE ROOM
        const previousRoomId = window.chat.activeRoomId;
        window.chat.activeRoomId = roomId;
        window.chat.roomType = response.data.room_type;
        window.chat.membershipId = response.data.membership_id;
        subscribeToRoom(roomId, previousRoomId);

        /*
        |--------------------------------------------------------------------------
        | RENDER CHAT AREA
        |--------------------------------------------------------------------------
        */

        document.getElementById("chat-room-container").innerHTML =
            response.data.html;
        document.body.classList.add('chat-mobile-room');
        showChatLoadStatus(window.Echo ? '' : 'Live updates are unavailable. Reload to reconnect.', !window.Echo);

        initializeInfiniteScroll();
        /*
        |--------------------------------------------------------------------------
        | ACTIVE UI STATE
        |--------------------------------------------------------------------------
        */
        document
            .querySelectorAll(".room-item")
            .forEach((item) => item.classList.remove("active"));

        if (roomItem) {
            roomItem.classList.add("active");
        } else {
            document
                .querySelector(`[data-room-id="${roomId}"]`)
                ?.classList.add("active");
        }

        // AUTO SCROLL TO BOTTOM

        scrollMessagesToBottom();
        await markRoomAsRead();
        // ← ADD THIS
        updatePresenceUI();
    } catch (error) {
        if (token !== currentLoadToken) return;
        if ([401, 403, 404, 419].includes(error.response?.status)) {
            revokeRoom(roomId);
        }
        showChatLoadStatus('Could not open this conversation. Choose it again to retry, or reload to check your access.', true);
        console.error("Failed loading room:", error);
    }
}

//Subscribe To Room Channel
function subscribeToRoom(roomId, previousRoomId) {
    if (!window.Echo) {
        window.chat.channel = null;
        return;
    }
    if (window.chat.channel) {
        window.chat.channel.stopListening(".message.sent");
        window.chat.channel.stopListening(".conversation.updated");
        window.chat.channel.stopListening(".read.updated");
        window.chat.channel.stopListening(".message.updated");
        window.chat.channel.stopListening(".message.deleted");
        window.chat.channel.stopListening(".reaction.updated");

        window.Echo.leave(window.chat.roomTopic || `chat.room.${previousRoomId}`);
    }

    window.chat.roomTopic = window.chat.roomType === 'group'
        ? `chat.membership.${window.chat.membershipId}` : `chat.room.${roomId}`;
    const channel = window.chat.roomType === 'group'
        ? window.Echo.private(window.chat.roomTopic) : window.Echo.join(window.chat.roomTopic);

    channel.listen(".message.sent", window.ChatRealtime.handleIncomingMessage);
    channel.listen(".conversation.updated", updateConversationList);
    channel.listen(".read.updated", handleReadReceipt);
    channel.listenForWhisper("typing", window.ChatRealtime.handleUserTyping);
    channel.listen('.group.typing', window.ChatRealtime.handleUserTyping);
    channel.listen(".message.updated", handleMessageUpdated);
    channel.listen(".message.deleted", handleMessageDeleted);
    channel.listen(".reaction.updated", handleReactionUpdated);

    window.chat.channel = channel;
}

function subscribeSidebarUpdates() {
    window.Echo.private(`user.${window.chat.currentUserId}`)
        .listen(".sidebar.updated", updateConversationList)
        .listen(".unread.count.updated", handleUnreadCountUpdated);
}
function handleUnreadCountUpdated(event) {
    const roomItem = document.querySelector(
        `[data-room-id="${event.room_id}"]`,
    );

    if (!roomItem) {
        return;
    }

    const previewRow = roomItem.querySelector(".teams-room-preview-row");

    if (!previewRow) {
        return;
    }

    // Look for an existing badge inside this room card
    let badge = previewRow.querySelector(".unread-badge");

    if (event.unread_count > 0) {
        if (badge) {
            // Update existing badge in place
            badge.textContent = event.unread_count;
        } else {
            // Create and append a new badge
            badge = document.createElement("span");
            badge.className = "unread-badge";
            badge.textContent = event.unread_count;
            previewRow.appendChild(badge);
        }
    } else {
        // Count is 0 — remove badge entirely if it exists
        if (badge) {
            badge.remove();
        }
    }
}
//Create Incoming Message Handler
window.ChatRealtime = {
    async handleIncomingMessage(event) {
        await appendIncomingMessage(event);

        // Only mark as read if this message actually belongs to the room
        // currently open in the UI — not just "whatever activeRoomId says"
        if (
            String(event.room_id) === String(window.chat.activeRoomId) &&
            document.hasFocus()
        ) {
            await markRoomAsRead();
        }
    },

    handleUserTyping(event) {
        if (event.userId === window.chat.currentUserId) {
            return;
        }
        showTypingIndicator(event.userName);
    },
};

async function appendIncomingMessage(event) {
    const response = await axios.get(`/chat/messages/${event.message_id}/html`);

    const html = response.data.html;

    const optimistic = document.querySelector(
        `[data-client-id="${event.client_uuid}"]`,
    );

    if (optimistic) {
        optimistic.outerHTML = html;
        scrollMessagesToBottom();
        return;
    }

    const container = document.querySelector(".messages-container");

    if (!container) {
        return;
    }
    const exists = document.querySelector(
        `[data-message-id="${response.data.message_id}"]`,
    );

    if (exists) {
        return;
    }
    container.insertAdjacentHTML("beforeend", html);

    scrollMessagesToBottom();
}

let typingTimeout;

function showTypingIndicator(userName) {
    const indicator = document.getElementById("typing-indicator");

    if (!indicator) {
        return;
    }

    indicator.innerText = `${userName} is typing...`;
    indicator.classList.add("active");

    clearTimeout(typingTimeout);

    typingTimeout = setTimeout(() => {
        indicator.innerText = "";
        indicator.classList.remove("active");
    }, 2000);
}
//InfiniteScroll
function initializeInfiniteScroll() {
    const container = document.querySelector(".messages-container");

    if (!container) {
        return;
    }

    container.onscroll = async () => {
        if (container.scrollTop < 100) {
            await loadOlderMessages();
        }
    };
}
//load Older Messages
async function loadOlderMessages() {
    if (!window.chat.nextCursor || window.chat.loadingOlderMessages) {
        return;
    }

    window.chat.loadingOlderMessages = true;

    try {
        const response = await axios.get(
            `/chat/rooms/${window.chat.activeRoomId}/older-messages`,
            {
                params: {
                    cursor: window.chat.nextCursor,
                },
            },
        );

        window.chat.nextCursor = response.data.next_cursor;

        prependMessages(response.data.html);
    } finally {
        window.chat.loadingOlderMessages = false;
    }
}
//User stays at same position Screen don't jumps like telegram or messenger
function prependMessages(html) {
    const container = document.querySelector(".messages-container");

    if (!container) {
        return;
    }

    const previousHeight = container.scrollHeight;

    container.insertAdjacentHTML("afterbegin", html);

    const newHeight = container.scrollHeight;

    container.scrollTop += newHeight - previousHeight;
}

function updateConversationList(event) {
    const room = document.querySelector(`[data-room-id="${event.room_id}"]`);

    if (!room) {
        return;
    }

    const message = room.querySelector(".room-last-message");

    const time = room.querySelector(".room-last-time");

    if (message) {
        message.innerText = `${event.sender}: ${event.body}`;
    }

    if (time) {
        time.innerText = event.created_at;
    }

    // ← ADD THIS: move to top of the list
    room.parentElement.prepend(room);
}
// Join Presence Channel Once
function subscribePresence() {
    window.onlineUsers = [];

    window.Echo.join("online")

        .here((users) => {
            window.onlineUsers = users;

            updatePresenceUI();
        })

        .joining((user) => {
            window.onlineUsers.push(user);

            updatePresenceUI();
        })

        .leaving((user) => {
            window.onlineUsers = window.onlineUsers.filter(
                (u) => u.id !== user.id,
            );

            updatePresenceUI();
        });
}
function updatePresenceUI() {
    document.querySelectorAll(".presence-dot").forEach((dot) => {
        const userId = parseInt(dot.dataset.userId);

        const online =
            window.onlineUsers &&
            window.onlineUsers.some((u) => u.id === userId);

        dot.style.background = online ? "#16a34a" : "#a0a0a0";

        document
            .querySelectorAll(`.user-status[data-user-id="${userId}"]`)
            .forEach((label) => {
                if (online) {
                    label.textContent = "Online";
                } else {
                    label.textContent = formatLastSeen(label.dataset.lastSeen);
                }
            });
    });
}
//helper for updatePresenceUI()
function formatLastSeen(dateString) {
    if (!dateString) {
        return "Offline";
    }

    const diffSeconds = Math.floor(
        (Date.now() - new Date(dateString).getTime()) / 1000,
    );

    if (diffSeconds < 60) return "Last seen just now";
    if (diffSeconds < 3600)
        return `Last seen ${Math.floor(diffSeconds / 60)}m ago`;
    if (diffSeconds < 86400)
        return `Last seen ${Math.floor(diffSeconds / 3600)}h ago`;
    if (diffSeconds < 172800) return "Last seen yesterday";

    const days = Math.floor(diffSeconds / 86400);
    return `Last seen ${days}d ago`;
}
function handleReadReceipt(event) {
    const readTimestamp = new Date(event.read_at).getTime() / 1000;

    document.querySelectorAll(".teams-read-row").forEach((row) => {
        const message = row.closest(".message-item");
        if (!message) return;

        const senderId = Number(message.dataset.senderId);

        // Read receipts only apply to messages *we* sent
        if (senderId !== window.chat.currentUserId) return;

        const createdAt = Number(message.dataset.createdAt);
        if (createdAt > readTimestamp) return; // message was sent after this read event

        const isGroup = row.dataset.isGroup === "1";

        if (!isGroup) {
            // DIRECT CHAT — unchanged checkmark behavior
            const receipt = row.querySelector(".read-status");
            if (receipt) receipt.innerText = "✓✓";
            return;
        }

        // GROUP CHAT — update the seen-by avatar stack
        addReaderToSeenByStack(row, event.reader_id, event.reader_name);
    });
}

const MAX_SEEN_BY_AVATARS = 3;

function addReaderToSeenByStack(row, readerId, readerName) {
    const stack = row.querySelector(".seen-by-stack");
    if (!stack) return;

    // Already shown? nothing to do.
    if (stack.querySelector(`.seen-by-avatar[data-user-id="${readerId}"]`)) {
        return;
    }

    stack.classList.remove("is-empty");

    const currentAvatarCount = stack.querySelectorAll(".seen-by-avatar").length;
    const overflowEl = stack.querySelector(".seen-by-overflow");

    if (currentAvatarCount < MAX_SEEN_BY_AVATARS) {
        const initial = (readerName || "?").trim().charAt(0).toUpperCase();

        const avatar = document.createElement("span");
        avatar.className = "seen-by-avatar seen-by-new";
        avatar.dataset.userId = readerId;
        avatar.title = `Seen by ${readerName}`;
        avatar.textContent = initial;

        // Insert before the overflow badge if one exists, else append
        if (overflowEl) {
            stack.insertBefore(avatar, overflowEl);
        } else {
            stack.appendChild(avatar);
        }
    } else {
        // Stack is full — bump (or create) the overflow counter instead
        if (overflowEl) {
            const current =
                parseInt(overflowEl.textContent.replace("+", ""), 10) || 0;
            overflowEl.textContent = `+${current + 1}`;
        } else {
            const badge = document.createElement("span");
            badge.className = "seen-by-overflow";
            badge.textContent = "+1";
            stack.appendChild(badge);
        }
    }
}

async function markRoomAsRead() {
    if (!window.chat.activeRoomId || document.hidden) {
        return;
    }

    try {
        await axios.post(`/chat/rooms/${window.chat.activeRoomId}/mark-read`);
    } catch (error) {
        console.error(error);
    }
}

function scrollMessagesToBottom() {
    const messageContainer = document.querySelector(".messages-container");

    if (!messageContainer) {
        return;
    }

    messageContainer.scrollTop = messageContainer.scrollHeight;
}

//////////// handle edit message /////////////
async function handleMessageUpdated(event) {
    const response = await axios.get(`/chat/messages/${event.message_id}/html`);

    const existing = document.querySelector(
        `[data-message-id="${event.message_id}"]`,
    );

    if (!existing) return;

    /*
    | insertAdjacentHTML + remove gives us a live reference to the new node,
    | unlike outerHTML assignment which orphans the element and returns nothing.
    */
    existing.insertAdjacentHTML("afterend", response.data.html);
    existing.remove();

    const updated = document.querySelector(
        `[data-message-id="${event.message_id}"]`,
    );

    if (!updated) return;

    /*
    | Flash animation for the recipient only.
    | The sender already flashed optimistically in messages.js before
    | this broadcast even arrives, so we skip them here to avoid double-flash.
    */
    const senderId = Number(updated.dataset.senderId);

    if (senderId !== window.chat.currentUserId) {
        const bubble = updated.querySelector(".teams-message-bubble");
        if (bubble) {
            bubble.classList.add("message-edited-highlight");
            setTimeout(
                () => bubble.classList.remove("message-edited-highlight"),
                1000,
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| HANDLE MESSAGE DELETED broadcast
|--------------------------------------------------------------------------
| Fired for all clients (including the sender, who gets the optimistic
| update too — replaceWithDeletedUI is idempotent so the double-call is safe).
*/
async function handleMessageDeleted(event) {
    // Refresh from server so text, attachment, and voice messages all re-render correctly.
    const existing = document.querySelector(
        `[data-message-id="${event.message_id}"]`,
    );
    if (!existing) return;

    try {
        const response = await axios.get(
            `/chat/messages/${event.message_id}/html`,
        );
        existing.insertAdjacentHTML("afterend", response.data.html);
        existing.remove();
    } catch (error) {
        console.error("Delete refresh failed:", error);
         // Fallback if the HTML refresh fails.
        replaceWithDeletedUI(event.message_id);
    }

    const room = document.querySelector(`[data-room-id="${event.room_id}"]`);
    if (!room) return;

    const preview = room.querySelector(".room-last-message");
    if (preview) {
        preview.innerHTML = "<em>This message was deleted</em>";
    }
}

/*
|--------------------------------------------------------------------------
| HANDLE REACTION UPDATED broadcast
|--------------------------------------------------------------------------
*/
function handleReactionUpdated(event) {
    const reactionArea = document.querySelector(
        `.message-reactions[data-message-id="${event.message_id}"]`,
    );
    if (!reactionArea) return;

    renderReactionPills(reactionArea, event.message_id, event.reactions);
}

/*
|--------------------------------------------------------------------------
| RENDER REACTION PILLS
|--------------------------------------------------------------------------
*/

function renderReactionPills(container, messageId, reactions) {
    container.innerHTML = "";

    window.chat.allowedReactions.forEach((emoji) => {
        const count = reactions[emoji] || 0;
        if (count === 0) return;

        const btn = document.createElement("button");
        btn.className = "reaction-pill";
        btn.dataset.messageId = messageId;
        btn.dataset.emoji = emoji;
        btn.textContent = `${emoji} ${count}`;
        container.appendChild(btn);
    });
}

///This captures most tab/window closes.

window.addEventListener("pagehide", () => {
    if (window.chat?.activeRoomId && window.Echo) {
        window.Echo.leave(window.chat.roomTopic || `chat.room.${window.chat.activeRoomId}`);
    }
    if (!window.chat?.currentUserId) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) {
        return;
    }

    const formData = new FormData();
    formData.append("_token", token);

    navigator.sendBeacon("/chat/presence/ping", formData);
});
/*
|--------------------------------------------------------------------------
| PRESENCE PING — heartbeat while the tab is active
|--------------------------------------------------------------------------
| Bounds the staleness of last_seen_at to one interval, even when the
| browser never gets a chance to fire a clean disconnect event (crash,
| dead battery, internet drop, lid close). Skips background tabs since
| nobody's watching the UI update anyway and it just wastes requests.
*/
function startPresencePing() {
    setInterval(async () => {
        if (document.hidden) {
            return;
        }

        try {
            await axios.post("/chat/presence/ping");
        } catch (error) {
            console.error("Presence ping failed", error);
        }
    }, 60000);
}

// public/js/chat/search.js

/* =========================================================
   PART 1 — SIDEBAR: filter the room list by name (client-side)
   ========================================================= */
document.addEventListener("input", (e) => {
    if (!e.target.matches("#chat-search-input")) return;
    filterConversationList(e.target.value.trim().toLowerCase());
});

function filterConversationList(term) {
    const rooms = document.querySelectorAll(".teams-room-scroll .room-item");
    const emptyState = document.getElementById("conversation-search-empty");
    const filter = document.querySelector('[data-conversation-filter][aria-pressed="true"]')?.dataset.conversationFilter || 'all';
    let visibleCount = 0;

    rooms.forEach((room) => {
        const name = room.querySelector(".room-name")?.textContent.toLowerCase() || "";
        const matchesType = filter === 'all' || (filter === 'unread' ? !!room.querySelector('.unread-badge') : room.dataset.roomType === filter);
        const match = (!term || name.includes(term)) && matchesType;
        room.style.display = match ? "" : "none";
        if (match) visibleCount++;
    });

    if (emptyState) {
        emptyState.style.display = (term || filter !== 'all') && visibleCount === 0 ? "block" : "none";
    }
}

/* =========================================================
   PART 2 — IN-CHAT: search messages inside the open room
   ========================================================= */
let roomSearchDebounce;
let roomSearchResults = [];
let roomSearchIndex = -1;
let roomSearchRequestId = 0;

document.addEventListener("click", (e) => {
    const result = e.target.closest('[data-room-search-result]');
    if (result) {
        roomSearchIndex = Number(result.dataset.roomSearchResult);
        updateRoomSearchCount();
        jumpToRoomSearchResult(roomSearchIndex);
        return;
    }
    if (e.target.closest("#open-room-search-btn")) {
        toggleRoomSearchBar();
        return;
    }

    if (e.target.closest("#room-search-close")) {
        closeRoomSearchBar({ restoreFocus: true });
        return;
    }

    if (e.target.closest("#room-search-next")) {
        navigateRoomSearch(1);
        return;
    }

    if (e.target.closest("#room-search-prev")) navigateRoomSearch(-1);
});

document.addEventListener("keydown", (e) => {
    const bar = document.getElementById("room-search-bar");

    if (e.key !== "Escape" || !bar || bar.hidden) return;

    e.preventDefault();
    closeRoomSearchBar({ restoreFocus: true });
});

document.addEventListener("input", (e) => {
    if (!e.target.matches("#room-search-input")) return;

    clearTimeout(roomSearchDebounce);
    roomSearchRequestId++;
    const keyword = e.target.value.trim();

    if (!keyword) {
        roomSearchResults = [];
        roomSearchIndex = -1;
        updateRoomSearchCount();
        clearRoomSearchHighlights();
        return;
    }

    roomSearchDebounce = setTimeout(() => runRoomSearch(keyword), 300);
});

function toggleRoomSearchBar() {
    const bar = document.getElementById("room-search-bar");
    const trigger = document.getElementById("open-room-search-btn");
    if (!bar) return;

    if (bar.hidden) {
        bar.hidden = false;
        trigger?.setAttribute("aria-expanded", "true");
        if (trigger) trigger.hidden = true;
        document.getElementById("room-search-input")?.focus();
    } else {
        closeRoomSearchBar({ restoreFocus: true });
    }
}

function closeRoomSearchBar({ restoreFocus = false } = {}) {
    const bar = document.getElementById("room-search-bar");
    const trigger = document.getElementById("open-room-search-btn");

    if (bar) bar.hidden = true;
    if (trigger) {
        trigger.hidden = false;
        trigger.setAttribute("aria-expanded", "false");
    }

    const input = document.getElementById("room-search-input");
    if (input) input.value = "";

    clearTimeout(roomSearchDebounce);
    roomSearchRequestId++;
    roomSearchResults = [];
    roomSearchIndex = -1;
    updateRoomSearchCount();
    clearRoomSearchHighlights();

    if (restoreFocus) trigger?.focus();
}

// Called from chat.js whenever a new room is loaded, so an open search
// from the previous room doesn't carry stray state into the new one.
window.resetRoomSearchState = function () {
    closeRoomSearchBar();
};

async function runRoomSearch(keyword) {
    if (!window.chat.activeRoomId) return;

    const requestId = ++roomSearchRequestId;
    const roomId = window.chat.activeRoomId;
    const count = document.getElementById('room-search-count');
    if (count) count.textContent = 'Searching…';

    try {
        const response = await axios.get(
            `/chat/rooms/${roomId}/messages/search`,
            { params: { keyword } }
        );

        const bar = document.getElementById("room-search-bar");

        if (requestId !== roomSearchRequestId || roomId !== window.chat.activeRoomId || !bar || bar.hidden) {
            return;
        }

        roomSearchResults = response.data.results || [];
        // Start from the most recent match — closest to where you're reading
        roomSearchIndex = roomSearchResults.length ? roomSearchResults.length - 1 : -1;

        updateRoomSearchCount();

        if (roomSearchIndex >= 0) {
            await jumpToRoomSearchResult(roomSearchIndex);
        } else {
            clearRoomSearchHighlights();
        }
    } catch (error) {
        if (requestId === roomSearchRequestId && roomId === window.chat.activeRoomId && count) {
            count.textContent = 'Search failed. Try again.';
        }
        console.error("Room search failed:", error);
    }
}

function updateRoomSearchCount() {
    renderRoomSearchResults();
    const countEl = document.getElementById("room-search-count");
    if (!countEl) return;

    const hasKeyword = document.getElementById("room-search-input")?.value;

    if (!roomSearchResults.length) {
        countEl.textContent = hasKeyword ? "No results" : "";
        return;
    }

    countEl.textContent = `${roomSearchIndex + 1}/${roomSearchResults.length}`;
}

async function navigateRoomSearch(direction) {
    if (!roomSearchResults.length) return;

    roomSearchIndex =
        (roomSearchIndex + direction + roomSearchResults.length) % roomSearchResults.length;

    updateRoomSearchCount();
    await jumpToRoomSearchResult(roomSearchIndex);
}

function clearRoomSearchHighlights() {
    document
        .querySelectorAll(".message-item.search-match-active")
        .forEach((el) => el.classList.remove("search-match-active"));
}

/*
| The matching message might be further back than what's currently loaded
| in the DOM, so we keep paging older messages (reusing chat.js's
| loadOlderMessages) until it shows up, or we run out of history.
*/
async function jumpToRoomSearchResult(index) {
    const target = roomSearchResults[index];
    if (!target) return;

    let el = document.querySelector(`[data-message-id="${target.message_id}"]`);
    let attempts = 0;

    while (!el && window.chat.nextCursor && attempts < 30) {
        await loadOlderMessages();
        el = document.querySelector(`[data-message-id="${target.message_id}"]`);
        attempts++;
    }

    if (!el) return;

    clearRoomSearchHighlights();
    el.classList.add("search-match-active");
    el.scrollIntoView({ behavior: "smooth", block: "center" });
}

function renderRoomSearchResults() {
    const list = document.getElementById('room-search-results');
    if (!list) return;
    list.replaceChildren();
    list.hidden = roomSearchResults.length === 0;
    roomSearchResults.forEach((result, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.roomSearchResult = String(index);
        button.textContent = result.snippet || `Matching message ${index + 1}`;
        if (index === roomSearchIndex) button.setAttribute('aria-current', 'true');
        list.append(button);
    });
}

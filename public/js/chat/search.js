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
    let visibleCount = 0;

    rooms.forEach((room) => {
        const name = room.querySelector(".room-name")?.textContent.toLowerCase() || "";
        const match = !term || name.includes(term);
        room.style.display = match ? "" : "none";
        if (match) visibleCount++;
    });

    if (emptyState) {
        emptyState.style.display = term && visibleCount === 0 ? "block" : "none";
    }
}

/* =========================================================
   PART 2 — IN-CHAT: search messages inside the open room
   ========================================================= */
let roomSearchDebounce;
let roomSearchResults = [];
let roomSearchIndex = -1;

document.addEventListener("click", (e) => {
    if (e.target.closest("#open-room-search-btn")) toggleRoomSearchBar();
    if (e.target.closest("#room-search-close")) closeRoomSearchBar();
    if (e.target.closest("#room-search-next")) navigateRoomSearch(1);
    if (e.target.closest("#room-search-prev")) navigateRoomSearch(-1);
});

document.addEventListener("input", (e) => {
    if (!e.target.matches("#room-search-input")) return;

    clearTimeout(roomSearchDebounce);
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
    if (!bar) return;

    const opening = bar.style.display === "none" || !bar.style.display;

    if (opening) {
        bar.style.display = "flex";
        document.getElementById("room-search-input")?.focus();
    } else {
        closeRoomSearchBar();
    }
}

function closeRoomSearchBar() {
    const bar = document.getElementById("room-search-bar");
    if (bar) bar.style.display = "none";

    const input = document.getElementById("room-search-input");
    if (input) input.value = "";

    roomSearchResults = [];
    roomSearchIndex = -1;
    updateRoomSearchCount();
    clearRoomSearchHighlights();
}

// Called from chat.js whenever a new room is loaded, so an open search
// from the previous room doesn't carry stray state into the new one.
window.resetRoomSearchState = function () {
    roomSearchResults = [];
    roomSearchIndex = -1;
};

async function runRoomSearch(keyword) {
    if (!window.chat.activeRoomId) return;

    try {
        const response = await axios.get(
            `/chat/rooms/${window.chat.activeRoomId}/messages/search`,
            { params: { keyword } }
        );

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
        console.error("Room search failed:", error);
    }
}

function updateRoomSearchCount() {
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
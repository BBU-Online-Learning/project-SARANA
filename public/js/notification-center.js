(() => {
    "use strict";

    const script = document.currentScript;
    const feedUrl = script?.dataset.feedUrl;
    const readUrl = script?.dataset.readUrl;
    const readAllUrl = script?.dataset.readAllUrl;
    const clearAllUrl = script?.dataset.clearAllUrl;
    const readRoomUrl = script?.dataset.readRoomUrl;
    const userId = Number(script?.dataset.userId);
    if (!feedUrl || !readUrl || !readAllUrl || !clearAllUrl) return;

    let nextPage = null;
    let loading = false;
    let refreshPending = false;
    let feedVersion = 0;
    const roomRequests = new Map();
    const element = (id) => document.getElementById(`notification-center-${id}`);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";

    function count(value) {
        const badge = element("count");
        const unread = Number(value) || 0;
        badge.hidden = unread === 0;
        badge.textContent = unread > 99 ? "99+" : String(unread);
        element("toggle").setAttribute("aria-label", unread ? `Notifications, ${unread} unread` : "Notifications");
        element("read-all").disabled = unread === 0;
    }

    function row(notification) {
        const link = document.createElement("a");
        link.className = `notification-center-item${notification.read_at ? "" : " is-unread"}`;
        link.dataset.notificationId = notification.id;
        if (notification.room_id) link.dataset.roomId = String(notification.room_id);
        const url = new URL(notification.url, window.location.href);
        link.href = url.origin === window.location.origin ? url.href : "#";
        const icon = document.createElement("span");
        icon.className = "notification-center-item-icon";
        icon.setAttribute("aria-hidden", "true");
        const glyph = document.createElement("i");
        const icons = { message: "ti-message-circle", call: "ti-phone", class: "ti-school", quiz: "ti-clipboard-check", group: "ti-users" };
        glyph.className = `ti ${icons[notification.category] || "ti-bell"}`;
        icon.append(glyph);
        const copy = document.createElement("span");
        copy.className = "notification-center-item-copy";
        const title = document.createElement("strong");
        title.textContent = notification.title;
        const body = document.createElement("span");
        body.textContent = notification.body;
        const time = document.createElement("small");
        time.textContent = notification.created_at;
        copy.append(title, body, time);
        const dot = document.createElement("span");
        dot.className = "notification-center-unread-dot";
        dot.setAttribute("aria-hidden", "true");
        link.append(icon, copy, dot);
        return link;
    }

    async function load(page = 1) {
        if (loading) {
            if (page === 1) refreshPending = true;
            return;
        }
        loading = true;
        const version = feedVersion;
        try {
            const url = new URL(feedUrl, window.location.href);
            url.searchParams.set("page", String(page));
            const response = await fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } });
            if (!response.ok) throw new Error("Unable to load notifications.");
            const data = await response.json();
            if (version !== feedVersion) return;
            count(data.unread_count);
            const list = element("list");
            if (page === 1) list.replaceChildren();
            data.notifications.forEach((notification) => list.append(row(notification)));
            if (!list.children.length) {
                showEmpty();
            }
            nextPage = data.next_page;
            element("more").hidden = !nextPage;
            if (page === 1) element("clear-all").disabled = data.notifications.length === 0;
        } catch (_) {
            if (version !== feedVersion) return;
            if (!element("list").children.length) element("list").textContent = "Notifications are unavailable. Try again shortly.";
        } finally {
            loading = false;
            if (refreshPending) {
                refreshPending = false;
                load();
            }
        }
    }

    function showEmpty() {
        const empty = document.createElement("p");
        empty.className = "notification-center-empty";
        empty.textContent = "No notifications yet.";
        element("list").replaceChildren(empty);
    }

    function readRoom(roomId) {
        const id = Number(roomId);
        if (!readRoomUrl || !Number.isSafeInteger(id) || id <= 0) return Promise.resolve();
        const existing = roomRequests.get(id);
        if (existing) {
            existing.repeat = true;
            return existing.promise;
        }

        const state = { repeat: false, promise: null };
        state.promise = (async () => {
            do {
                state.repeat = false;
                const response = await fetch(readRoomUrl, {
                    method: "POST", credentials: "same-origin",
                    headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf() },
                    body: JSON.stringify({ room_id: id }),
                });
                if (!response.ok) throw new Error("Unable to mark conversation notifications as read.");
                count((await response.json()).unread_count);
                element("list").querySelectorAll(".is-unread").forEach((item) => {
                    if (item.dataset.roomId === String(id)) item.classList.remove("is-unread");
                });
            } while (state.repeat);
        })().catch(() => {}).finally(() => {
            roomRequests.delete(id);
            load();
        });
        roomRequests.set(id, state);
        return state.promise;
    }

    async function markRead(id) {
        const response = await fetch(readUrl.replace("__ID__", encodeURIComponent(id)), {
            method: "PATCH", credentials: "same-origin",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf() },
        });
        if (!response.ok) throw new Error("Unable to mark notification as read.");
        count((await response.json()).unread_count);
    }

    function initialize() {
        const toggle = element("toggle");
        const panel = element("panel");
        toggle.addEventListener("click", () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute("aria-expanded", String(!panel.hidden));
            if (!panel.hidden) load();
        });
        document.addEventListener("click", (event) => {
            if (!panel.hidden && !event.target.closest(".notification-center")) {
                panel.hidden = true;
                toggle.setAttribute("aria-expanded", "false");
            }
        });
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && !panel.hidden) {
                panel.hidden = true;
                toggle.setAttribute("aria-expanded", "false");
                toggle.focus();
            }
        });
        element("more").addEventListener("click", () => { if (nextPage) load(nextPage); });
        element("read-all").addEventListener("click", async () => {
            try {
                const response = await fetch(readAllUrl, {
                    method: "POST", credentials: "same-origin",
                    headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf() },
                });
                if (!response.ok) throw new Error("Unable to mark notifications as read.");
                count(0);
                element("list").querySelectorAll(".is-unread").forEach((item) => item.classList.remove("is-unread"));
            } catch (_) { window.AppNotifications?.error("Could not mark notifications as read."); }
        });
        element("clear-all").addEventListener("click", async () => {
            const confirmed = await window.AppConfirm?.ask?.({
                title: "Clear all notifications?",
                message: "This removes your notification history. Your messages and class activity stay available.",
                confirmLabel: "Clear all",
                tone: "danger",
            });
            if (!confirmed) return;

            const button = element("clear-all");
            button.disabled = true;
            try {
                const response = await fetch(clearAllUrl, {
                    method: "DELETE", credentials: "same-origin",
                    headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf() },
                });
                if (!response.ok) throw new Error("Unable to clear notifications.");
                feedVersion++;
                count((await response.json()).unread_count);
                nextPage = null;
                element("more").hidden = true;
                showEmpty();
                load();
            } catch (_) {
                button.disabled = false;
                window.AppNotifications?.error("Could not clear notifications. Try again.");
            }
        });
        element("list").addEventListener("click", async (event) => {
            const link = event.target.closest("[data-notification-id]");
            if (!link || link.href === "#") return;
            event.preventDefault();
            try { await markRead(link.dataset.notificationId); }
            catch (_) {}
            window.location.assign(link.href);
        });
        load();
        if (userId && window.Echo) {
            window.Echo.private(`user.${userId}`).listen(".activity.notification.changed", (event) => {
                if (event?.room_id && window.ChatRealtime?.isRoomVisible?.(event.room_id)) {
                    readRoom(event.room_id);
                    return;
                }
                load();
            });
        }
        window.addEventListener("focus", () => {
            const roomId = window.chat?.activeRoomId;
            if (window.ChatRealtime?.isRoomVisible?.(roomId)) readRoom(roomId);
            else load();
        });
        setInterval(() => { if (panel.hidden) load(); }, 15000);
    }

    window.NotificationCenter = { refresh: () => load(), readRoom };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialize, { once: true });
    else initialize();
})();

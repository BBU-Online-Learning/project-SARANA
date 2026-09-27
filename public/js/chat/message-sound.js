(() => {
    "use strict";

    const script = document.currentScript;
    const userId = Number(script?.dataset.userId);
    const soundUrl = script?.dataset.soundUrl;
    const chatUrl = script?.dataset.chatUrl;
    if (!userId || !soundUrl || !chatUrl) return;

    const player = new Audio(soundUrl);
    player.preload = "auto";
    player.volume = 1;
    let context = null;
    const playing = new Set();
    const recentIds = new Set();
    const storageKey = `chat-message-sound:${userId}`;

    function stopTone() {
        playing.forEach((oscillator) => { try { oscillator.stop(); } catch (_) {} });
        playing.clear();
        player.pause();
    }

    function note(frequency, delay, duration) {
        if (!context || context.state !== "running") return;
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        const start = context.currentTime + delay;
        oscillator.type = "sine";
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.35, start + 0.02);
        gain.gain.linearRampToValueAtTime(0, start + duration);
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.onended = () => { playing.delete(oscillator); oscillator.disconnect(); gain.disconnect(); };
        playing.add(oscillator);
        oscillator.start(start);
        oscillator.stop(start + duration + 0.01);
    }

    async function playTone(selected = "chime") {
        stopTone();
        if (selected === "classic" || !["chime", "pulse"].includes(selected)) {
            player.currentTime = 0;
            await player.play().catch(() => {});
            return;
        }

        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        context ||= new AudioContext();
        if (context.state === "suspended") await context.resume().catch(() => {});
        if (selected === "chime") {
            note(740, 0, 0.17); note(988, 0.2, 0.26);
        } else {
            note(523, 0, 0.13); note(523, 0.22, 0.13); note(659, 0.44, 0.19);
        }
    }

    function hasNotified(messageId) {
        if (recentIds.has(messageId)) return true;
        try {
            const stored = JSON.parse(localStorage.getItem(storageKey) || "[]");
            if (Array.isArray(stored) && stored.includes(messageId)) {
                recentIds.add(messageId);
                return true;
            }
            localStorage.setItem(storageKey, JSON.stringify([...(Array.isArray(stored) ? stored : []), messageId].slice(-100)));
        } catch (_) {}
        recentIds.add(messageId);
        return false;
    }

    function roomUrl(roomId) {
        const url = new URL(chatUrl, window.location.href);
        url.searchParams.set("room", String(roomId));
        return url.href;
    }

    function showInApp(sender, preview, url) {
        const region = document.getElementById("app-notifications");
        if (!region) return;

        const toast = document.createElement("div");
        toast.className = "app-toast app-toast-info is-visible";
        toast.setAttribute("role", "status");

        const icon = document.createElement("span");
        icon.className = "app-toast-icon";
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = "✉";

        const content = document.createElement("div");
        content.className = "app-toast-message";
        const link = document.createElement("a");
        link.className = "text-reset text-decoration-none";
        link.href = url;
        const name = document.createElement("strong");
        name.className = "d-block";
        name.textContent = sender;
        const message = document.createElement("span");
        message.className = "d-block";
        message.textContent = preview;
        const open = document.createElement("span");
        open.className = "d-block small text-decoration-underline";
        open.textContent = "Open conversation";
        link.append(name, message, open);
        content.append(link);

        if ("Notification" in window && Notification.permission === "default") {
            const enable = document.createElement("button");
            enable.type = "button";
            enable.className = "btn btn-sm btn-outline-primary mt-2";
            enable.textContent = "Enable desktop notifications";
            enable.addEventListener("click", async () => {
                try {
                    const permission = await Notification.requestPermission();
                    if (permission === "granted") enable.remove();
                    else if (permission === "denied") {
                        enable.remove();
                        window.AppNotifications?.warning("Desktop notifications are blocked in your browser settings.");
                    }
                } catch (_) {
                    enable.remove();
                    window.AppNotifications?.warning("Desktop notifications could not be enabled in this browser.");
                }
            });
            content.append(enable);
        }

        const close = document.createElement("button");
        close.type = "button";
        close.className = "app-toast-close";
        close.setAttribute("aria-label", "Close message notification");
        close.textContent = "×";
        close.addEventListener("click", () => toast.remove());
        toast.append(icon, content, close);
        region.prepend(toast);
        setTimeout(() => toast.remove(), 12000);
    }

    function showDesktop(messageId, sender, preview, url) {
        if (!("Notification" in window) || Notification.permission !== "granted") return;
        try {
            const notification = new Notification(sender, {
                body: preview,
                tag: `chat-message-${messageId}`,
            });
            notification.onclick = () => {
                window.focus();
                window.location.href = url;
                notification.close();
            };
        } catch (_) {}
    }

    function incoming(event) {
        const messageId = Number(event?.message_id);
        const roomId = Number(event?.room_id);
        if (!messageId || !roomId || Number(event.sender_id) === userId || hasNotified(messageId)) return;
        if (window.ChatRealtime?.isRoomVisible?.(roomId)) return;
        const sender = String(event.sender || "New message").trim() || "New message";
        const preview = String(event.body || "New message").trim().slice(0, 140) || "New message";
        const url = roomUrl(roomId);
        if (window.appPreferences?.message_sound !== false) {
            playTone(window.appPreferences?.message_tone).catch(() => {});
        }
        if (window.appPreferences?.message_popups !== false) showInApp(sender, preview, url);
        if (window.appPreferences?.desktop_messages !== false) showDesktop(messageId, sender, preview, url);
        window.NotificationCenter?.refresh();
    }

    function initialize() {
        window.Echo?.private(`user.${userId}`).listen(".sidebar.updated", incoming);
    }

    window.MessageSounds = { preview: playTone };

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialize, { once: true });
    else initialize();
})();

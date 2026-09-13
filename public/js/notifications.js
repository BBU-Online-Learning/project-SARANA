(() => {
    "use strict";

    const TYPES = {
        success: { icon: "✓", duration: 4000 },
        error: { icon: "✕", duration: 6000 },
        warning: { icon: "⚠", duration: 5000 },
        info: { icon: "ⓘ", duration: 4000 },
    };
    const MAX_VISIBLE = 3;
    const queued = [];
    const visible = new Map();
    const recentlyShown = new Map();

    function normalize(input, message, options = {}) {
        const notification = typeof input === "object" && input !== null
            ? input
            : { ...options, type: input, message };
        const type = TYPES[notification.type] ? notification.type : "info";
        const text = String(notification.message ?? "").trim();
        const configuredDuration = Number(notification.duration);

        return {
            type,
            message: text,
            duration: Number.isFinite(configuredDuration) && configuredDuration >= 0
                ? configuredDuration
                : TYPES[type].duration,
            key: String(notification.id ?? `${type}:${text}`),
        };
    }

    function region() {
        return document.getElementById("app-notifications");
    }

    function markRecent(notification) {
        const existingTimer = recentlyShown.get(notification.key);
        if (existingTimer) {
            clearTimeout(existingTimer);
        }
        recentlyShown.set(notification.key, setTimeout(
            () => recentlyShown.delete(notification.key),
            Math.max(notification.duration, 3000),
        ));
    }

    function drainQueue() {
        if (!region()) {
            return;
        }

        while (visible.size < MAX_VISIBLE && queued.length > 0) {
            render(queued.shift());
        }
    }

    function render(notification) {
        const container = region();
        if (!container) {
            queued.push(notification);
            return;
        }

        const toast = document.createElement("div");
        toast.className = `app-toast app-toast-${notification.type}`;
        toast.dataset.notificationKey = notification.key;
        toast.setAttribute("role", notification.type === "error" ? "alert" : "status");
        toast.setAttribute("aria-live", notification.type === "error" ? "assertive" : "polite");
        toast.setAttribute("aria-atomic", "true");

        const icon = document.createElement("span");
        icon.className = "app-toast-icon";
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = TYPES[notification.type].icon;

        const text = document.createElement("span");
        text.className = "app-toast-message";
        text.textContent = notification.message;

        const close = document.createElement("button");
        close.type = "button";
        close.className = "app-toast-close";
        close.setAttribute("aria-label", "Close notification");
        close.textContent = "×";

        toast.append(icon, text, close);
        container.appendChild(toast);

        const state = {
            notification,
            remaining: notification.duration,
            startedAt: Date.now(),
            timer: null,
            closing: false,
        };
        visible.set(notification.key, state);
        markRecent(notification);

        const closeToast = () => {
            if (state.closing) {
                return;
            }
            state.closing = true;
            clearTimeout(state.timer);
            toast.classList.add("is-leaving");
            setTimeout(() => {
                toast.remove();
                visible.delete(notification.key);
                drainQueue();
            }, 220);
        };
        const pause = () => {
            if (!state.timer || state.closing) {
                return;
            }
            clearTimeout(state.timer);
            state.timer = null;
            state.remaining = Math.max(0, state.remaining - (Date.now() - state.startedAt));
        };
        const resume = () => {
            if (state.timer || state.closing || notification.duration === 0) {
                return;
            }
            state.startedAt = Date.now();
            state.timer = setTimeout(closeToast, state.remaining);
        };

        close.addEventListener("click", closeToast);
        toast.addEventListener("mouseenter", pause);
        toast.addEventListener("mouseleave", resume);
        toast.addEventListener("focusin", pause);
        toast.addEventListener("focusout", resume);
        requestAnimationFrame(() => toast.classList.add("is-visible"));
        resume();
    }

    function show(input, message, options) {
        const notification = normalize(input, message, options);
        if (!notification.message || visible.has(notification.key) || recentlyShown.has(notification.key)
            || queued.some((item) => item.key === notification.key)) {
            return false;
        }
        if (visible.size >= MAX_VISIBLE || !region()) {
            queued.push(notification);
            return true;
        }
        render(notification);

        return true;
    }

    function fromAxios(error, fallback = "Something went wrong. Please try again.") {
        const status = error?.response?.status;
        const serverMessage = error?.response?.data?.message;
        const message = status === 403
            ? "You are not authorized to perform this action."
            : status === 419
                ? "Your session expired. Refresh the page and try again."
                : status === 422
                    ? "Please check the highlighted fields."
                    : (typeof serverMessage === "string" && status < 500 ? serverMessage : fallback);

        return show("error", message);
    }

    window.AppNotifications = {
        show,
        success: (message, options) => show("success", message, options),
        error: (message, options) => show("error", message, options),
        warning: (message, options) => show("warning", message, options),
        info: (message, options) => show("info", message, options),
        fromAxios,
    };

    function initialize() {
        document.querySelectorAll("[data-notification-seed]").forEach((seed) => {
            show({ type: seed.dataset.type, message: seed.textContent });
        });
        drainQueue();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
})();

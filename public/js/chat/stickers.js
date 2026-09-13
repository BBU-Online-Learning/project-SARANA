(() => {
    function elements() {
        return {
            button: document.getElementById("sticker-picker-btn"),
            picker: document.getElementById("sticker-picker"),
        };
    }

    function isOpen() {
        const { picker } = elements();

        return Boolean(picker && !picker.hidden);
    }

    function close({ restoreFocus = false } = {}) {
        const { button, picker } = elements();
        if (!picker) return;

        picker.hidden = true;
        button?.classList.remove("is-active");
        button?.setAttribute("aria-expanded", "false");

        if (restoreFocus) button?.focus();
    }

    function open() {
        const { button, picker } = elements();
        if (!button || !picker) return;

        if (window.chat.voiceDraftFile || !document.getElementById("voice-overlay")?.hidden) {
            window.AppNotifications?.warning("Send or cancel your voice message before choosing a sticker.");
            return;
        }

        if (window.ChatAttachments?.hasPending?.()) {
            window.AppNotifications?.warning("Send or remove your attachments before choosing a sticker.");
            return;
        }

        document.querySelectorAll(".reaction-picker").forEach((reactionPicker) => {
            reactionPicker.style.display = "none";
        });

        picker.hidden = false;
        button.classList.add("is-active");
        button.setAttribute("aria-expanded", "true");
        picker.querySelector(".sticker-option")?.focus();
    }

    document.addEventListener("click", async (event) => {
        if (event.target.closest("#sticker-picker-btn")) {
            isOpen() ? close() : open();
            return;
        }

        if (event.target.closest("#sticker-picker-close")) {
            close({ restoreFocus: true });
            return;
        }

        const option = event.target.closest(".sticker-option");
        if (option) {
            const stickerId = option.dataset.stickerId;
            close();
            await window.ChatMessages?.sendSticker?.(stickerId);
            return;
        }

        const { picker } = elements();
        if (isOpen() && !picker?.contains(event.target)) close();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape" || !isOpen()) return;

        event.preventDefault();
        close({ restoreFocus: true });
    });

    window.StickerPicker = { close, open };
})();

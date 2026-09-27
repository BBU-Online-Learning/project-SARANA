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

    function syncCatalog(picker) {
        if (!picker || !window.chat) return;

        const stickers = Array.from(picker.querySelectorAll(".sticker-option[data-sticker-id]"))
            .map((option) => ({
                id: option.dataset.stickerId,
                name: option.dataset.stickerName,
                pack: option.dataset.stickerPack,
                url: option.dataset.stickerUrl,
            }))
            .filter((sticker) => sticker.id && sticker.name && sticker.url);

        if (stickers.length) window.chat.stickers = stickers;
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

        syncCatalog(picker);
        picker.hidden = false;
        button.classList.add("is-active");
        button.setAttribute("aria-expanded", "true");
        picker.querySelector('.sticker-picker-grid:not([hidden]) .sticker-option')?.focus();
    }

    function selectPack(tab) {
        const picker = tab.closest("#sticker-picker");
        const packId = tab.dataset.stickerPackTab;
        if (!picker || !packId) return;

        picker.querySelectorAll("[data-sticker-pack-tab]").forEach((packTab) => {
            const isSelected = packTab === tab;
            packTab.setAttribute("aria-selected", String(isSelected));
            packTab.setAttribute("tabindex", isSelected ? "0" : "-1");
        });
        picker.querySelectorAll("[data-sticker-pack-panel]").forEach((panel) => {
            panel.hidden = panel.dataset.stickerPackPanel !== packId;
        });
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

        const packTab = event.target.closest("[data-sticker-pack-tab]");
        if (packTab) {
            selectPack(packTab);
            return;
        }

        const option = event.target.closest(".sticker-option");
        if (option) {
            const stickerId = option.dataset.stickerId;
            syncCatalog(option.closest("#sticker-picker"));
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

    window.StickerPicker = { close, open, selectPack };
})();

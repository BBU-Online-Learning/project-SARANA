(() => {
    "use strict";

    function renderPermission() {
        const button = document.getElementById("settings-browser-permission");
        const status = document.getElementById("settings-browser-permission-status");
        if (!button || !status) return;

        if (!("Notification" in window)) {
            button.hidden = true;
            status.textContent = "Browser notifications are unavailable here.";
        } else if (Notification.permission === "granted") {
            button.hidden = true;
            status.textContent = "Browser notifications are enabled.";
        } else if (Notification.permission === "denied") {
            button.hidden = true;
            status.textContent = "Browser notifications are blocked. Enable them in your browser settings.";
        } else {
            button.hidden = false;
            status.textContent = "Your browser will ask for permission.";
        }
    }

    document.addEventListener("click", async (event) => {
        const previewButton = event.target.closest?.("[data-preview-sound]");
        if (previewButton) {
            const tone = document.getElementById(previewButton.dataset.toneSelect)?.value;
            if (previewButton.dataset.previewSound === "message") {
                window.MessageSounds?.preview(tone);
            } else if (previewButton.dataset.previewSound === "call") {
                window.CallSounds?.preview(tone);
            }
            return;
        }

        if (!event.target.closest?.("#settings-browser-permission") || !("Notification" in window)) return;
        try {
            await Notification.requestPermission();
            renderPermission();
        } catch (_) {
            document.getElementById("settings-browser-permission-status").textContent =
                "Could not request browser permission. Check your browser settings.";
        }
    });

    window.SettingsPage = { refresh: renderPermission };
    renderPermission();
})();

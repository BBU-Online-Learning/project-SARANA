(() => {
    "use strict";

    const dialog = document.getElementById("app-confirm-dialog");

    if (!dialog || typeof dialog.showModal !== "function") {
        window.AppConfirm = {
            ask: async () => {
                window.AppNotifications?.warning("Confirmation is unavailable in this browser.");
                return false;
            },
        };
        return;
    }

    const title = dialog.querySelector("#app-confirm-title");
    const message = dialog.querySelector("#app-confirm-message");
    const accept = dialog.querySelector("[data-confirm-accept]");
    const icon = dialog.querySelector("[data-confirm-icon]");
    let resolveRequest = null;

    function finish(confirmed) {
        if (!resolveRequest) return;

        const resolve = resolveRequest;
        resolveRequest = null;
        resolve(confirmed);
    }

    function applyTone(tone) {
        const isDanger = tone === "danger";

        dialog.dataset.tone = isDanger ? "danger" : "primary";
        accept.className = `btn ${isDanger ? "btn-danger" : "btn-primary"}`;
        icon.innerHTML = `<i class="ti ${isDanger ? "ti-alert-triangle" : "ti-help-circle"}"></i>`;
    }

    function ask({
        title: heading = "Confirm action",
        message: body = "Are you sure?",
        confirmLabel = "Confirm",
        tone = "danger",
    } = {}) {
        if (resolveRequest) {
            finish(false);
            if (dialog.open) dialog.close("cancel");
        }

        title.textContent = heading;
        message.textContent = body;
        accept.textContent = confirmLabel;
        applyTone(tone);
        dialog.returnValue = "";
        dialog.showModal();

        return new Promise((resolve) => {
            resolveRequest = resolve;
        });
    }

    dialog.addEventListener("close", () => {
        finish(dialog.returnValue === "confirm");
    });

    dialog.addEventListener("cancel", () => {
        finish(false);
    });

    dialog.addEventListener("click", (event) => {
        if (event.target === dialog) {
            dialog.close("cancel");
        }
    });

    document.addEventListener("submit", async (event) => {
        const form = event.target.closest("form[data-confirm-message]");

        if (!form || form.dataset.confirmed === "true") return;

        event.preventDefault();
        const confirmed = await ask({
            title: form.dataset.confirmTitle,
            message: form.dataset.confirmMessage,
            confirmLabel: form.dataset.confirmLabel,
            tone: form.dataset.confirmTone,
        });

        if (!confirmed) return;

        form.dataset.confirmed = "true";
        form.requestSubmit(event.submitter || undefined);
        setTimeout(() => delete form.dataset.confirmed, 0);
    });

    window.AppConfirm = { ask };
})();

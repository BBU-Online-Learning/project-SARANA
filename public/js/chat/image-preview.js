(() => {
    "use strict";

    const modal = document.getElementById("image-preview-modal");
    const image = document.getElementById("image-preview-modal-img");
    const caption = document.getElementById("image-preview-caption");
    const closeButton = document.getElementById("image-preview-close");
    const download = document.getElementById("image-preview-download");
    const status = document.getElementById("image-preview-status");

    if (!modal || !image) {
        return;
    }

    let currentImage = null;
    let previouslyFocused = null;

    function preload(src) {
        return new Promise((resolve, reject) => {
            const loader = new Image();

            loader.onload = () => resolve();

            loader.onerror = reject;

            loader.src = src;
        });
    }

    async function open(src, filename = "", downloadSrc = src) {
        if (!src) {
            return;
        }

        previouslyFocused = document.activeElement;
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("image-preview-open");
        if (status) status.hidden = false;
        image.hidden = true;
        modal.focus();

        try {
            await preload(src);

            currentImage = src;

            image.src = src;

            image.alt = filename;
            image.hidden = false;
            if (status) status.hidden = true;

            if (caption) {
                caption.textContent = filename;
            }

            if (download) {
                download.href = downloadSrc || src;
                download.setAttribute("aria-label", `Download ${filename || "image"}`);
            }
        } catch (error) {
            console.error("Unable to preview image.", error);
            close();
            window.AppNotifications?.error?.("The image preview could not be loaded. You can try downloading the file instead.");
        }
    }

    function close() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");

        image.removeAttribute("src");

        image.removeAttribute("alt");

        currentImage = null;
        if (status) status.hidden = true;
        if (download) download.removeAttribute("href");

        if (caption) {
            caption.textContent = "";
        }

        document.body.classList.remove("image-preview-open");
        previouslyFocused?.focus?.();
        previouslyFocused = null;
    }

    document.addEventListener("click", (event) => {
        const thumb = event.target.closest(".message-attachment-thumb");

        if (!thumb) {
            return;
        }

        open(thumb.dataset.fullSrc, thumb.dataset.filename, thumb.dataset.downloadSrc);
    });

    document.addEventListener("keydown", (event) => {
        const thumb = event.target.closest?.(".message-attachment-thumb");
        if (thumb && (event.key === "Enter" || event.key === " ")) {
            event.preventDefault();
            open(thumb.dataset.fullSrc, thumb.dataset.filename, thumb.dataset.downloadSrc);
            return;
        }

        if (!modal.classList.contains("show") || event.key !== "Tab") return;
        const controls = [closeButton, download].filter((element) => element && !element.hidden);
        if (controls.length === 0) return;
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    closeButton?.addEventListener("click", close);

    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            close();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal.classList.contains("show")) {
            close();
        }
    });

    window.ChatImagePreview = {
        open,

        close,

        isOpen() {
            return modal.classList.contains("show");
        },

        current() {
            return currentImage;
        },
    };
})();

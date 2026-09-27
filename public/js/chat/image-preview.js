(() => {
    "use strict";

    const modal = document.getElementById("image-preview-modal");
    const image = document.getElementById("image-preview-modal-img");
    const caption = document.getElementById("image-preview-caption");
    const closeButton = document.getElementById("image-preview-close");
    const previousButton = document.getElementById("image-preview-previous");
    const nextButton = document.getElementById("image-preview-next");
    const counter = document.getElementById("image-preview-counter");
    const download = document.getElementById("image-preview-download");
    const status = document.getElementById("image-preview-status");

    if (!modal || !image) {
        return;
    }

    let currentImage = null;
    let previouslyFocused = null;
    let gallery = [];
    let currentIndex = -1;
    let loadToken = 0;

    function preload(src) {
        return new Promise((resolve, reject) => {
            const loader = new Image();

            loader.onload = () => resolve();

            loader.onerror = reject;

            loader.src = src;
        });
    }

    function updateNavigation() {
        const hasMultiplePhotos = gallery.length > 1;
        if (previousButton) {
            previousButton.hidden = !hasMultiplePhotos;
            previousButton.disabled = currentIndex <= 0;
        }
        if (nextButton) {
            nextButton.hidden = !hasMultiplePhotos;
            nextButton.disabled = currentIndex >= gallery.length - 1;
        }
        if (counter) counter.textContent = hasMultiplePhotos ? `${currentIndex + 1} of ${gallery.length}` : "";
    }

    async function showImage(src, filename, downloadSrc) {
        const requestToken = ++loadToken;
        if (status) status.hidden = false;
        image.hidden = true;
        if (download) download.removeAttribute("href");

        try {
            await preload(src);
            if (requestToken !== loadToken || !modal.classList.contains("show")) return;

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
            if (requestToken !== loadToken) return;
            console.error("Unable to preview image.", error);
            close();
            window.AppNotifications?.error?.("The image preview could not be loaded. You can try downloading the file instead.");
        }
    }

    function navigate(direction) {
        const nextIndex = currentIndex + direction;
        if (nextIndex < 0 || nextIndex >= gallery.length) return;
        currentIndex = nextIndex;
        updateNavigation();
        const thumb = gallery[currentIndex];
        showImage(thumb.dataset.fullSrc, thumb.dataset.filename || "", thumb.dataset.downloadSrc || thumb.dataset.fullSrc);
    }

    function open(src, filename = "", downloadSrc = src, thumb = null) {
        if (!src) return;

        gallery = thumb ? [...document.querySelectorAll("#chat-room-container .messages-container .message-attachment-thumb")] : [];
        currentIndex = gallery.indexOf(thumb);
        if (currentIndex < 0) gallery = [];
        previouslyFocused = document.activeElement;
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("image-preview-open");
        updateNavigation();
        modal.focus();
        showImage(src, filename, downloadSrc);
    }

    function close() {
        loadToken++;
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");

        image.removeAttribute("src");

        image.removeAttribute("alt");

        currentImage = null;
        gallery = [];
        currentIndex = -1;
        updateNavigation();
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

        open(thumb.dataset.fullSrc, thumb.dataset.filename, thumb.dataset.downloadSrc, thumb);
    });

    document.addEventListener("keydown", (event) => {
        const thumb = event.target.closest?.(".message-attachment-thumb");
        if (thumb && (event.key === "Enter" || event.key === " ")) {
            event.preventDefault();
            open(thumb.dataset.fullSrc, thumb.dataset.filename, thumb.dataset.downloadSrc, thumb);
            return;
        }

        if (!modal.classList.contains("show")) return;
        if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
            event.preventDefault();
            navigate(event.key === "ArrowLeft" ? -1 : 1);
            return;
        }
        if (event.key !== "Tab") return;
        const controls = [closeButton, previousButton, nextButton, download]
            .filter((element) => element && !element.hidden && !element.disabled);
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
    previousButton?.addEventListener("click", () => navigate(-1));
    nextButton?.addEventListener("click", () => navigate(1));

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

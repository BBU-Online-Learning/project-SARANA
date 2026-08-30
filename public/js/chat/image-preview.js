(() => {
    "use strict";

    const modal = document.getElementById("image-preview-modal");
    const image = document.getElementById("image-preview-modal-img");
    const caption = document.getElementById("image-preview-caption");
    const closeButton = document.getElementById("image-preview-close");

    if (!modal || !image) {
        return;
    }

    let currentImage = null;

    function preload(src) {
        return new Promise((resolve, reject) => {
            const loader = new Image();

            loader.onload = () => resolve();

            loader.onerror = reject;

            loader.src = src;
        });
    }

    async function open(src, filename = "") {
        if (!src) {
            return;
        }

        try {
            await preload(src);

            currentImage = src;

            image.src = src;

            image.alt = filename;

            if (caption) {
                caption.textContent = filename;
            }

            modal.classList.add("show");

            document.body.classList.add("image-preview-open");
        } catch (error) {
            console.error("Unable to preview image.", error);
        }
    }

    function close() {
        modal.classList.remove("show");

        image.removeAttribute("src");

        image.removeAttribute("alt");

        currentImage = null;

        if (caption) {
            caption.textContent = "";
        }

        document.body.classList.remove("image-preview-open");
    }

    document.addEventListener("click", (event) => {
        const thumb = event.target.closest(".message-attachment-thumb");

        if (!thumb) {
            return;
        }

        open(thumb.dataset.fullSrc, thumb.dataset.filename);
    });

    closeButton?.addEventListener("click", close);

    modal.addEventListener("click", (event) => {
        if (event.target === modal) {
            close();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
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

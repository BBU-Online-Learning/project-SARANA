(() => {
    "use strict";

    window.chat = window.chat || {};
    window.chat.attachments = window.chat.attachments || { pendingFiles: [], objectUrls: new Map() };

    const state = window.chat.attachments;
    const suppliedConfig = window.chatAttachmentConfig || {};
    const config = {
        maxFiles: Number(suppliedConfig.maxFiles || 10),
        maxFileSizeBytes: Number(suppliedConfig.maxFileSizeBytes || 20 * 1024 * 1024),
        maxTotalSizeBytes: Number(suppliedConfig.maxTotalSizeBytes || 50 * 1024 * 1024),
        allowedExtensions: Array.isArray(suppliedConfig.allowedExtensions)
            ? suppliedConfig.allowedExtensions.map((value) => String(value).toLowerCase())
            : [],
    };
    let dragDepth = 0;

    const input = () => document.getElementById("attachment-input");
    const previewBar = () => document.getElementById("attachment-preview");
    const previewList = () => document.getElementById("attachment-preview-list");
    const limitSummary = () => document.getElementById("attachment-limit-summary");
    const dropOverlay = () => document.querySelector("[data-attachment-drop-overlay]");

    function extension(file) {
        const parts = String(file?.name || "").split(".");
        return parts.length > 1 ? parts.pop().toLowerCase() : "";
    }

    function formatBytes(bytes) {
        if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(bytes >= 10485760 ? 0 : 1)} MB`;
        if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
        return `${bytes} B`;
    }

    function totalBytes() {
        return state.pendingFiles.reduce((total, file) => total + Number(file.size || 0), 0);
    }

    function fileSignature(files = state.pendingFiles) {
        return Array.from(files).map((file) => `${file.name}:${file.size}:${file.lastModified}`).join("|");
    }

    function isDuplicate(file) {
        return state.pendingFiles.some((existing) => existing.name === file.name
            && existing.size === file.size && existing.lastModified === file.lastModified);
    }

    function validationError(file) {
        if (!config.allowedExtensions.includes(extension(file))) {
            return `“${file.name}” is not a supported file type.`;
        }
        if (Number(file.size || 0) > config.maxFileSizeBytes) {
            return `“${file.name}” exceeds the ${formatBytes(config.maxFileSizeBytes)} file limit.`;
        }
        if (state.pendingFiles.length >= config.maxFiles) {
            return `You can attach up to ${config.maxFiles} files.`;
        }
        if (totalBytes() + Number(file.size || 0) > config.maxTotalSizeBytes) {
            return `Attachments can total up to ${formatBytes(config.maxTotalSizeBytes)} per message.`;
        }
        return null;
    }

    function addFiles(files) {
        let added = 0;
        const errors = [];

        Array.from(files || []).forEach((file) => {
            if (isDuplicate(file)) return;
            const error = validationError(file);
            if (error) {
                errors.push(error);
                return;
            }
            state.pendingFiles.push(file);
            added += 1;
        });

        render();
        if (errors.length > 0) {
            const remaining = errors.length > 1 ? ` ${errors.length - 1} more file(s) were skipped.` : "";
            window.AppNotifications?.warning(errors[0] + remaining);
        }
        return added;
    }

    function objectUrl(file) {
        if (!state.objectUrls.has(file)) state.objectUrls.set(file, URL.createObjectURL(file));
        return state.objectUrls.get(file);
    }

    function revokeObjectUrl(file) {
        const url = state.objectUrls.get(file);
        if (!url) return;
        URL.revokeObjectURL(url);
        state.objectUrls.delete(file);
    }

    function clearObjectUrls() {
        state.objectUrls.forEach((url) => URL.revokeObjectURL(url));
        state.objectUrls.clear();
    }

    function removeFile(index) {
        const file = state.pendingFiles[index];
        if (!file) return;
        revokeObjectUrl(file);
        state.pendingFiles.splice(index, 1);
        render();
    }

    function clearFiles() {
        clearObjectUrls();
        state.pendingFiles.length = 0;
        render();
    }

    function createChip(file, index) {
        const chip = document.createElement("div");
        chip.className = "attachment-chip";

        if (String(file.type).startsWith("image/")) {
            const image = document.createElement("img");
            image.className = "attachment-chip-image";
            image.src = objectUrl(file);
            image.alt = "";
            chip.appendChild(image);
        } else {
            const icon = document.createElement("span");
            icon.className = "attachment-chip-icon";
            const iconClass = String(file.type).startsWith("audio/")
                ? "ti ti-music"
                : (String(file.type).startsWith("video/") ? "ti ti-video" : null);
            icon.innerHTML = iconClass ? `<i class="${iconClass}" aria-hidden="true"></i>` : extension(file).toUpperCase();
            chip.appendChild(icon);
        }

        const details = document.createElement("span");
        details.className = "attachment-chip-details";
        const name = document.createElement("span");
        name.className = "attachment-chip-name";
        name.textContent = file.name;
        const size = document.createElement("span");
        size.className = "attachment-chip-size";
        size.textContent = formatBytes(Number(file.size || 0));
        details.append(name, size);
        chip.appendChild(details);

        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "attachment-chip-remove";
        remove.dataset.index = index;
        remove.setAttribute("aria-label", `Remove ${file.name}`);
        remove.innerHTML = '<i class="ti ti-x" aria-hidden="true"></i>';
        chip.appendChild(remove);
        return chip;
    }

    function render() {
        const bar = previewBar();
        const list = previewList();
        if (!bar || !list) return;

        list.replaceChildren();
        if (state.pendingFiles.length === 0) {
            bar.style.display = "none";
            return;
        }

        bar.style.display = "flex";
        state.pendingFiles.forEach((file, index) => list.appendChild(createChip(file, index)));
        const summary = limitSummary();
        if (summary) {
            summary.textContent = `${state.pendingFiles.length}/${config.maxFiles} files · ${formatBytes(totalBytes())}/${formatBytes(config.maxTotalSizeBytes)}`;
        }
    }

    function containsFiles(event) {
        return Array.from(event.dataTransfer?.types || []).includes("Files");
    }

    function setDropOverlay(visible) {
        const overlay = dropOverlay();
        if (!overlay) return;
        overlay.hidden = !visible;
        overlay.setAttribute("aria-hidden", visible ? "false" : "true");
    }

    document.addEventListener("click", (event) => {
        if (event.target.closest("#attach-file-btn")) {
            window.StickerPicker?.close?.();
            input()?.click();
            return;
        }
        const removeButton = event.target.closest(".attachment-chip-remove");
        if (removeButton) {
            removeFile(Number(removeButton.dataset.index));
            return;
        }
        if (event.target.closest("#cancel-attachments-btn")) clearFiles();
    });

    document.addEventListener("change", (event) => {
        if (!event.target.matches("#attachment-input")) return;
        addFiles(event.target.files);
        event.target.value = "";
    });

    document.addEventListener("dragenter", (event) => {
        if (!window.chat.activeRoomId || !containsFiles(event)) return;
        event.preventDefault();
        dragDepth += 1;
        setDropOverlay(true);
    });
    document.addEventListener("dragover", (event) => {
        if (!window.chat.activeRoomId || !containsFiles(event)) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
    });
    document.addEventListener("dragleave", (event) => {
        if (!containsFiles(event)) return;
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) setDropOverlay(false);
    });
    document.addEventListener("drop", (event) => {
        if (!window.chat.activeRoomId || !containsFiles(event)) return;
        event.preventDefault();
        dragDepth = 0;
        setDropOverlay(false);
        addFiles(event.dataTransfer?.files);
    });
    document.addEventListener("paste", (event) => {
        if (!window.chat.activeRoomId) return;
        const files = Array.from(event.clipboardData?.files || []);
        if (files.length > 0 && addFiles(files) > 0) event.preventDefault();
    });

    window.addEventListener("beforeunload", clearObjectUrls);
    window.ChatAttachments = {
        hasPending: () => state.pendingFiles.length > 0,
        count: () => state.pendingFiles.length,
        primaryFile: () => state.pendingFiles[0] ?? null,
        files: () => [...state.pendingFiles],
        totalBytes,
        signature: () => fileSignature(),
        clearIfSignature(signature) {
            if (fileSignature() === signature) clearFiles();
        },
        addFiles,
        clear: clearFiles,
        remove: removeFile,
        render,
        buildFormData(body, clientUuid, replyToMessageId = null) {
            const formData = new FormData();
            formData.append("body", body || "");
            if (clientUuid) formData.append("client_uuid", clientUuid);
            if (replyToMessageId) formData.append("reply_to_message_id", replyToMessageId);
            state.pendingFiles.forEach((file) => formData.append("attachments[]", file));
            return formData;
        },
    };
})();

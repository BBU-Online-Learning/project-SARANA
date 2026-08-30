// public/js/chat/attachments.js

(() => {
    "use strict";

    /*
    |--------------------------------------------------------------------------
    | CHAT STATE
    |--------------------------------------------------------------------------
    */

    window.chat = window.chat || {};

    window.chat.attachments = window.chat.attachments || {
        pendingFiles: [],
        objectUrls: new Map(),
    };

    const state = window.chat.attachments;

    /*
    |--------------------------------------------------------------------------
    | CONFIG
    |--------------------------------------------------------------------------
    */

    const CONFIG = {
        maxFiles: 10,

        allowedExtensions: [
            "jpg",
            "jpeg",
            "png",
            "gif",
            "webp",
            "pdf",
            "doc",
            "docx",
            "xls",
            "xlsx",
            "zip",
            "ogg",
            "oga",
            "webm",
            "mp3",
            "wav",
            "m4a",
            "aac",
            "mpeg",
            "mpga",
            "mp4",
        ],
    };
    function isAudio(file) {
        return file.type.startsWith("audio/");
    }
    /*
    |--------------------------------------------------------------------------
    | DOM HELPERS
    |--------------------------------------------------------------------------
    */

    function getInput() {
        return document.getElementById("attachment-input");
    }

    function getPreviewBar() {
        return document.getElementById("attachment-preview");
    }

    function getPreviewList() {
        return document.getElementById("attachment-preview-list");
    }

    /*
    |--------------------------------------------------------------------------
    | FILE HELPERS
    |--------------------------------------------------------------------------
    */

    function extension(file) {
        return file.name.split(".").pop().toLowerCase();
    }

    function isAllowed(file) {
        return CONFIG.allowedExtensions.includes(extension(file));
    }

    function isDuplicate(file) {
        return state.pendingFiles.some((existing) => {
            return (
                existing.name === file.name &&
                existing.size === file.size &&
                existing.lastModified === file.lastModified
            );
        });
    }

    function addFile(file) {
        if (state.pendingFiles.length >= CONFIG.maxFiles) {
            alert(`You can attach up to ${CONFIG.maxFiles} files.`);
            return false;
        }

        if (!isAllowed(file)) {
            alert(`"${file.name}" is not a supported file type.`);
            return false;
        }

        if (isDuplicate(file)) {
            return false;
        }

        state.pendingFiles.push(file);
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | FILE PICKER
    |--------------------------------------------------------------------------
    */

    document.addEventListener("click", (event) => {
        if (!event.target.closest("#attach-file-btn")) {
            return;
        }

        getInput()?.click();
    });

    /*
    |--------------------------------------------------------------------------
    | INPUT CHANGE
    |--------------------------------------------------------------------------
    */

    document.addEventListener("change", (event) => {
        if (!event.target.matches("#attachment-input")) {
            return;
        }

        const files = Array.from(event.target.files || []);

        /*
        | Allows selecting the same file again later.
        */
        event.target.value = "";

        files.forEach(addFile);

        /*
        | Part 2 will implement this.
        */
        renderAttachmentPreviews();
    });

    /*
    |--------------------------------------------------------------------------
    | PLACEHOLDER
    |--------------------------------------------------------------------------
    |
    | Implemented in Part 2.
    |
    */

    /*
|--------------------------------------------------------------------------
| OBJECT URL HELPERS
|--------------------------------------------------------------------------
*/

    function getObjectUrl(file) {
        if (state.objectUrls.has(file)) {
            return state.objectUrls.get(file);
        }

        const url = URL.createObjectURL(file);

        state.objectUrls.set(file, url);

        return url;
    }

    function revokeObjectUrl(file) {
        if (!state.objectUrls.has(file)) {
            return;
        }

        URL.revokeObjectURL(state.objectUrls.get(file));

        state.objectUrls.delete(file);
    }

    function revokeAllObjectUrls() {
        state.objectUrls.forEach((url) => {
            URL.revokeObjectURL(url);
        });

        state.objectUrls.clear();
    }

    /*
|--------------------------------------------------------------------------
| FILE HELPERS
|--------------------------------------------------------------------------
*/

    function isImage(file) {
        return file.type.startsWith("image/");
    }

    function fileExtension(file) {
        return (file.name.split(".").pop() || "FILE").toUpperCase();
    }

    /*
|--------------------------------------------------------------------------
| REMOVE
|--------------------------------------------------------------------------
*/

    function removeFile(index) {
        const file = state.pendingFiles[index];

        if (!file) {
            return;
        }

        revokeObjectUrl(file);

        state.pendingFiles.splice(index, 1);

        renderAttachmentPreviews();
    }

    function clearFiles() {
        revokeAllObjectUrls();

        state.pendingFiles.length = 0;

        renderAttachmentPreviews();
    }

    /*
|--------------------------------------------------------------------------
| PREVIEW RENDERING
|--------------------------------------------------------------------------
*/

    function renderAttachmentPreviews() {
        const bar = getPreviewBar();
        const list = getPreviewList();

        if (!bar || !list) {
            return;
        }

        list.innerHTML = "";

        if (state.pendingFiles.length === 0) {
            bar.style.display = "none";

            return;
        }

        bar.style.display = "flex";

        state.pendingFiles.forEach((file, index) => {
            const chip = document.createElement("div");
            chip.className = "attachment-chip";

            if (isAudio(file)) {
                const icon = document.createElement("span");
                icon.className = "attachment-chip-icon";
                icon.innerHTML = '<i class="ti ti-microphone"></i>';
                chip.appendChild(icon);

                const name = document.createElement("span");
                name.className = "attachment-chip-name";
                name.textContent = file.name;
                chip.appendChild(name);
            } else if (isImage(file)) {
                const image = document.createElement("img");
                image.className = "attachment-chip-image";
                image.src = getObjectUrl(file);
                image.alt = file.name;
                chip.appendChild(image);
            } else {
                const icon = document.createElement("span");
                icon.className = "attachment-chip-icon";
                icon.textContent = fileExtension(file);
                chip.appendChild(icon);
            }
            /*
        |--------------------------------------------------------------------------
        | FILE NAME
        |--------------------------------------------------------------------------
        */

            // const name = document.createElement("span");
            // name.className = "attachment-chip-name";
            // name.textContent = file.name;
            // chip.appendChild(name);

            /*
        |--------------------------------------------------------------------------
        | REMOVE BUTTON
        |--------------------------------------------------------------------------
        */

            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "attachment-chip-remove";
            remove.dataset.index = index;
            remove.innerHTML = '<i class="ti ti-x"></i>';
            chip.appendChild(remove);
            list.appendChild(chip);
        });
    }

    /*
|--------------------------------------------------------------------------
| EVENT DELEGATION
|--------------------------------------------------------------------------
*/

    document.addEventListener("click", (event) => {
        const removeButton = event.target.closest(".attachment-chip-remove");

        if (!removeButton) {
            return;
        }

        removeFile(Number(removeButton.dataset.index));
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest("#cancel-attachments-btn")) {
            return;
        }

        clearFiles();
    });

    /*
|--------------------------------------------------------------------------
| CLEANUP
|--------------------------------------------------------------------------
|
| Prevent memory leaks if the user refreshes while
| previews are still active.
|
*/

    window.addEventListener("beforeunload", () => {
        revokeAllObjectUrls();
    });
    /*
|--------------------------------------------------------------------------
| PUBLIC API
|--------------------------------------------------------------------------
|
| Used by messages.js and any future chat modules.
|
*/

    window.ChatAttachments = {
        /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

        hasPending() {
            return state.pendingFiles.length > 0;
        },

        count() {
            return state.pendingFiles.length;
        },
        primaryFile() {
            return state.pendingFiles[0] ?? null;
        },
        files() {
            return [...state.pendingFiles];
        },

        /*
    |--------------------------------------------------------------------------
    | FORM DATA
    |--------------------------------------------------------------------------
    */
        buildFormData(body, clientUuid, replyToMessageId = null) {
            const formData = new FormData();

            formData.append("body", body || "");

            if (clientUuid) {
                formData.append("client_uuid", clientUuid);
            }

            if (replyToMessageId) {
                formData.append("reply_to_message_id", replyToMessageId);
            }

            state.pendingFiles.forEach((file) => {
                formData.append("attachments[]", file);
            });

            return formData;
        },

        /*
    |--------------------------------------------------------------------------
    | FILE MANAGEMENT
    |--------------------------------------------------------------------------
    */

        clear() {
            clearFiles();
        },

        remove(index) {
            removeFile(index);
        },

        render() {
            renderAttachmentPreviews();
        },
        addGeneratedFile(file) {
            if (addFile(file)) {
                renderAttachmentPreviews();
            }
        },
        /*
    |--------------------------------------------------------------------------
    | DEBUG
    |--------------------------------------------------------------------------
    |
    | Useful during development.
    | Can be removed later.
    |
    */

        dump() {
            console.table(
                state.pendingFiles.map((file) => ({
                    name: file.name,
                    size: file.size,
                    type: file.type,
                })),
            );
        },
    };

    /*
|--------------------------------------------------------------------------
| INITIALIZE
|--------------------------------------------------------------------------
*/

    renderAttachmentPreviews();
})();

/*
attachments.js
│
├── ChatAttachments class
│
├── State
│     ├── pendingFiles
│     ├── objectUrls
│
├── DOM Cache
│
├── Validation
│
├── File Queue
│
├── Preview Rendering
│
├── Remove Attachment
│
├── Clear Attachments
│
├── FormData Builder
│
├── Public API
│
└── Initialize
*/

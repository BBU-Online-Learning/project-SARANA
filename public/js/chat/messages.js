/*
|--------------------------------------------------------------------------
| FORM SUBMIT — New Message / Reply / Edit Mode
|--------------------------------------------------------------------------
*/
// File: D:\education\Laravel_Project\Elearning\public\js\chat\messages.js

const failedMessageRetries = new Map();
const activeUploadControllers = new Map();
const failedUploadSignatures = new Map();

function createMessageUuid() {
    if (typeof crypto.randomUUID === "function") return crypto.randomUUID();

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, "0")).join("");
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function fileUploadSignature(file) {
    return file ? `${file.name}:${file.size}:${file.lastModified}` : "";
}

function setOptimisticMessagePending(clientUuid) {
    const message = document.querySelector(`[data-client-id="${clientUuid}"]`);
    if (!message) return;

    message.classList.add("message-pending");
    message.classList.remove("message-failed");

    const status = message.querySelector(".message-send-status");
    if (status) {
        status.textContent = "⏳";
        status.setAttribute("aria-label", "Sending");
    }

    const retry = message.querySelector(".retry-message-btn");
    if (retry) retry.hidden = true;

    const cancel = message.querySelector(".cancel-upload-btn");
    if (cancel) cancel.hidden = false;
}

function setOptimisticMessageFailed(clientUuid) {
    const message = document.querySelector(`[data-client-id="${clientUuid}"]`);
    if (!message) return;

    message.classList.remove("message-pending");
    message.classList.add("message-failed");

    const status = message.querySelector(".message-send-status");
    if (status) {
        status.textContent = "!";
        status.setAttribute("aria-label", "Failed to send");
    }

    const retry = message.querySelector(".retry-message-btn");
    if (retry) retry.hidden = false;

    const cancel = message.querySelector(".cancel-upload-btn");
    if (cancel) cancel.hidden = true;
}

function updateUploadProgress(clientUuid, progressEvent) {
    const message = document.querySelector(`[data-client-id="${clientUuid}"]`);
    const progress = message?.querySelector(".attachment-upload-progress");
    const label = message?.querySelector(".attachment-upload-percent");
    if (!progress || !progressEvent.total) return;

    const percent = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100));
    progress.value = percent;
    if (label) label.textContent = `${percent}%`;
}

function uploadMessage(roomId, formData, clientUuid) {
    const controller = new AbortController();
    activeUploadControllers.set(clientUuid, controller);

    return axios.post(`/chat/rooms/${roomId}/messages`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
        signal: controller.signal,
        onUploadProgress: (event) => updateUploadProgress(clientUuid, event),
    }).finally(() => {
        if (activeUploadControllers.get(clientUuid) === controller) {
            activeUploadControllers.delete(clientUuid);
        }
    });
}

function isCanceledRequest(error) {
    return error?.code === "ERR_CANCELED" || error?.name === "CanceledError" || axios.isCancel?.(error);
}

async function attemptMessageSend(clientUuid, send, { body = "", onSuccess = null, uploadSignature = null } = {}) {
    setOptimisticMessagePending(clientUuid);

    try {
        const response = await send();
        failedMessageRetries.delete(clientUuid);
        failedUploadSignatures.delete(clientUuid);
        await onSuccess?.(response);

        const input = document.querySelector("#message-form input[name='body']");
        if (body && input?.value === body) input.value = "";

        return true;
    } catch (error) {
        if (isCanceledRequest(error)) {
            failedMessageRetries.delete(clientUuid);
            failedUploadSignatures.delete(clientUuid);
            document.querySelector(`[data-client-id="${clientUuid}"]`)?.remove();
            window.AppNotifications?.info?.("Attachment upload cancelled. Your files are still selected.");
            return false;
        }

        const reconciledMessage = document.querySelector(`[data-client-id="${clientUuid}"][data-message-id]`);
        if (reconciledMessage) {
            failedMessageRetries.delete(clientUuid);
            failedUploadSignatures.delete(clientUuid);
            await onSuccess?.(null);
            const input = document.querySelector("#message-form input[name='body']");
            if (body && input?.value === body) input.value = "";
            return true;
        }

        if (uploadSignature) failedUploadSignatures.set(clientUuid, uploadSignature);
        failedMessageRetries.set(clientUuid, () => attemptMessageSend(clientUuid, send, { body, onSuccess, uploadSignature }));
        setOptimisticMessageFailed(clientUuid);

        const input = document.querySelector("#message-form input[name='body']");
        if (body && input && !input.value.trim()) input.value = body;

        console.error(error);
        window.AppNotifications?.fromAxios(error, "Unable to send the message. Your draft was kept so you can retry.");
        return false;
    }
}

document.addEventListener("submit", async (e) => {
    if (!e.target.matches("#message-form")) return;

    e.preventDefault();
    if (window.chat.isSendingMessage) return;

    const form = e.target;
    const input = form.querySelector('input[name="body"]');
    const body = input.value.trim();

    const hasFiles =
        window.ChatAttachments && window.ChatAttachments.hasPending();
    const hasVoice = !!window.chat.voiceDraftFile;

    if (!body && !hasFiles && !hasVoice) {
        return;
    }

    // Prevent double-send when the user double-clicks reply/send.
    window.chat.isSendingMessage = true;

    /*
    | EDIT MODE — PUT to update existing message
    | We reset the UI immediately, then fire the request.
    | The server will broadcast MessageUpdated → handleMessageUpdated()
    | re-renders the bubble with fresh HTML for everyone (including sender).
    | The sender gets a flash via the optimistic path BEFORE the broadcast.
    */
    // Reply/edit flow stays the same; only the guard above prevents duplicate sends.
    if (window.chat.editingMessageId) {
        const messageId = window.chat.editingMessageId;
        let editSnapshot = null;

        // 1. Optimistic flash for the sender before network round-trip
        const messageItem = document.querySelector(
            `[data-message-id="${messageId}"]`,
        );
        if (messageItem) {
            const bubble = messageItem.querySelector(".teams-message-bubble");
            const editBtn = messageItem.querySelector(".edit-message-btn");
            const editedLabel = messageItem.querySelector(".teams-edited-label");
            editSnapshot = {
                body: bubble?.textContent ?? "",
                messageBody: editBtn?.dataset.messageBody,
                hadEditedLabel: Boolean(editedLabel),
            };
            if (bubble) {
                // Update text immediately so sender sees change at once
                bubble.textContent = body;
                bubble.classList.add("message-edited-highlight");
                setTimeout(
                    () => bubble.classList.remove("message-edited-highlight"),
                    1000,
                );
            }
            // Add "edited" label immediately for the sender
            const metaSpan = messageItem.querySelector(
                ".teams-message-meta span",
            );
            if (metaSpan && !metaSpan.querySelector(".teams-edited-label")) {
                metaSpan.insertAdjacentHTML(
                    "beforeend",
                    `<span class="teams-edited-label">edited</span>`,
                );
            }
            // Keep data attribute in sync so re-clicking Edit reads correct body
            if (editBtn) editBtn.dataset.messageBody = body;
            const bubbleEl = messageItem.querySelector(".teams-message-bubble");
            if (bubbleEl) bubbleEl.dataset.messageBody = body;
        }

        // 2. Reset composer immediately (don't wait for network)
        cancelEditMode();

        // 3. Fire PUT — broadcast will re-render for all other users
        try {
            await axios.put(`/chat/messages/${messageId}`, { body });
        } catch (error) {
            const currentItem = document.querySelector(`[data-message-id="${messageId}"]`);
            const currentBubble = currentItem?.querySelector(".teams-message-bubble");
            const currentEditButton = currentItem?.querySelector(".edit-message-btn");

            if (editSnapshot && currentBubble) currentBubble.textContent = editSnapshot.body;
            if (editSnapshot && currentEditButton) currentEditButton.dataset.messageBody = editSnapshot.messageBody ?? "";
            if (editSnapshot && !editSnapshot.hadEditedLabel) currentItem?.querySelector(".teams-edited-label")?.remove();

            enterEditMode(messageId, body);
            console.error("Edit failed:", error);
            window.AppNotifications?.fromAxios(error, "Unable to edit the message. Your changes were restored for retry.");
        } finally {
            window.chat.isSendingMessage = false;
        }

        return;
    }

    /*
|--------------------------------------------------------------------------
| NEW MESSAGE / REPLY
|--------------------------------------------------------------------------
*/
    const roomId = window.chat.activeRoomId;
    const replyToMessageId = window.chat.replyingToMessageId;
    const attachmentFilesSignature = hasFiles ? window.ChatAttachments.signature() : null;
    const voiceFileSignature = hasVoice ? fileUploadSignature(window.chat.voiceDraftFile) : null;
    const uploadSignature = hasVoice
        ? `voice:${voiceFileSignature}:reply:${replyToMessageId || ""}`
        : (hasFiles ? `files:${attachmentFilesSignature}:body:${body}:reply:${replyToMessageId || ""}` : null);
    const matchingFailedUpload = uploadSignature
        ? Array.from(failedUploadSignatures.entries()).find(([, signature]) => signature === uploadSignature)
        : null;

    if (matchingFailedUpload && failedMessageRetries.has(matchingFailedUpload[0])) {
        try {
            await failedMessageRetries.get(matchingFailedUpload[0])();
        } finally {
            window.chat.isSendingMessage = false;
        }
        return;
    }

    const clientUuid = createMessageUuid();
    let send;
    let onSuccess;

    if (hasVoice) {
        appendOptimisticVoicePlaceholder(clientUuid);
        const formData = new FormData();
        formData.append("body", "");
        formData.append("client_uuid", clientUuid);
        formData.append("attachment_context", "voice");
        if (replyToMessageId) formData.append("reply_to_message_id", replyToMessageId);
        formData.append("attachments[]", window.chat.voiceDraftFile);
        send = () => uploadMessage(roomId, formData, clientUuid);
        onSuccess = () => {
            if (fileUploadSignature(window.chat.voiceDraftFile) === voiceFileSignature) window.ChatVoice?.reset?.();
        };
    } else if (hasFiles) {
        appendOptimisticAttachmentPlaceholder(
            clientUuid,
            window.ChatAttachments.count(),
        );
        const formData = window.ChatAttachments.buildFormData(body, clientUuid, replyToMessageId);
        send = () => uploadMessage(roomId, formData, clientUuid);
        onSuccess = () => window.ChatAttachments.clearIfSignature(attachmentFilesSignature);
    } else {
        appendOptimisticMessage(body, clientUuid);
        send = () => axios.post(`/chat/rooms/${roomId}/messages`, {
            body,
            client_uuid: clientUuid,
            reply_to_message_id: replyToMessageId,
        });
    }

    input.value = "";
    const sent = await attemptMessageSend(clientUuid, send, {
        body,
        uploadSignature,
        onSuccess: async (response) => {
            onSuccess?.();
            if (response?.data?.message_id) {
                await appendIncomingMessage(response.data).catch(error => console.error("Message saved; display sync failed", error));
            }
        },
    });

    if (sent) {
        window.chat.replyingToMessageId = null;
        window.chat.replyingToMessageText = null;
        document
            .getElementById("reply-preview")
            ?.style.setProperty("display", "none");
    }

    window.chat.isSendingMessage = false;
});

document.addEventListener("click", async (event) => {
    const cancelButton = event.target.closest(".cancel-upload-btn");
    if (cancelButton) {
        activeUploadControllers.get(cancelButton.dataset.clientId)?.abort();
        return;
    }

    const button = event.target.closest(".retry-message-btn");
    if (!button || window.chat.isSendingMessage) return;

    const retry = failedMessageRetries.get(button.dataset.clientId);
    if (!retry) return;

    window.chat.isSendingMessage = true;
    try {
        await retry();
    } finally {
        window.chat.isSendingMessage = false;
    }
});

/*
|--------------------------------------------------------------------------
| EDIT BUTTON CLICK
|--------------------------------------------------------------------------
| Reads the body from data-message-body on the button — set by Blade and
| kept in sync after optimistic updates — never from bubble.textContent
| which can have surrounding whitespace from Blade's template indentation.
*/
document.addEventListener("click", (e) => {
    const button = e.target.closest(".edit-message-btn");
    if (!button) return;

    const messageId = button.dataset.messageId;
    const originalBody = button.dataset.messageBody;

    if (!messageId || originalBody === undefined) return;

    enterEditMode(messageId, originalBody);
});

/*
|--------------------------------------------------------------------------
| enterEditMode()
|--------------------------------------------------------------------------
*/
function enterEditMode(messageId, body) {
    window.chat.editingMessageId = messageId;

    // Collapse any open reply preview — the two modes are mutually exclusive
    const replyPreview = document.getElementById("reply-preview");
    if (replyPreview) replyPreview.style.display = "none";
    window.chat.replyingToMessageId = null;
    window.chat.replyingToMessageText = null;

    // Show edit preview bar with truncated preview text
    const editPreview = document.getElementById("edit-preview");
    if (editPreview) {
        const previewText = editPreview.querySelector("#edit-preview-text");
        if (previewText) previewText.textContent = body;
        editPreview.style.display = "flex";
    }

    // Populate composer and focus with cursor at end
    const input = document.querySelector("#message-form input[name='body']");
    if (input) {
        input.value = body;
        input.focus();
        input.selectionStart = input.selectionEnd = input.value.length;
    }
}

/*
|--------------------------------------------------------------------------
| cancelEditMode()
|--------------------------------------------------------------------------
*/
function cancelEditMode() {
    window.chat.editingMessageId = null;

    const editPreview = document.getElementById("edit-preview");
    if (editPreview) editPreview.style.display = "none";

    const input = document.querySelector("#message-form input[name='body']");
    if (input) input.value = "";
}

/*
|--------------------------------------------------------------------------
| CANCEL EDIT BUTTON
|--------------------------------------------------------------------------
*/
document.addEventListener("click", (e) => {
    if (!e.target.closest("#cancel-edit-btn")) return;
    cancelEditMode();
});

/*
|--------------------------------------------------------------------------
| REPLY BUTTON
|--------------------------------------------------------------------------
*/
document.addEventListener("click", (e) => {
    const button = e.target.closest(".reply-btn");
    if (!button) return;

    // Cancel any active edit first
    if (window.chat.editingMessageId) cancelEditMode();

    window.chat.replyingToMessageId = button.dataset.messageId;
    window.chat.replyingToMessageText = button.dataset.messageBody;

    showReplyPreview();
});

function showReplyPreview() {
    const preview = document.getElementById("reply-preview");
    if (!preview) return;

    preview.style.display = "flex";
    document.getElementById("reply-preview-text").innerText =
        window.chat.replyingToMessageText;
}

/*
|--------------------------------------------------------------------------
| CANCEL REPLY
|--------------------------------------------------------------------------
*/
document.addEventListener("click", (e) => {
    if (!e.target.closest("#cancel-reply-btn")) return;

    window.chat.replyingToMessageId = null;
    window.chat.replyingToMessageText = null;

    const preview = document.getElementById("reply-preview");
    if (preview) preview.style.display = "none";
});

/*
|--------------------------------------------------------------------------
| JUMP TO REPLIED MESSAGE
|--------------------------------------------------------------------------
*/
document.addEventListener("click", (e) => {
    const el = e.target.closest(".jump-to-message");
    if (!el) return;

    const target = document.querySelector(
        `[data-message-id="${el.dataset.targetMessageId}"]`,
    );
    if (!target) return;

    target.scrollIntoView({ behavior: "smooth", block: "center" });
    target.classList.add("message-highlight");
    setTimeout(() => target.classList.remove("message-highlight"), 2000);
});

/*
|--------------------------------------------------------------------------
| OPTIMISTIC MESSAGE (new messages only)
|--------------------------------------------------------------------------
*/
function appendOptimisticMessage(body, clientUuid) {
    const container = document.querySelector(".messages-container");
    if (!container) return;

    const time = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });

    container.insertAdjacentHTML(
        "beforeend",
        `
        <div class="message-item teams-message is-own message-pending"
             data-client-id="${clientUuid}">
            <div class="teams-message-avatar">
                ${window.chat.currentUserInitial}
            </div>
            <div class="teams-message-stack">
                <div class="teams-message-meta">
                    <strong>${window.chat.currentUserName}</strong>
                    <span>${time} <span class="read-status message-send-status" aria-label="Sending">⏳</span></span>
                </div>
                <div class="teams-message-bubble">
                    ${escapeHtml(body)}
                </div>
                <button type="button" class="retry-message-btn" data-client-id="${clientUuid}" hidden>
                    <i class="ti ti-refresh" aria-hidden="true"></i> Retry
                </button>
            </div>
        </div>
    `,
    );

    container.scrollTop = container.scrollHeight;
}
function appendOptimisticAttachmentPlaceholder(clientUuid, fileCount) {
    const container = document.querySelector(".messages-container");

    if (!container) return;

    const time = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });

    const label =
        fileCount === 1
            ? "Sending attachment..."
            : `Sending ${fileCount} attachments...`;

    container.insertAdjacentHTML(
        "beforeend",
        `
        <div class="message-item teams-message is-own message-pending"
             data-client-id="${clientUuid}">

            <div class="teams-message-avatar">
                ${window.chat.currentUserInitial}
            </div>

            <div class="teams-message-stack">

                <div class="teams-message-meta">
                    <strong>${window.chat.currentUserName}</strong>
                    <span>${time} <span class="message-send-status" aria-label="Sending">⏳</span></span>
                </div>

                <div class="teams-message-bubble attachment-uploading-bubble">
                    <i class="ti ti-loader-2"></i>
                    <span>${escapeHtml(label)}</span>
                    <span class="attachment-upload-percent">0%</span>
                </div>
                <progress class="attachment-upload-progress" max="100" value="0" aria-label="Attachment upload progress"></progress>
                <button type="button" class="cancel-upload-btn" data-client-id="${clientUuid}">Cancel upload</button>
                <button type="button" class="retry-message-btn" data-client-id="${clientUuid}" hidden>
                    <i class="ti ti-refresh" aria-hidden="true"></i> Retry
                </button>

            </div>

        </div>
        `,
    );

    container.scrollTop = container.scrollHeight;
}
function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

/*
|--------------------------------------------------------------------------
| DELETE FOR EVERYONE — sender only
|--------------------------------------------------------------------------
*/
document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".delete-message-btn");
    if (!btn) return;

    const messageId = btn.dataset.messageId;

    const confirmed = await window.AppConfirm.ask({
        title: "Delete for everyone?",
        message: 'This cannot be undone. All members will see "This message was deleted".',
        confirmLabel: "Delete message",
        tone: "danger",
    });
    if (!confirmed) return;

    /*
    | Optimistic UI: replace the bubble immediately for the sender.
    | The broadcast will update all other clients.
    */
    replaceWithDeletedUI(messageId);

    try {
        await axios.delete(`/chat/messages/${messageId}`);
        window.AppNotifications?.success("Message deleted.");
    } catch (error) {
        console.error("Delete for everyone failed:", error);
        window.AppNotifications?.fromAxios(error, "Unable to delete the message. Please try again.");
        /*
        | On failure, reload the room to restore truth.
        | A more polished approach would restore the original bubble.
        */
        if (window.chat.activeRoomId) {
            await loadRoom(window.chat.activeRoomId);
        }
    }
});

/*
|--------------------------------------------------------------------------
| DELETE FOR ME — available to all members
|--------------------------------------------------------------------------
*/
document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".hide-message-btn");
    if (!btn) return;

    const messageId = btn.dataset.messageId;

    const confirmed = await window.AppConfirm.ask({
        title: "Remove for you?",
        message: "Other members will still be able to see this message.",
        confirmLabel: "Remove message",
        tone: "danger",
    });
    if (!confirmed) return;

    /*
    | Optimistic: remove from DOM immediately.
    | No broadcast needed — this is local only.
    */
    const el = document.querySelector(`[data-message-id="${messageId}"]`);
    if (el) el.remove();

    try {
        await axios.post(`/chat/messages/${messageId}/hide`);
        window.AppNotifications?.success("Message removed for you.");
    } catch (error) {
        console.error("Hide for me failed:", error);
        window.AppNotifications?.fromAxios(error, "Unable to remove the message. Please try again.");
        if (window.chat.activeRoomId) {
            await loadRoom(window.chat.activeRoomId);
        }
    }
});

/*
|--------------------------------------------------------------------------
| replaceWithDeletedUI()
|--------------------------------------------------------------------------
| Transforms an existing message bubble into the "deleted" state in-place.
| Used both for optimistic sender updates and for the broadcast handler.
*/
function replaceWithDeletedUI(messageId) {
    const el = document.querySelector(`[data-message-id="${messageId}"]`);
    if (!el) return;

    // Mark the wrapper so deleted styling can apply immediately.
    el.classList.add("is-deleted");

    // Hide the toolbar.
    const toolbar = el.querySelector(".teams-message-toolbar");
    if (toolbar) toolbar.remove();

    // Replace either the normal text bubble or the attachment/voice area.
    const bubble = el.querySelector(".teams-message-bubble");
    const attachments = el.querySelector(".message-attachments");
    const sticker = el.querySelector(".sticker-message");

    const deletedBubble = document.createElement("div");
    deletedBubble.className = "teams-message-bubble teams-message-deleted";
    deletedBubble.innerHTML = '<i class="ti ti-ban" aria-hidden="true"></i> This message was deleted';

    if (bubble) {
        bubble.replaceWith(deletedBubble);
    } else if (sticker) {
        sticker.replaceWith(deletedBubble);
    } else if (attachments) {
        attachments.replaceWith(deletedBubble);
    } else {
        // Fallback for any custom render path.
        el.querySelector(".teams-message-stack")?.appendChild(deletedBubble);
    }

    // Hide reply preview if present.
    const replyPreview = el.querySelector(".teams-reply-preview");
    if (replyPreview) replyPreview.remove();

    // Hide edit button if present.
    const editBtn = el.querySelector(".edit-message-btn");
    if (editBtn) editBtn.remove();

    // Hide read row.
    const readRow = el.querySelector(".teams-read-row");
    if (readRow) readRow.remove();
    el.querySelector('.read-status')?.remove();

    // Update any quoted reply snippets elsewhere in the thread.
    document
        .querySelectorAll(`.jump-to-message[data-target-message-id="${messageId}"] .reply-text`)
        .forEach((span) => {
            span.innerHTML = "<em>Deleted message</em>";
        });
}
/*
|--------------------------------------------------------------------------
| REACTION PICKER — open / close
|--------------------------------------------------------------------------
*/
document.addEventListener("click", (e) => {
    // Toggle picker on trigger button click
    const trigger = e.target.closest(".reaction-trigger-btn");
    if (trigger) {
        const messageId = trigger.dataset.messageId;
        const picker = document.querySelector(
            `.reaction-picker[data-message-id="${messageId}"]`,
        );
        if (!picker) return;

        // Close any other open pickers
        document.querySelectorAll(".reaction-picker").forEach((p) => {
            if (p !== picker) p.style.display = "none";
        });

        picker.style.display =
            picker.style.display === "flex" ? "none" : "flex";
        return;
    }

    // Close picker when clicking outside
    if (
        !e.target.closest(".reaction-picker") &&
        !e.target.closest(".reaction-trigger-btn")
    ) {
        document.querySelectorAll(".reaction-picker").forEach((p) => {
            p.style.display = "none";
        });
    }
});

/*
|--------------------------------------------------------------------------
| REACTION SELECTED — from picker popup
|--------------------------------------------------------------------------
*/
document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".reaction-picker-emoji");
    if (!btn) return;

    const messageId = btn.dataset.messageId;
    const emoji = btn.dataset.emoji;

    // Close picker immediately
    const picker = document.querySelector(
        `.reaction-picker[data-message-id="${messageId}"]`,
    );
    if (picker) picker.style.display = "none";

    await sendReaction(messageId, emoji);
});

/*
|--------------------------------------------------------------------------
| REACTION PILL CLICK — toggle existing reaction
|--------------------------------------------------------------------------
*/
document.addEventListener("click", async (e) => {
    const pill = e.target.closest(".reaction-pill");
    if (!pill) return;

    await sendReaction(pill.dataset.messageId, pill.dataset.emoji);
});

/*
|--------------------------------------------------------------------------
| sendReaction()
|--------------------------------------------------------------------------
*/
async function sendReaction(messageId, emoji) {
    // Optimistic UI — find and update immediately
    const container = document.querySelector(
        `.message-reactions[data-message-id="${messageId}"]`,
    );

    try {
        await axios.post(`/chat/messages/${messageId}/reactions`, { emoji });
        // Server will broadcast reaction.updated → handleReactionUpdated()
        // which re-renders pills with authoritative counts for everyone.
    } catch (error) {
        console.error("Reaction failed:", error);
    }
}
function appendOptimisticVoicePlaceholder(clientUuid) {
    const container = document.querySelector(".messages-container");
    if (!container) return;

    const time = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });

    container.insertAdjacentHTML(
        "beforeend",
        `
        <div class="message-item teams-message is-own message-pending"
             data-client-id="${clientUuid}">
            <div class="teams-message-avatar">
                ${window.chat.currentUserInitial}
            </div>

            <div class="teams-message-stack">
                <div class="teams-message-meta">
                    <strong>${window.chat.currentUserName}</strong>
                    <span>${time} <span class="message-send-status" aria-label="Sending">⏳</span></span>
                </div>

                <div class="voice-message-card voice-message-pending">
                    <div class="voice-message-icon">
                        <i class="ti ti-microphone"></i>
                    </div>

                    <div class="voice-message-body">
                        <strong>Voice message</strong>
                        <span>Sending… <span class="attachment-upload-percent">0%</span></span>
                    </div>
                </div>
                <progress class="attachment-upload-progress" max="100" value="0" aria-label="Voice message upload progress"></progress>
                <button type="button" class="cancel-upload-btn" data-client-id="${clientUuid}">Cancel upload</button>
                <button type="button" class="retry-message-btn" data-client-id="${clientUuid}" hidden>
                    <i class="ti ti-refresh" aria-hidden="true"></i> Retry
                </button>
            </div>
        </div>
        `,
    );

    container.scrollTop = container.scrollHeight;
}

function appendOptimisticSticker(sticker, clientUuid) {
    const container = document.querySelector(".messages-container");
    if (!container) return;

    const time = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });

    container.insertAdjacentHTML(
        "beforeend",
        `
        <div class="message-item teams-message is-own message-pending sticker-message-item"
             data-client-id="${escapeHtml(clientUuid)}" data-message-type="sticker">
            <div class="teams-message-avatar">
                ${escapeHtml(window.chat.currentUserInitial)}
            </div>
            <div class="teams-message-stack">
                <div class="teams-message-meta">
                    <strong>${escapeHtml(window.chat.currentUserName)}</strong>
                    <span>${time} <span class="message-send-status" aria-label="Sending">⏳</span></span>
                </div>
                <div class="sticker-message" aria-label="${escapeHtml(sticker.name)} sticker">
                    <img src="${escapeHtml(sticker.url)}" alt="${escapeHtml(sticker.name)} sticker">
                </div>
                <button type="button" class="retry-message-btn" data-client-id="${escapeHtml(clientUuid)}" hidden>
                    <i class="ti ti-refresh" aria-hidden="true"></i> Retry
                </button>
            </div>
        </div>
        `,
    );

    container.scrollTop = container.scrollHeight;
}

async function sendSticker(stickerId) {
    if (!window.chat.activeRoomId || window.chat.isSendingMessage) return false;

    const sticker = window.chat.stickers?.find((item) => item.id === stickerId);
    if (!sticker) {
        window.AppNotifications?.error("That sticker is not available.");
        return false;
    }

    if (window.chat.voiceDraftFile) {
        window.AppNotifications?.warning("Send or cancel your voice message before choosing a sticker.");
        return false;
    }

    if (window.ChatAttachments?.hasPending?.()) {
        window.AppNotifications?.warning("Send or remove your attachments before choosing a sticker.");
        return false;
    }

    window.chat.isSendingMessage = true;
    const clientUuid = createMessageUuid();
    const roomId = window.chat.activeRoomId;
    const replyToMessageId = window.chat.replyingToMessageId;
    appendOptimisticSticker(sticker, clientUuid);

    const sent = await attemptMessageSend(
        clientUuid,
        () => axios.post(`/chat/rooms/${roomId}/messages`, {
            sticker_id: sticker.id,
            client_uuid: clientUuid,
            reply_to_message_id: replyToMessageId,
        }),
        {
            onSuccess: async (response) => {
                if (response?.data?.message_id) {
                    await appendIncomingMessage(response.data).catch((error) =>
                        console.error("Sticker saved; display sync failed", error),
                    );
                }
            },
        },
    );

    if (sent) {
        window.chat.replyingToMessageId = null;
        window.chat.replyingToMessageText = null;
        document.getElementById("reply-preview")?.style.setProperty("display", "none");
    }

    window.chat.isSendingMessage = false;
    return sent;
}

async function sendVoiceDraft(file) {
    if (!file || !window.chat.activeRoomId || window.chat.isSendingMessage) return false;

    window.chat.isSendingMessage = true;
    const roomId = window.chat.activeRoomId;
    const replyToMessageId = window.chat.replyingToMessageId;
    const voiceFileSignature = fileUploadSignature(file);
    const uploadSignature = `voice:${voiceFileSignature}:reply:${replyToMessageId || ""}`;
    const matchingFailedUpload = Array.from(failedUploadSignatures.entries())
        .find(([, signature]) => signature === uploadSignature);

    if (matchingFailedUpload && failedMessageRetries.has(matchingFailedUpload[0])) {
        try {
            return await failedMessageRetries.get(matchingFailedUpload[0])();
        } finally {
            window.chat.isSendingMessage = false;
        }
    }

    const clientUuid = createMessageUuid();
    const formData = new FormData();

    formData.append("body", "");
    formData.append("client_uuid", clientUuid);
    formData.append("attachment_context", "voice");
    if (replyToMessageId) formData.append("reply_to_message_id", replyToMessageId);
    formData.append("attachments[]", file);
    appendOptimisticVoicePlaceholder(clientUuid);

    const sent = await attemptMessageSend(
        clientUuid,
        () => uploadMessage(roomId, formData, clientUuid),
        {
            uploadSignature,
            onSuccess: async (response) => {
                if (fileUploadSignature(window.chat.voiceDraftFile) === voiceFileSignature) window.ChatVoice?.reset?.();
                if (response?.data?.message_id) {
                    await appendIncomingMessage(response.data).catch(error => console.error("Voice message saved; display sync failed", error));
                }
            },
        },
    );

    if (sent) {
        window.chat.replyingToMessageId = null;
        window.chat.replyingToMessageText = null;
        document.getElementById("reply-preview")?.style.setProperty("display", "none");
    }

    window.chat.isSendingMessage = false;
    return sent;
}

// Expose helper for voice.js
window.ChatMessages = {
    appendOptimisticVoicePlaceholder,
    appendOptimisticSticker,
    sendSticker,
    sendVoiceDraft,
};

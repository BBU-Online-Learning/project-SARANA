/*
|--------------------------------------------------------------------------
| FORM SUBMIT — New Message / Reply / Edit Mode
|--------------------------------------------------------------------------
*/
// File: D:\education\Laravel_Project\Elearning\public\js\chat\messages.js

document.addEventListener("submit", async (e) => {
    if (!e.target.matches("#message-form")) return;

    e.preventDefault();
    // Prevent double-send when the user double-clicks reply/send.
    if (window.chat.isSendingMessage) return;
    window.chat.isSendingMessage = true;

    const form = e.target;
    const input = form.querySelector('input[name="body"]');
    const body = input.value.trim();

    const hasFiles =
        window.ChatAttachments && window.ChatAttachments.hasPending();
    const hasVoice = !!window.chat.voiceDraftFile;

    if (!body && !hasFiles && !hasVoice) {
        return;
    }

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

        // 1. Optimistic flash for the sender before network round-trip
        const messageItem = document.querySelector(
            `[data-message-id="${messageId}"]`,
        );
        if (messageItem) {
            const bubble = messageItem.querySelector(".teams-message-bubble");
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
            const editBtn = messageItem.querySelector(".edit-message-btn");
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
            console.error("Edit failed:", error);
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
    const clientUuid = crypto.randomUUID();

    if (hasVoice) {
        appendOptimisticVoicePlaceholder(clientUuid);
    } else if (body) {
        appendOptimisticMessage(body, clientUuid);
    } else {
        appendOptimisticAttachmentPlaceholder(
            clientUuid,
            window.ChatAttachments.count(),
        );
    }

    input.value = "";
    const replyToMessageId = window.chat.replyingToMessageId;
    try {
        if (hasVoice) {
            const formData = new FormData();
            formData.append("body", "");
            formData.append("client_uuid", clientUuid);

            if (replyToMessageId) {
                formData.append("reply_to_message_id", replyToMessageId);
            }

            formData.append("attachments[]", window.chat.voiceDraftFile);

            await axios.post(
                `/chat/rooms/${window.chat.activeRoomId}/messages`,
                formData,
                { headers: { "Content-Type": "multipart/form-data" } },
            );

            window.ChatVoice?.reset?.();
        } else if (hasFiles) {
            const formData = window.ChatAttachments.buildFormData(
                body,
                clientUuid,
                replyToMessageId,
            );

            await axios.post(
                `/chat/rooms/${window.chat.activeRoomId}/messages`,
                formData,
                { headers: { "Content-Type": "multipart/form-data" } },
            );

            window.ChatAttachments.clear();
        } else {
            await axios.post(
                `/chat/rooms/${window.chat.activeRoomId}/messages`,
                {
                    body,
                    client_uuid: clientUuid,
                    reply_to_message_id: replyToMessageId,
                },
            );
        }

        window.chat.replyingToMessageId = null;
        window.chat.replyingToMessageText = null;
        document
            .getElementById("reply-preview")
            ?.style.setProperty("display", "none");
    } catch (error) {
        rollbackOptimisticMessage(clientUuid);
        console.error(error);
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
                    <span>${time}</span>
                </div>
                <div class="teams-message-bubble">
                    ${escapeHtml(body)}
                </div>
                <div class="teams-read-row">
                    <span class="read-status">⏳</span>
                </div>
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
                    <span>${time}</span>
                </div>

                <div class="teams-message-bubble attachment-uploading-bubble">
                    <i class="ti ti-loader-2"></i>
                    ${escapeHtml(label)}
                </div>

            </div>

        </div>
        `,
    );

    container.scrollTop = container.scrollHeight;
}
function rollbackOptimisticMessage(clientUuid) {
    document.querySelector(`[data-client-id="${clientUuid}"]`)?.remove();
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

    /*
    | Inline confirm — keeps it simple without a modal library.
    | Production upgrade: replace with a custom modal component.
    */
    const confirmed = confirm(
        'Delete for everyone?\n\nThis cannot be undone. All members will see "This message was deleted".',
    );
    if (!confirmed) return;

    /*
    | Optimistic UI: replace the bubble immediately for the sender.
    | The broadcast will update all other clients.
    */
    replaceWithDeletedUI(messageId);

    try {
        await axios.delete(`/chat/messages/${messageId}`);
    } catch (error) {
        console.error("Delete for everyone failed:", error);
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

    const confirmed = confirm(
        "Remove this message for you only? Others will still see it.",
    );
    if (!confirmed) return;

    /*
    | Optimistic: remove from DOM immediately.
    | No broadcast needed — this is local only.
    */
    const el = document.querySelector(`[data-message-id="${messageId}"]`);
    if (el) el.remove();

    try {
        await axios.post(`/chat/messages/${messageId}/hide`);
    } catch (error) {
        console.error("Hide for me failed:", error);
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

    const deletedBubble = document.createElement("div");
    deletedBubble.className = "teams-message-bubble teams-message-deleted";
    deletedBubble.innerHTML = '<i class="ti ti-ban" aria-hidden="true"></i> This message was deleted';

    if (bubble) {
        bubble.replaceWith(deletedBubble);
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
                    <span>${time}</span>
                </div>

                <div class="voice-message-card voice-message-pending">
                    <div class="voice-message-icon">
                        <i class="ti ti-microphone"></i>
                    </div>

                    <div class="voice-message-body">
                        <strong>Voice message</strong>
                        <span>Sending...</span>
                    </div>
                </div>
            </div>
        </div>
        `,
    );

    container.scrollTop = container.scrollHeight;
}

// Expose helper for voice.js
window.ChatMessages = {
    appendOptimisticVoicePlaceholder,
};

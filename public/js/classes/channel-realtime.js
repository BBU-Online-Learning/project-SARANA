document.addEventListener("DOMContentLoaded", () => {
    const page = document.getElementById("school-class-channel-page");
    const list = document.getElementById("class-channel-message-list");
    if (!page || !list || !page.dataset.messagesUrl) return;

    const form = document.getElementById("class-channel-message-form");
    const status = document.getElementById("class-channel-sync-status");
    const older = document.getElementById("class-channel-older");
    const historyPage = page.dataset.historyPage === "1";
    const topic = `school-class.membership.${page.dataset.membershipId}`;
    const messages = new Map();
    let cursor = 0;
    let stopped = false;
    let subscribed = false;
    let connection;
    let queue = Promise.resolve();
    let refreshQueued = false;
    let dirty = false;
    let sending = false;
    let canSend = !!form;

    const enqueue = (work) => {
        queue = queue.then(work).catch(() => {
            status.textContent = "Connection interrupted. Retrying automatically; your draft is preserved.";
        });
        return queue;
    };

    function revoke() {
        stopped = true;
        clearInterval(timer);
        window.Echo?.leave(topic);
        connection?.unbind("connected", refresh);
        const warning = document.createElement("div");
        warning.className = "alert alert-warning";
        warning.textContent = "Class access is no longer available. Return to Classes or sign in again.";
        page.replaceChildren(warning);
    }

    async function request(url, options = {}) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(url, {
                ...options,
                credentials: "same-origin", cache: "no-store", signal: controller.signal,
                headers: {
                    Accept: "application/json", "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
                },
            });
            if (response.redirected || [401, 419].includes(response.status)) {
                revoke();
                throw new Error("Please sign in again.");
            }
            const data = await response.json();
            if (!response.ok) {
                if (options.method === undefined && [403, 404].includes(response.status)) revoke();
                throw new Error(Object.values(data.errors || {}).flat().join(" ") || data.message || "Request failed. Please retry.");
            }
            return data;
        } finally {
            clearTimeout(timeout);
        }
    }

    function subscribe() {
        if (subscribed || !window.Echo) return;
        window.Echo.private(topic).listen(".school-class.messages.changed", refresh)
            .subscribed(refresh).error(() => { status.textContent = "Live connection unavailable. Checking for updates automatically."; });
        connection = window.Echo.connector?.pusher?.connection;
        connection?.bind("connected", refresh);
        subscribed = true;
    }

    function button(label, action) {
        const element = document.createElement("button");
        element.type = "button";
        element.className = "btn btn-sm btn-outline-secondary";
        element.textContent = label;
        element.addEventListener("click", action);
        return element;
    }

    function showEmptyState() {
        if (messages.size || list.querySelector('.alert')) return;
        const empty = document.createElement('div');
        empty.className = 'alert alert-light mb-0';
        empty.textContent = 'No messages on this page. Use Older messages to browse history, or start the discussion.';
        list.append(empty);
    }

    function apply(message) {
        const id = Number(message.message_id);
        let node = list.querySelector(`[data-class-message-id="${id}"]`);
        if (message.deleted) {
            messages.delete(id);
            node?.remove();
            return;
        }
        messages.set(id, message);
        if (node?.querySelector("[data-message-editor]")) {
            node.querySelectorAll("button, textarea").forEach((control) => { control.disabled = !message.can_modify; });
            return;
        }
        const replacement = document.createElement("div");
        replacement.className = "border-bottom py-3";
        replacement.dataset.classMessageId = id;
        const heading = document.createElement("div");
        heading.className = "d-flex justify-content-between align-items-center";
        const sender = document.createElement("strong");
        sender.textContent = message.sender_name;
        const time = document.createElement("span");
        time.className = "text-muted small";
        time.textContent = new Date(message.created_at).toLocaleString() + (message.is_edited ? " (edited)" : "");
        const body = document.createElement("div");
        body.className = "mt-2";
        body.style.whiteSpace = "pre-wrap";
        body.textContent = message.body;
        heading.append(sender, time);
        replacement.append(heading, body);
        if (message.can_modify) {
            const actions = document.createElement("div");
            actions.className = "d-flex gap-2 mt-2";
            actions.append(button("Edit", () => edit(id)), button("Delete", () => {
                if (!window.confirm("Delete your message?")) return;
                actions.querySelectorAll("button").forEach((control) => { control.disabled = true; });
                enqueue(async () => {
                    try {
                        const data = await request(`${page.dataset.messagesUrl}/${id}`, { method: "DELETE" });
                        if (!stopped) apply(data.message);
                    } catch (error) {
                        status.textContent = error.message;
                        if (!stopped) apply(messages.get(id));
                    }
                    refresh();
                });
            }));
            replacement.append(actions);
        }
        if (node) node.replaceWith(replacement);
        else {
            const next = Array.from(list.children).find((child) => Number(child.dataset.classMessageId) > id);
            list.querySelector(".alert")?.remove();
            list.insertBefore(replacement, next || null);
        }
        while (messages.size > 200) {
            const first = Math.min(...messages.keys());
            messages.delete(first);
            list.querySelector(`[data-class-message-id="${first}"]`)?.remove();
            older.hidden = false;
        }
        if (messages.size) {
            const url = new URL(page.dataset.pageUrl, location.origin);
            url.searchParams.set("before_id", Math.min(...messages.keys()));
            older.href = url;
        }
    }

    function edit(id) {
        const node = list.querySelector(`[data-class-message-id="${id}"]`);
        const editor = document.createElement("form");
        editor.dataset.messageEditor = "true";
        const input = document.createElement("textarea");
        input.className = "form-control my-2";
        input.maxLength = 5000;
        input.required = true;
        input.value = messages.get(id).body;
        input.setAttribute("aria-label", "Edit message");
        const error = document.createElement("div");
        error.className = "text-danger small";
        error.setAttribute("role", "alert");
        const save = button("Save", () => editor.requestSubmit());
        const cancel = button("Cancel", () => { editor.remove(); apply(messages.get(id)); });
        editor.append(input, save, cancel, error);
        node.replaceChildren(editor);
        editor.addEventListener("submit", (event) => {
            event.preventDefault();
            save.disabled = true;
            cancel.disabled = true;
            enqueue(async () => {
                try {
                    const data = await request(`${page.dataset.messagesUrl}/${id}`, {
                        method: "PATCH", body: JSON.stringify({ body: input.value }),
                    });
                    if (!stopped) { editor.remove(); apply(data.message); }
                } catch (failure) {
                    error.textContent = failure.message;
                } finally {
                    save.disabled = false;
                    cancel.disabled = false;
                    refresh();
                }
            });
        });
        input.focus();
    }

    function refresh() {
        if (stopped) return;
        dirty = true;
        if (refreshQueued) return;
        refreshQueued = true;
        enqueue(async () => {
            dirty = false;
            try {
                subscribe();
                const url = new URL(page.dataset.messagesUrl, location.origin);
                url.searchParams.set("after_id", cursor);
                if (historyPage) url.searchParams.set("sync_only", "1");
                for (const id of messages.keys()) url.searchParams.append("visible_ids[]", id);
                const data = await request(url);
                if (stopped) return;
                const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
                for (const message of data.messages) apply(message);
                for (const message of data.updates) apply(message);
                showEmptyState();
                cursor = Math.max(cursor, data.next_id);
                canSend = data.can_send;
                form?.querySelectorAll("textarea, button").forEach((control) => { control.disabled = !canSend || sending; });
                const notice = document.getElementById("class-channel-read-only");
                if (notice) notice.hidden = canSend;
                if (nearBottom) list.scrollTop = list.scrollHeight;
                status.textContent = historyPage ? "Viewing older history. Use Latest messages to return to the conversation." : "Messages are up to date.";
                dirty = dirty || data.has_more;
            } finally {
                refreshQueued = false;
                if (dirty && !stopped) setTimeout(refresh, 0);
            }
        });
    }

    form?.addEventListener("submit", (event) => {
        event.preventDefault();
        if (sending || !canSend || stopped) return;
        sending = true;
        const body = form.elements.body.value;
        const uuid = form.elements.client_uuid.value;
        const error = document.getElementById("class-channel-send-error");
        error.textContent = "";
        form.querySelector("button[type=submit]").disabled = true;
        enqueue(async () => {
            try {
                const data = await request(form.action, { method: "POST", body: JSON.stringify({ body, client_uuid: uuid }) });
                if (stopped) return;
                if (!historyPage) apply(data.message);
                if (form.elements.body.value === body) form.elements.body.value = "";
                form.elements.client_uuid.value = crypto.randomUUID();
                status.textContent = "Message saved.";
            } catch (failure) {
                error.textContent = failure.message + " Your draft is preserved.";
            } finally {
                sending = false;
                form.querySelector("button[type=submit]").disabled = !canSend;
                refresh();
            }
        });
    });

    const initial = JSON.parse(document.getElementById("class-channel-initial").textContent);
    list.replaceChildren();
    for (const message of initial) { apply(message); cursor = Math.max(cursor, message.message_id); }
    showEmptyState();
    const timer = setInterval(refresh, 15000);
    window.addEventListener("online", refresh);
    window.addEventListener("pageshow", (event) => { if (event.persisted) window.location.reload(); });
    window.addEventListener("pagehide", () => {
        stopped = true;
        clearInterval(timer);
        window.Echo?.leave(topic);
        connection?.unbind("connected", refresh);
    });
    list.scrollTop = list.scrollHeight;
    refresh();
});

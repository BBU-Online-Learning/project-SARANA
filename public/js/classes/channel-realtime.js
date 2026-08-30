document.addEventListener("DOMContentLoaded", () => {
    const page = document.getElementById("school-class-channel-page");
    const list = document.getElementById("class-channel-message-list");
    if (!page || !list || !page.dataset.messagesUrl) {
        return;
    }

    const topic = `school-class.membership.${page.dataset.membershipId}`;
    let cursor = Math.max(0, ...Array.from(list.querySelectorAll("[data-class-message-id]"),
        (node) => Number(node.dataset.classMessageId)));
    let busy = false;
    let stopped = false;
    let subscribed = false;

    function revoke() {
        stopped = true;
        clearInterval(timer);
        window.Echo?.leave(topic);
        const warning = document.createElement("div");
        warning.className = "alert alert-warning";
        warning.textContent = "Class access is no longer available. Return to Classes or sign in again.";
        page.replaceChildren(warning);
    }

    function subscribe() {
        if (!subscribed && window.Echo) {
            window.Echo.private(topic).listen(".school-class.messages.changed", refresh);
            subscribed = true;
        }
    }

    async function refresh() {
        if (busy || stopped) {
            return;
        }
        busy = true;
        try {
            subscribe();
            let more;
            do {
                const url = new URL(page.dataset.messagesUrl, window.location.origin);
                url.searchParams.set("after_id", cursor);
                const response = await fetch(url, {
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: { Accept: "application/json" },
                });
                if (response.redirected || [401, 403, 404, 419].includes(response.status)) {
                    revoke();
                    return;
                }
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                for (const message of data.messages) {
                    appendMessage(message);
                }
                cursor = data.next_id;
                more = data.has_more;
            } while (more && !stopped);
        } catch {
            // Polling retries transient network errors without discarding history.
        } finally {
            busy = false;
        }
    }

    function appendMessage(message) {
        if (list.querySelector(`[data-class-message-id="${message.message_id}"]`)) {
            return;
        }
        const wrapper = document.createElement("div");
        wrapper.className = "border-bottom py-3";
        wrapper.dataset.classMessageId = message.message_id;
        const heading = document.createElement("div");
        heading.className = "d-flex justify-content-between align-items-center";
        const sender = document.createElement("strong");
        sender.textContent = message.sender_name;
        const time = document.createElement("span");
        time.className = "text-muted small";
        time.textContent = new Date(message.created_at).toLocaleString();
        const body = document.createElement("div");
        body.className = "mt-2";
        body.textContent = message.body;
        heading.append(sender, time);
        wrapper.append(heading, body);
        list.querySelector(".alert")?.remove();
        list.appendChild(wrapper);
        list.scrollTop = list.scrollHeight;
    }

    const timer = setInterval(refresh, 15000);
    window.addEventListener("pageshow", (event) => {
        if (event.persisted) {
            revoke();
            window.location.reload();
        }
    });
    window.addEventListener("pagehide", () => {
        clearInterval(timer);
        window.Echo?.leave(topic);
    });
    list.scrollTop = list.scrollHeight;
    refresh();
});

(() => {
    let timer, refreshTimer, observer, mutationObserver, container;
    let generation = 0, pending = false, lastReadId = null, refreshing = false, refreshAgain = false;
    let panelMessageId = null, panelVersion = 0, returnFocus = null;
    const dialog = () => document.getElementById('chat-read-dialog');
    const active = () => container?.isConnected && !document.hidden && document.hasFocus()
        && container.getBoundingClientRect().width > 0 && container.getBoundingClientRect().height > 0;

    function newestVisibleIncoming() {
        if (!active()) return null;
        const bounds = container.getBoundingClientRect();
        return [...container.querySelectorAll('.message-item.is-other:not(.is-deleted)[data-message-id]')]
            .filter(node => {
                const rect = node.getBoundingClientRect();
                return rect.height > 0 && rect.top < bounds.bottom && rect.bottom > bounds.top;
            }).at(-1)?.dataset.messageId ?? null;
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(markVisible, 250);
    }

    async function markVisible() {
        const id = newestVisibleIncoming();
        if (!id || id === lastReadId || pending) return;
        const roomId = window.chat.activeRoomId, token = generation;
        pending = true;
        try {
            const response = await axios.post(`/chat/rooms/${roomId}/read`, { up_to_message_id: Number(id) });
            if (token !== generation) return;
            lastReadId = id;
            handleUnreadCountUpdated({ room_id: roomId, unread_count: response.data.unread_count });
        } catch (error) {
            if (token === generation && [401, 403, 404, 419].includes(error.response?.status)) closePanel();
        } finally {
            if (token === generation) {
                pending = false;
                if (lastReadId === id && newestVisibleIncoming() !== id) schedule();
            }
        }
    }

    function applyStatus(status) {
        const button = container?.querySelector(`.read-status[data-message-id="${status.message_id}"]`);
        if (!button) return;
        const read = status.read_count > 0, group = button.dataset.isGroup === '1';
        button.textContent = read ? '✓✓' : '✓';
        button.dataset.read = read ? '1' : '0';
        button.disabled = !group || !read;
        button.setAttribute('aria-label', read ? (group ? 'Read by members. Show details' : 'Read') : 'Sent');
    }

    function refresh() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(syncStatuses, 150);
    }

    async function syncStatuses() {
        if (!active()) return;
        if (refreshing) { refreshAgain = true; return; }
        refreshing = true;
        const roomId = window.chat.activeRoomId, token = generation;
        const ids = [...container.querySelectorAll('.read-status[data-message-id]')].map(node => node.dataset.messageId);
        try {
            for (let offset = 0; offset < Math.max(1, ids.length); offset += 50) {
                const response = await axios.get(`/chat/rooms/${roomId}/reads`, { params: { ids: ids.slice(offset, offset + 50).join(',') } });
                if (token !== generation) return;
                response.data.statuses.forEach(applyStatus);
                handleUnreadCountUpdated({ room_id: roomId, unread_count: response.data.unread_count });
            }
            if (panelMessageId) await loadPanel();
        } catch (error) {
            if (token === generation && [401, 403, 404, 419].includes(error.response?.status)) closePanel();
        } finally {
            if (token === generation) {
                refreshing = false;
                if (refreshAgain) { refreshAgain = false; refresh(); }
            }
        }
    }

    async function loadPanel() {
        const id = panelMessageId, version = ++panelVersion;
        if (!id) return;
        try {
            const { data } = await axios.get(`/chat/messages/${id}/reads`);
            if (version !== panelVersion || id !== panelMessageId) return;
            document.getElementById('chat-read-title').textContent = `Read by ${data.read_count} of ${data.eligible_reader_count}`;
            const list = document.getElementById('chat-read-list');
            list.replaceChildren();
            for (const reader of data.readers) {
                const row = document.createElement('li');
                const avatar = document.createElement('span');
                avatar.className = 'chat-reader-avatar';
                avatar.textContent = (reader.name || '?').slice(0, 1).toUpperCase();
                if (reader.avatar) {
                    const url = new URL(reader.avatar, window.location.href);
                    if (['http:', 'https:'].includes(url.protocol)) {
                        const image = document.createElement('img');
                        image.src = url.href; image.alt = ''; image.referrerPolicy = 'no-referrer';
                        image.onerror = () => image.remove();
                        avatar.append(image);
                    }
                }
                const info = document.createElement('span');
                const name = document.createElement('strong');
                name.textContent = reader.name;
                const time = document.createElement('time');
                time.dateTime = reader.read_at;
                time.textContent = new Date(reader.read_at).toLocaleString();
                info.append(name, time); row.append(avatar, info); list.append(row);
            }
            if (!data.readers.length) list.textContent = 'No current members have read this message.';
        } catch (error) {
            if (version !== panelVersion) return;
            if ([401, 403, 404, 419].includes(error.response?.status)) closePanel();
            else document.getElementById('chat-read-list').textContent = 'Could not load readers. Please try again.';
        }
    }

    function closePanel() {
        panelMessageId = null; panelVersion++;
        if (dialog()?.open) dialog().close();
        document.getElementById('chat-read-list')?.replaceChildren();
        if (returnFocus?.isConnected) returnFocus.focus();
        returnFocus = null;
    }

    function attach() {
        generation++; pending = false; refreshing = false; lastReadId = null;
        observer?.disconnect(); mutationObserver?.disconnect(); closePanel();
        container = document.querySelector('.messages-container');
        if (!container) return;
        observer = new IntersectionObserver(schedule, { root: container, threshold: [0, 0.25, 1] });
        const observeMessages = () => container.querySelectorAll('.message-item').forEach(node => observer.observe(node));
        observeMessages();
        mutationObserver = new MutationObserver(records => {
            observeMessages(); schedule();
            if (records.some(record => [...record.addedNodes].some(node => node.matches?.('.message-item')))) refresh();
            if (panelMessageId && !container.querySelector(`.read-status[data-message-id="${panelMessageId}"]`)) closePanel();
        });
        mutationObserver.observe(container, { childList: true, subtree: true });
        container.addEventListener('scroll', schedule, { passive: true });
        schedule(); refresh();
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('.read-status[data-is-group="1"][data-read="1"]');
        if (button && !button.disabled) {
            returnFocus = button; panelMessageId = button.dataset.messageId;
            document.getElementById('chat-read-title').textContent = 'Read by';
            document.getElementById('chat-read-list').textContent = 'Loading readers…';
            dialog().showModal(); loadPanel();
        }
        if (event.target.closest('[data-close-read-dialog]') || event.target === dialog()) closePanel();
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { schedule(); refresh(); } });
    window.addEventListener('focus', () => { schedule(); refresh(); });
    window.addEventListener('online', () => { schedule(); refresh(); });
    document.addEventListener('DOMContentLoaded', () => dialog()?.addEventListener('cancel', event => { event.preventDefault(); closePanel(); }));
    setInterval(() => { if (active()) { schedule(); refresh(); } }, 20000);
    window.ChatReads = { attach, schedule, refresh, close: closePanel, applyStatus, newestVisibleIncoming };
})();

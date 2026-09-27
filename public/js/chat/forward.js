(() => {
    window.ChatForward?.destroy?.();

    const controller = new AbortController();
    const selectedMessages = new Set();
    const selectedRooms = new Set();
    let activeFilter = 'all';
    let longPressTimer = null;
    let longPressTarget = null;

    const modalElement = () => document.getElementById('forwardMessageModal');
    const modal = () => window.bootstrap?.Modal.getOrCreateInstance(modalElement());

    function messageElement(id) {
        const messageId = Number(id);
        if (!Number.isSafeInteger(messageId) || messageId < 1) return null;
        return document.querySelector(`.message-item[data-message-id="${messageId}"]`);
    }

    function updateSelectionUi() {
        document.querySelectorAll('.message-item.is-forward-selected').forEach((item) => {
            if (!selectedMessages.has(String(item.dataset.messageId))) item.classList.remove('is-forward-selected');
        });
        selectedMessages.forEach((id) => messageElement(id)?.classList.add('is-forward-selected'));

        const bar = document.querySelector('[data-message-selection-bar]');
        const area = document.querySelector('.teams-chat-area');
        const count = selectedMessages.size;
        if (bar) bar.hidden = count === 0;
        area?.classList.toggle('is-selecting', count > 0);
        const label = bar?.querySelector('[data-selection-count]');
        if (label) label.textContent = `${count} selected`;
    }

    function resetSelection() {
        selectedMessages.clear();
        document.querySelectorAll('.message-item.is-forward-selected').forEach((item) => item.classList.remove('is-forward-selected'));
        document.querySelector('.teams-chat-area')?.classList.remove('is-selecting');
        const bar = document.querySelector('[data-message-selection-bar]');
        if (bar) bar.hidden = true;
    }

    function toggleMessage(item) {
        if (!item || item.dataset.forwardable !== '1') return;
        const id = String(item.dataset.messageId);
        selectedMessages.has(id) ? selectedMessages.delete(id) : selectedMessages.add(id);
        updateSelectionUi();
    }

    function updateRoomUi() {
        const root = modalElement();
        if (!root) return;
        root.querySelectorAll('[data-forward-room]').forEach((button) => {
            button.setAttribute('aria-pressed', selectedRooms.has(String(button.dataset.forwardRoom)) ? 'true' : 'false');
        });
        const count = selectedRooms.size;
        root.querySelector('[data-forward-submit]').disabled = count === 0;
        root.querySelector('[data-forward-destination-count]').textContent = count === 0
            ? 'Choose a conversation'
            : `${count} conversation${count === 1 ? '' : 's'} selected`;
    }

    function filterRooms() {
        const root = modalElement();
        if (!root) return;
        const query = root.querySelector('#forward-room-search').value.trim().toLowerCase();
        let shown = 0;
        root.querySelectorAll('[data-forward-room]').forEach((button) => {
            const matchesType = activeFilter === 'all' || button.dataset.forwardRoomType === activeFilter;
            const matchesQuery = !query || button.dataset.forwardRoomName.includes(query);
            button.hidden = !(matchesType && matchesQuery);
            if (!button.hidden) shown += 1;
        });
        root.querySelector('[data-forward-empty]').hidden = shown > 0;
    }

    function openForward(ids) {
        const root = modalElement();
        if (!root || ids.length === 0) return;
        selectedMessages.clear();
        ids.forEach((id) => selectedMessages.add(String(id)));
        selectedRooms.clear();
        activeFilter = 'all';
        root.querySelector('#forward-room-search').value = '';
        root.querySelector('#forward-note').value = '';
        root.querySelector('[data-forward-error]').hidden = true;
        root.querySelector('[data-forward-source-count]').textContent = `${ids.length} message${ids.length === 1 ? '' : 's'}`;
        const first = messageElement(ids[0]);
        root.querySelector('[data-forward-source-preview]').textContent = ids.length === 1 ? (first?.dataset.messagePreview || '') : 'Send these messages together';
        root.querySelectorAll('[data-forward-filter]').forEach((button) => {
            const active = button.dataset.forwardFilter === 'all';
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        updateRoomUi();
        filterRooms();
        modal()?.show();
    }

    function toast(message) {
        document.querySelector('.chat-forward-toast')?.remove();
        const element = document.createElement('div');
        element.className = 'chat-forward-toast';
        element.setAttribute('role', 'status');
        element.textContent = message;
        document.body.appendChild(element);
        window.setTimeout(() => element.remove(), 2600);
    }

    async function submitForward() {
        const root = modalElement();
        const submit = root?.querySelector('[data-forward-submit]');
        const error = root?.querySelector('[data-forward-error]');
        if (!root || !submit || selectedMessages.size === 0 || selectedRooms.size === 0) return;

        submit.disabled = true;
        submit.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Forwarding…';
        error.hidden = true;

        let response;
        try {
            response = await axios.post(root.dataset.forwardUrl, {
                message_ids: Array.from(selectedMessages).map(Number),
                room_ids: Array.from(selectedRooms).map(Number),
                note: root.querySelector('#forward-note').value,
            });
        } catch (requestError) {
            error.textContent = Object.values(requestError.response?.data?.errors || {})[0]?.[0]
                || requestError.response?.data?.message
                || 'The messages could not be forwarded. Try again.';
            error.hidden = false;
            return;
        } finally {
            submit.innerHTML = '<i class="ti ti-send" aria-hidden="true"></i> Forward';
            updateRoomUi();
        }

        modal()?.hide();
        for (const update of response.data.sidebar_updates || []) {
            window.updateConversationList?.(update);
        }

        const activeRoomId = String(window.chat?.activeRoomId || '');
        for (const message of response.data.messages || []) {
            if (String(message.room_id) !== activeRoomId || typeof window.appendIncomingMessage !== 'function') continue;
            try {
                await window.appendIncomingMessage({ message_id: message.id, room_id: message.room_id });
            } catch (displayError) {
                console.error('Forwarded message saved; display sync failed.', displayError);
            }
        }

        const forwarded = Number(response.data.forwarded_count || 0);
        toast(`${forwarded} message${forwarded === 1 ? '' : 's'} forwarded`);
        resetSelection();
    }

    document.addEventListener('click', (event) => {
        const forwardButton = event.target.closest('.forward-message-btn');
        if (forwardButton) {
            event.preventDefault();
            openForward([forwardButton.dataset.messageId]);
            return;
        }

        const selectButton = event.target.closest('.select-message-btn');
        if (selectButton) {
            event.preventDefault();
            toggleMessage(selectButton.closest('.message-item'));
            return;
        }

        if (event.target.closest('[data-selection-cancel]')) {
            resetSelection();
            return;
        }
        if (event.target.closest('[data-selection-forward]')) {
            openForward(Array.from(selectedMessages));
            return;
        }

        const room = event.target.closest('[data-forward-room]');
        if (room) {
            const id = String(room.dataset.forwardRoom);
            selectedRooms.has(id) ? selectedRooms.delete(id) : selectedRooms.add(id);
            updateRoomUi();
            return;
        }

        const filter = event.target.closest('[data-forward-filter]');
        if (filter) {
            activeFilter = filter.dataset.forwardFilter;
            modalElement().querySelectorAll('[data-forward-filter]').forEach((button) => {
                const active = button === filter;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            filterRooms();
            return;
        }

        if (event.target.closest('[data-forward-submit]')) {
            submitForward();
            return;
        }

        const item = event.target.closest('.teams-chat-area.is-selecting .message-item[data-forwardable="1"]');
        if (item && !event.target.closest('a, button, input, audio, video')) toggleMessage(item);
    }, { signal: controller.signal });

    document.addEventListener('input', (event) => {
        if (event.target.matches('#forward-room-search')) filterRooms();
    }, { signal: controller.signal });

    document.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || event.target.closest('a, button, input, textarea, audio, video')) return;
        const item = event.target.closest('.message-item[data-forwardable="1"]');
        if (!item) return;
        longPressTarget = item;
        longPressTimer = window.setTimeout(() => {
            toggleMessage(longPressTarget);
            navigator.vibrate?.(20);
            longPressTimer = null;
        }, 550);
    }, { signal: controller.signal });

    ['pointerup', 'pointercancel', 'pointermove'].forEach((eventName) => {
        document.addEventListener(eventName, () => {
            if (longPressTimer) window.clearTimeout(longPressTimer);
            longPressTimer = null;
            longPressTarget = null;
        }, { signal: controller.signal });
    });

    window.ChatForward = {
        resetSelection,
        destroy() {
            controller.abort();
            resetSelection();
        },
    };
})();

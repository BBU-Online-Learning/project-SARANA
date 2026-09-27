(() => {
    'use strict';

    const drafts = new Map();
    const container = document.getElementById('chat-room-container');
    let observedMessages = null;
    let messageObserver = null;
    let connection = null;
    let groupTrigger = null;

    function syncMessageList() {
        const empty = observedMessages?.querySelector('[data-empty-conversation]');
        if (empty) empty.hidden = !!observedMessages.querySelector('.message-item');
    }

    function observeMessages() {
        const messages = container?.querySelector('.messages-container');
        if (messages === observedMessages) return;
        messageObserver?.disconnect();
        observedMessages = messages;
        if (!messages) return;
        syncMessageList();
        messageObserver = new MutationObserver(syncMessageList);
        messageObserver.observe(messages, { childList: true, subtree: true });
    }

    function saveDraft() {
        const input = container?.querySelector('[name="body"]');
        const roomId = window.chat?.activeRoomId;
        if (input && roomId && !window.chat.editingMessageId) drafts.set(String(roomId), input.value);
    }

    function restoreDraft(roomId) {
        const input = container?.querySelector('[name="body"]');
        if (input) {
            input.value = drafts.get(String(roomId)) || '';
            window.resizeMessageComposer?.(input);
        }
        window.chat.editingMessageId = null;
        window.chat.replyingToMessageId = null;
        window.chat.replyingToMessageText = null;
        observeMessages();
    }

    function setLoading(loading) {
        const area = document.querySelector('.chat-room-area');
        if (!area) return;
        area.setAttribute('aria-busy', String(loading));
        area.querySelector('.chat-loading-skeleton')?.remove();
        if (loading) {
            const skeleton = document.createElement('div');
            skeleton.className = 'chat-loading-skeleton';
            skeleton.setAttribute('aria-hidden', 'true');
            skeleton.innerHTML = '<span></span><span></span><span></span><span></span>';
            area.append(skeleton);
        }
    }

    function filterRooms() {
        const term = document.getElementById('chat-search-input')?.value.trim().toLowerCase() || '';
        window.filterConversationList?.(term);
    }

    function closeEmoji(restoreFocus = false) {
        const picker = document.getElementById('chat-emoji-picker');
        const trigger = document.querySelector('[data-emoji-toggle]');
        if (picker) picker.hidden = true;
        trigger?.setAttribute('aria-expanded', 'false');
        if (restoreFocus) trigger?.focus();
    }

    async function openGroupInfo(trigger) {
        const dialog = document.getElementById('group-info-drawer');
        const roomId = window.chat?.activeRoomId;
        if (!dialog || !roomId) return;
        groupTrigger = trigger;
        dialog.showModal();
        const status = dialog.querySelector('[data-group-refresh-status]');
        if (status) status.textContent = 'Updating group information…';
        try {
            const response = await axios.get(`/chat/rooms/${roomId}`);
            if (!dialog.isConnected || !dialog.open || String(window.chat.activeRoomId) !== String(roomId)) return;
            const updated = new DOMParser().parseFromString(response.data.html, 'text/html').getElementById('group-info-drawer');
            if (updated) {
                dialog.innerHTML = updated.innerHTML;
                dialog.querySelector('[data-close-group-info]')?.focus();
            }
        } catch (error) {
            if (!dialog.isConnected || !dialog.open) return;
            if ([401, 403, 404, 419].includes(error.response?.status)) {
                dialog.close();
                window.revokeRoom?.(roomId);
            } else if (status) status.textContent = 'Could not refresh. Close and reopen to try again.';
        }
    }

    document.addEventListener('click', event => {
        const mobileActions = event.target.closest('[data-mobile-message-actions]');
        document.querySelectorAll('.teams-message.mobile-actions-open').forEach(message => {
            if (message === mobileActions?.closest('.teams-message')) return;
            message.classList.remove('mobile-actions-open');
            message.querySelector('[data-mobile-message-actions]')?.setAttribute('aria-expanded', 'false');
        });
        if (mobileActions) {
            const message = mobileActions.closest('.teams-message');
            const isOpen = message.classList.toggle('mobile-actions-open');
            mobileActions.setAttribute('aria-expanded', String(isOpen));
        }
        if (event.target.closest('.teams-message-toolbar')) {
            const message = event.target.closest('.teams-message');
            message?.classList.remove('mobile-actions-open');
            message?.querySelector('[data-mobile-message-actions]')?.setAttribute('aria-expanded', 'false');
        }
        if (event.target.closest('[data-start-voice-call]')) {
            event.target.closest('.chat-mobile-call-menu')?.removeAttribute('open');
        } else if (!event.target.closest('.chat-mobile-call-menu')) {
            document.querySelectorAll('.chat-mobile-call-menu[open]').forEach(menu => menu.removeAttribute('open'));
        }
        const filter = event.target.closest('[data-conversation-filter]');
        if (filter) {
            document.querySelectorAll('[data-conversation-filter]').forEach(button => button.setAttribute('aria-pressed', String(button === filter)));
            filterRooms();
        }
        const info = event.target.closest('[data-group-info]');
        if (info) openGroupInfo(info);
        if (event.target.closest('[data-close-group-info]')) {
            document.getElementById('group-info-drawer')?.close();
            groupTrigger?.focus();
        }
        if (event.target.closest('[data-chat-list]')) {
            document.body.classList.remove('chat-mobile-room');
            document.querySelector('.room-item.active')?.focus();
        }
        const emojiToggle = event.target.closest('[data-emoji-toggle]');
        const picker = document.getElementById('chat-emoji-picker');
        if (emojiToggle && picker) {
            picker.hidden = !picker.hidden;
            emojiToggle.setAttribute('aria-expanded', String(!picker.hidden));
            if (!picker.hidden) picker.querySelector('button')?.focus();
        }
        const emoji = event.target.closest('[data-chat-emoji]');
        if (emoji) {
            const input = container?.querySelector('textarea[name="body"]');
            if (input) {
                input.setRangeText(emoji.dataset.chatEmoji, input.selectionStart, input.selectionEnd, 'end');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            }
            closeEmoji();
        } else if (!emojiToggle && !event.target.closest('#chat-emoji-picker')) closeEmoji();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.teams-message.mobile-actions-open').forEach(message => {
                message.classList.remove('mobile-actions-open');
                message.querySelector('[data-mobile-message-actions]')?.setAttribute('aria-expanded', 'false');
            });
            document.querySelectorAll('.chat-mobile-call-menu[open]').forEach(menu => menu.removeAttribute('open'));
        }
        if (event.key === 'Escape' && document.getElementById('chat-emoji-picker')?.hidden === false) {
            event.preventDefault();
            closeEmoji(true);
        }
    });

    function showConnection(state) {
        const status = document.getElementById('chat-connection-status');
        if (!status) return;
        status.hidden = state === 'connected';
        status.textContent = state === 'connecting' ? 'Connecting to live chat…' : 'Live connection interrupted. Reconnecting… Your draft is kept.';
    }

    function bindConnection() {
        const candidate = window.Echo?.connector?.pusher?.connection;
        if (!candidate || candidate === connection) return;
        connection = candidate;
        connection.bind('state_change', ({ current }) => showConnection(current));
        showConnection(connection.state);
    }

    function fitViewport() {
        if (!window.visualViewport) return;
        const viewportTop = Number.isFinite(window.visualViewport.pageTop)
            ? window.visualViewport.pageTop
            : (window.scrollY || 0) + (window.visualViewport.offsetTop || 0);
        const visibleBottom = window.visualViewport.height + Math.max(0, viewportTop);
        document.body.style.setProperty('--chat-viewport-height', `${Math.ceil(visibleBottom)}px`);
    }

    window.ChatWorkspace = { saveDraft, restoreDraft, setLoading };
    if (container) new MutationObserver(observeMessages).observe(container, { childList: true });
    const sidebar = document.querySelector('.conversation-list');
    if (sidebar) new MutationObserver(filterRooms).observe(sidebar, { childList: true, subtree: true, characterData: true });
    window.visualViewport?.addEventListener('resize', fitViewport);
    window.visualViewport?.addEventListener('scroll', fitViewport);
    window.addEventListener('resize', fitViewport);
    window.addEventListener('scroll', fitViewport);
    window.addEventListener('online', bindConnection);
    window.addEventListener('offline', () => showConnection('disconnected'));
    document.addEventListener('DOMContentLoaded', bindConnection, { once: true });
    bindConnection();
    fitViewport();
    observeMessages();
})();

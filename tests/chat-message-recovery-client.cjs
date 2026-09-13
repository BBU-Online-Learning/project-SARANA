const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const optimisticMessages = new Map();
const notifications = [];
const input = { value: 'Keep this draft', focus() {} };
let editedLabel = null;
const editedBubble = { textContent: 'Original message', dataset: { messageBody: 'Original message' }, classList: classes([]) };
const editedButton = { dataset: { messageBody: 'Original message' } };
const editedMeta = {
    querySelector: () => editedLabel,
    insertAdjacentHTML() { editedLabel = { remove() { editedLabel = null; } }; },
};
const editedMessage = {
    querySelector(selector) {
        if (selector === '.teams-message-bubble') return editedBubble;
        if (selector === '.edit-message-btn') return editedButton;
        if (selector === '.teams-edited-label') return editedLabel;
        if (selector === '.teams-message-meta span') return editedMeta;
        return null;
    },
};

function classes(initial) {
    const values = new Set(initial);
    return {
        add: value => values.add(value),
        remove: value => values.delete(value),
        contains: value => values.has(value),
    };
}

const container = {
    scrollHeight: 500,
    scrollTop: 0,
    insertAdjacentHTML(_position, html) {
        const clientId = html.match(/data-client-id="([^"]+)"/)?.[1];
        if (!clientId) return;

        const status = { textContent: '', setAttribute(name, value) { this[name] = value; } };
        const retry = { hidden: true, dataset: { clientId } };
        optimisticMessages.set(clientId, {
            classList: classes(['message-pending']),
            querySelector(selector) {
                if (selector === '.message-send-status') return status;
                if (selector === '.retry-message-btn') return retry;
                return null;
            },
            status,
            retry,
        });
    },
};

const form = {
    matches: selector => selector === '#message-form',
    querySelector: selector => selector === 'input[name="body"]' ? input : null,
};

const document = {
    addEventListener(name, callback) {
        listeners[name] = listeners[name] || [];
        listeners[name].push(callback);
    },
    querySelector(selector) {
        if (selector === '.messages-container') return container;
        if (selector === '#message-form input[name="body"]' || selector === "#message-form input[name='body']") return input;
        if (selector === '[data-message-id="55"]') return editedMessage;
        const clientId = selector.match(/^\[data-client-id="([^"]+)"\]$/)?.[1];
        return clientId ? optimisticMessages.get(clientId) || null : null;
    },
    querySelectorAll() { return []; },
    getElementById() { return null; },
    createElement() {
        return {
            textContent: '',
            get innerHTML() { return this.textContent; },
        };
    },
};

let shouldFail = true;
let postCount = 0;
const posts = [];
const axios = {
    async post(url, payload) {
        postCount++;
        posts.push({ url, payload });
        if (shouldFail) throw new Error('Offline');
        return { data: { success: true } };
    },
    async put() { throw new Error('Edit offline'); },
};

const window = {
    chat: {
        activeRoomId: 4,
        currentUserInitial: 'A',
        currentUserName: 'Alice',
        allowedReactions: [],
        stickers: [{ id: 'star_thumbs_up', name: 'Great job', url: '/images/stickers/star-thumbs-up.webp' }],
        replyingToMessageId: null,
        replyingToMessageText: null,
        voiceDraftFile: null,
        isSendingMessage: false,
    },
    ChatAttachments: null,
    ChatVoice: null,
    AppConfirm: { ask: async () => true },
    AppNotifications: { fromAxios: (_error, message) => notifications.push(message) },
};

const lanMode = process.argv.includes('--lan');
const expectedUuid = lanMode ? '00000000-0000-4000-8000-000000000000' : 'failed-uuid';
const browserCrypto = lanMode
    ? { getRandomValues: bytes => bytes.fill(0) }
    : { randomUUID: () => expectedUuid };
const context = vm.createContext({ window, document, axios, crypto: browserCrypto, console: { ...console, error() {} }, setTimeout, clearTimeout });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/messages.js'), 'utf8'), context);

async function dispatch(name, event) {
    for (const listener of listeners[name] || []) await listener(event);
}

(async () => {
    await dispatch('submit', { target: form, preventDefault() {} });

    const failed = optimisticMessages.get(expectedUuid);
    assert.equal(postCount, 1);
    assert.equal(input.value, 'Keep this draft');
    assert(failed.classList.contains('message-failed'));
    assert.equal(failed.retry.hidden, false);
    assert.equal(failed.status['aria-label'], 'Failed to send');
    assert.match(notifications[0], /draft was kept/i);

    shouldFail = false;
    await dispatch('click', {
        target: { closest: selector => selector === '.retry-message-btn' ? failed.retry : null },
    });

    assert.equal(postCount, 2);
    assert.equal(input.value, '');
    assert(failed.classList.contains('message-pending'));
    assert.equal(failed.retry.hidden, true);
    assert.equal(failed.status['aria-label'], 'Sending');

    window.chat.editingMessageId = '55';
    input.value = 'Changed while offline';
    await dispatch('submit', { target: form, preventDefault() {} });
    assert.equal(editedBubble.textContent, 'Original message');
    assert.equal(editedButton.dataset.messageBody, 'Original message');
    assert.equal(window.chat.editingMessageId, '55');
    assert.equal(input.value, 'Changed while offline');

    window.chat.editingMessageId = null;
    await window.ChatMessages.sendSticker('star_thumbs_up');
    assert.equal(postCount, 3);
    assert.equal(posts[2].url, '/chat/rooms/4/messages');
    assert.equal(posts[2].payload.sticker_id, 'star_thumbs_up');
    console.log('Failed message draft recovery and retry checks passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

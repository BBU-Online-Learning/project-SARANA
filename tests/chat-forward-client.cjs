const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const classList = () => ({ add() {}, remove() {}, toggle() {} });
const sourceCount = { textContent: '' };
const sourcePreview = { textContent: '' };
const destinationCount = { textContent: '' };
const submit = { disabled: true, innerHTML: '' };
const errorBox = { hidden: true, textContent: '' };
const search = { value: '' };
const note = { value: '' };
const empty = { hidden: true };
const room = {
    hidden: false,
    dataset: { forwardRoom: '8', forwardRoomType: 'group', forwardRoomName: 'study group' },
    attributes: {},
    setAttribute(name, value) { this.attributes[name] = value; },
};
const filter = {
    dataset: { forwardFilter: 'all' },
    classList: classList(),
    setAttribute() {},
};
const root = {
    dataset: { forwardUrl: '/chat/messages/forward' },
    querySelector(selector) {
        return {
            '#forward-room-search': search,
            '#forward-note': note,
            '[data-forward-source-count]': sourceCount,
            '[data-forward-source-preview]': sourcePreview,
            '[data-forward-destination-count]': destinationCount,
            '[data-forward-submit]': submit,
            '[data-forward-error]': errorBox,
            '[data-forward-empty]': empty,
        }[selector] || null;
    },
    querySelectorAll(selector) {
        if (selector === '[data-forward-room]') return [room];
        if (selector === '[data-forward-filter]') return [filter];
        return [];
    },
};
const message = {
    dataset: { messageId: '4', messagePreview: 'Lesson notes', forwardable: '1' },
    classList: classList(),
};

let request;
let shown = false;
let hidden = false;
let toast;
let sidebarUpdate;
let displayErrorLogged = false;
const modalApi = { show() { shown = true; }, hide() { hidden = true; } };
const body = { appendChild(element) { toast = element; } };
const document = {
    addEventListener(name, callback) { listeners[name] = callback; },
    getElementById(id) { return id === 'forwardMessageModal' ? root : null; },
    querySelector(selector) {
        if (selector.includes('.message-item[data-message-id=')) return message;
        if (selector === '.chat-forward-toast') return null;
        return null;
    },
    querySelectorAll() { return []; },
    createElement() {
        return { className: '', textContent: '', setAttribute() {}, remove() {} };
    },
    body,
};
const window = {
    bootstrap: { Modal: { getOrCreateInstance: () => modalApi } },
    chat: { activeRoomId: 8 },
    updateConversationList(update) { sidebarUpdate = update; },
    async appendIncomingMessage() { throw new Error('Temporary display failure'); },
    setTimeout(callback) { callback(); return 1; },
    clearTimeout() {},
};
const axios = {
    async post(url, payload) {
        request = { url, payload };
        return { data: {
            forwarded_count: 1,
            messages: [{ id: 22, room_id: 8 }],
            sidebar_updates: [{ room_id: 8, body: 'Lesson notes', sender: 'Me', created_at: 'now' }],
        } };
    },
};
const context = vm.createContext({
    window,
    document,
    axios,
    navigator: {},
    AbortController,
    console: { error() { displayErrorLogged = true; } },
});

vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/forward.js'), 'utf8'), context);

const target = matches => ({ closest: selector => matches[selector] || null });
listeners.click({ target: target({ '.forward-message-btn': { dataset: { messageId: '4' } } }), preventDefault() {} });
assert.equal(shown, true);
assert.equal(sourceCount.textContent, '1 message');
assert.equal(sourcePreview.textContent, 'Lesson notes');

listeners.click({ target: target({ '[data-forward-room]': room }) });
assert.equal(room.attributes['aria-pressed'], 'true');
assert.equal(submit.disabled, false);

note.value = 'Please read';
listeners.click({ target: target({ '[data-forward-submit]': submit }) });

setImmediate(() => {
    assert.equal(request.url, '/chat/messages/forward');
    assert.equal(JSON.stringify(request.payload), JSON.stringify({ message_ids: [4], room_ids: [8], note: 'Please read' }));
    assert.equal(hidden, true);
    assert.equal(toast.textContent, '1 message forwarded');
    assert.equal(sidebarUpdate.room_id, 8);
    assert.equal(sidebarUpdate.body, 'Lesson notes');
    assert.equal(displayErrorLogged, true);
    assert.equal(errorBox.hidden, true);
    console.log('Forward picker selection and submission checks passed.');
});

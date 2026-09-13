const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const calls = [];
const listeners = {};
const bodyListeners = {};
const title = {};
const sidebarTitle = {};
const loadStatus = { hidden: true, textContent: '', classList: { toggle() {} } };
const container = { replaceChildren: node => calls.push(['warning', node.textContent]) };
const channel = () => ({
    listen() { return this; }, listenForWhisper() { return this; }, stopListening() { return this; },
    here() { return this; }, joining() { return this; }, leaving() { return this; },
});
const window = {
    chat: { activeRoomId: 7, roomType: 'group', membershipId: 11, currentUserId: 5 },
    Echo: {
        private(topic) { calls.push(['private', topic]); return channel(); },
        join(topic) { calls.push(['join', topic]); return channel(); },
        leave(topic) { calls.push(['leave', topic]); },
    },
    ChatVoice: { reset: () => calls.push(['voice-reset']) },
    addEventListener(name, callback) { listeners[name] = callback; },
    location: { reload: () => calls.push(['reload']) },
};
const document = {
    readyState: 'complete',
    addEventListener() {},
    body: { addEventListener(name, callback) { bodyListeners[name] = callback; } },
    querySelector(selector) {
        if (selector.includes('h1')) return title;
        if (selector.includes('.room-name')) return sidebarTitle;
        return { remove: () => calls.push(['remove-item']) };
    },
    querySelectorAll() { return [{ pause: () => calls.push(['pause-audio']) }]; },
    getElementById(id) {
        if (id === 'chat-load-status') return loadStatus;
        return id === 'chat-room-container' ? container : { click: () => calls.push(['close-preview']) };
    },
    createElement() { return {}; },
};
const axios = { get: async () => ({ data: { membership_id: 12, type: 'group', name: '<Safe text>' } }) };
const context = vm.createContext({ window, document, axios, console: { ...console, error() {} }, AbortController, setInterval() {}, setTimeout, clearTimeout });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/chat.js'), 'utf8'), context);
(async () => {
    assert(calls.some(call => call[0] === 'private' && call[1] === 'user.5'));
    assert(calls.some(call => call[0] === 'join' && call[1] === 'online'));
    context.subscribeToRoom(7, null);
    assert(calls.some(call => call[0] === 'private' && call[1] === 'chat.membership.11'));
    await context.refreshRoomMembership();
    assert(calls.some(call => call[0] === 'leave' && call[1] === 'chat.membership.11'));
    assert(calls.some(call => call[0] === 'private' && call[1] === 'chat.membership.12'));
    assert.equal(title.textContent, '<Safe text>');
    assert.equal(sidebarTitle.textContent, '<Safe text>');
    window.chat.roomType = 'direct';
    context.subscribeToRoom(8, 7);
    assert(calls.some(call => call[0] === 'join' && call[1] === 'chat.room.8'));
    window.chat.activeRoomId = 8;
    axios.get = async () => { throw { response: { status: 403 } }; };
    await context.refreshRoomMembership();
    assert.equal(window.chat.activeRoomId, null);
    assert.equal(window.chat.channel, null);
    let rejectLoad;
    axios.get = () => new Promise((_resolve, reject) => { rejectLoad = reject; });
    const loading = context.loadRoom(12);
    assert(loadStatus.textContent.includes('Loading conversation'));
    rejectLoad({ response: { status: 503 } });
    await loading;
    assert(!loadStatus.hidden && loadStatus.textContent.includes('Could not open this conversation'));
    for (const action of ['remove-item', 'voice-reset', 'pause-audio', 'close-preview', 'warning']) {
        assert(calls.some(call => call[0] === action), action);
    }
    window.chat.activeRoomId = 9;
    axios.get = async () => ({ data: '<login page>' });
    await context.refreshRoomMembership();
    assert.equal(window.chat.activeRoomId, null);
    window.chat.activeRoomId = 10;
    axios.get = async () => { window.chat.activeRoomId = 11; return { data: { membership_id: 99 } }; };
    await context.refreshRoomMembership();
    assert.equal(window.chat.activeRoomId, 11);
    assert.notEqual(window.chat.membershipId, 99);
    listeners.pageshow({ persisted: true });
    assert(calls.some(call => call[0] === 'reload'));
    assert.equal(context.isNearMessagesBottom({ scrollHeight: 500, scrollTop: 250, clientHeight: 200 }), true);
    assert.equal(context.isNearMessagesBottom({ scrollHeight: 500, scrollTop: 100, clientHeight: 200 }), false);
    context.testLoads = [];
    vm.runInContext('loadRoom = async (roomId) => testLoads.push(roomId)', context);
    let keyboardPrevented = false;
    const keyboardRoom = { dataset: { roomId: '44' } };
    await bodyListeners.keydown({
        key: 'Enter',
        preventDefault() { keyboardPrevented = true; },
        target: { closest: selector => selector === '.room-item' ? keyboardRoom : null },
    });
    assert(keyboardPrevented);
    assert.deepEqual(Array.from(context.testLoads), ['44']);
    window.Echo = undefined;
    context.subscribeToRoom(11, 10);
    assert.equal(window.chat.channel, null);
    console.log('Group/direct subscription, revocation, safe rename, and navigation-race checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

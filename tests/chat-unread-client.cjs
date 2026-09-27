const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/js/chat/chat.js', 'utf8');
const classes = new Set(['workspace-chat', 'chat-mobile-room']);
let focused = true;
let mobile = false;
let unreadFilter = false;
let badge = null;
let filtered = 0;
const preview = { querySelector: () => badge, appendChild(node) { badge = node; } };
const message = { innerText: '' };
const time = { innerText: '' };
const room = {
    dataset: { lastMessageId: '0' },
    parentElement: { prepend() {} },
    querySelector(selector) {
        if (selector === '.teams-room-preview-row') return preview;
        if (selector === '.room-last-message') return message;
        if (selector === '.room-last-time') return time;
        return null;
    },
};
const document = {
    readyState: 'loading', hidden: false,
    body: { classList: { contains: name => classes.has(name) } },
    hasFocus: () => focused,
    addEventListener() {},
    getElementById: () => ({ value: '' }),
    querySelector(selector) {
        if (selector === '.room-item[data-room-id="7"]') return room;
        if (selector === '#chat-room-container .messages-container') return {};
        if (selector === '[data-conversation-filter="unread"][aria-pressed="true"]') return unreadFilter ? {} : null;
        return null;
    },
    createElement() {
        return { textContent: '', className: '', remove() { badge = null; } };
    },
};
const window = {
    chat: { activeRoomId: 7 },
    matchMedia: () => ({ matches: mobile }),
    filterConversationList() { filtered++; },
    addEventListener() {},
};
const context = vm.createContext({ document, window, console, setInterval() {}, setTimeout() {} });
vm.runInContext(source, context);

assert.equal(window.ChatRealtime.isRoomVisible(7), true);
assert.equal(window.ChatRealtime.isRoomVisible(8), false);
classes.delete('workspace-chat');
assert.equal(window.ChatRealtime.isRoomVisible(7), false, 'cached chat is not visible on another page');
classes.add('workspace-chat');
focused = false;
assert.equal(window.ChatRealtime.isRoomVisible(7), false, 'an unfocused tab can notify');
focused = true;
document.hidden = true;
assert.equal(window.ChatRealtime.isRoomVisible(7), false);
document.hidden = false;
mobile = true;
classes.delete('chat-mobile-room');
assert.equal(window.ChatRealtime.isRoomVisible(7), false, 'the mobile conversation list is not an open room');
classes.add('chat-mobile-room');
assert.equal(window.ChatRealtime.isRoomVisible(7), true);

context.handleUnreadCountUpdated({ room_id: 7, unread_count: 2 });
assert.equal(badge.textContent, 2);
unreadFilter = true;
context.updateConversationList({ room_id: 7, message_id: 12, sender: 'Teacher', body: 'Hello', created_at: 'now', unread_count: 3 });
assert.equal(message.innerText, 'Teacher: Hello');
assert.equal(time.innerText, 'now');
assert.equal(badge.textContent, 3, 'the sidebar event updates the badge without a refresh');
assert.equal(filtered, 1, 'the unread filter refreshes with the badge');
context.updateConversationList({ room_id: 7, message_id: 11, sender: 'Teacher', body: 'Older', created_at: 'earlier' });
assert.equal(message.innerText, 'Teacher: Hello', 'an older event cannot replace the latest message');
context.handleUnreadCountUpdated({ room_id: 7, unread_count: 0 });
assert.equal(badge, null);
assert.equal(filtered, 2);

console.log('Chat active-room visibility and live unread badges passed.');

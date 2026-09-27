const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const events = {};
const picker = { hidden: true, querySelector: () => ({ focus() {} }) };
const trigger = { setAttribute(name, value) { this[name] = value; }, focus() {} };
const input = { value: 'Draft for room one', selectionStart: 0, selectionEnd: 0,
    setRangeText(text) { this.value = text + this.value; }, dispatchEvent() {}, focus() {} };
const messageRows = [
    { dataset: { senderId: '1', createdAt: '100' }, classList: { toggle() { throw new Error('Messages must stay separate'); } } },
    { dataset: { senderId: '1', createdAt: '200' }, classList: { toggle() { throw new Error('Messages must stay separate'); } } },
];
const emptyConversation = { hidden: false };
const messageList = {
    querySelector: selector => selector === '[data-empty-conversation]' ? emptyConversation : selector === '.message-item' ? messageRows[0] : null,
    querySelectorAll: selector => selector === '.message-item' ? messageRows : [],
};
const container = { querySelector: selector => selector === '.messages-container' ? messageList : selector.includes('body') ? input : null };
const status = { hidden: true, textContent: '' };
const filters = ['all', 'direct', 'group', 'unread'].map(type => ({ dataset: { conversationFilter: type },
    pressed: type === 'all', setAttribute(name, value) { if (name === 'aria-pressed') this.pressed = value === 'true'; } }));
function room(type, name, unread = false) {
    return { dataset: { roomType: type }, style: {}, querySelector(selector) {
        if (selector === '.room-name') return { textContent: name };
        if (selector === '.unread-badge') return unread ? {} : null;
        return null;
    } };
}
const rooms = [room('direct', 'Dara'), room('group', 'Web Development', true)];
const empty = { style: {} };
const elements = { 'chat-room-container': container, 'chat-emoji-picker': picker, 'chat-connection-status': status, 'conversation-search-empty': empty };
const bodyClasses = new Set(['chat-mobile-room']);
const activeRoom = { focus() { this.focused = true; } };
const mobileActionClasses = new Set();
const mobileActionButton = {
    setAttribute(name, value) { this[name] = value; },
    closest(selector) {
        if (selector === '[data-mobile-message-actions]') return this;
        if (selector === '.teams-message') return mobileMessage;
        return null;
    },
};
const mobileMessage = {
    classList: {
        toggle(name) {
            if (mobileActionClasses.has(name)) mobileActionClasses.delete(name);
            else mobileActionClasses.add(name);
            return mobileActionClasses.has(name);
        },
        remove(name) { mobileActionClasses.delete(name); },
    },
    querySelector: selector => selector === '[data-mobile-message-actions]' ? mobileActionButton : null,
};
const viewportStyles = {};
const viewportEvents = {};
const windowEvents = {};
const visualViewport = {
    height: 780,
    offsetTop: 0,
    pageTop: 0,
    addEventListener(name, callback) { viewportEvents[name] = callback; },
};
const document = {
    body: { style: { setProperty(name, value) { viewportStyles[name] = value; } }, classList: { remove: key => bodyClasses.delete(key) } },
    getElementById: id => elements[id] || null,
    querySelector: selector => selector.includes('aria-pressed') ? filters.find(button => button.pressed) : selector === '[data-emoji-toggle]' ? trigger : selector === '.room-item.active' ? activeRoom : null,
    querySelectorAll: selector => selector === '[data-conversation-filter]' ? filters
        : selector === '.teams-message.mobile-actions-open' ? (mobileActionClasses.has('mobile-actions-open') ? [mobileMessage] : [])
        : selector.includes('.room-item') ? rooms : [],
    addEventListener(name, callback) { (events[name] ||= []).push(callback); },
};
const connection = { state: 'connected', bind(name, callback) { this.callback = callback; } };
const window = { chat: { activeRoomId: 1 }, visualViewport, addEventListener(name, callback) { windowEvents[name] = callback; }, Echo: { connector: { pusher: { connection } } } };
const context = vm.createContext({ document, window, console, Event: class {}, MutationObserver: class { observe() {} disconnect() {} }, setTimeout, clearTimeout });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/search.js'), 'utf8'), context);
window.filterConversationList = context.filterConversationList;
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/workspace-ui.js'), 'utf8'), context);
const ui = window.ChatWorkspace;
assert.equal(viewportStyles['--chat-viewport-height'], '780px');
visualViewport.height = 430;
visualViewport.offsetTop = 96;
visualViewport.pageTop = 96;
viewportEvents.resize();
assert.equal(viewportStyles['--chat-viewport-height'], '526px');
visualViewport.offsetTop = 112;
visualViewport.pageTop = 112;
viewportEvents.scroll();
assert.equal(viewportStyles['--chat-viewport-height'], '542px');
windowEvents.resize();
assert.equal(viewportStyles['--chat-viewport-height'], '542px');
visualViewport.pageTop = 160;
windowEvents.scroll();
assert.equal(viewportStyles['--chat-viewport-height'], '590px');
assert.equal(emptyConversation.hidden, true, 'consecutive messages remain separate');
assert.equal(ui.canGroup, undefined);
ui.saveDraft();
window.chat.activeRoomId = 2;
ui.restoreDraft(2);
assert.equal(input.value, '');
input.value = 'Room two';
ui.saveDraft();
ui.restoreDraft(1);
assert.equal(input.value, 'Draft for room one');
ui.restoreDraft(2);
assert.equal(input.value, 'Room two');
function click(selector, target) {
    events.click.forEach(callback => callback({ target: target?.closest ? target : { closest: query => query === selector ? target : null } }));
}
click('[data-mobile-message-actions]', mobileActionButton);
assert(mobileActionClasses.has('mobile-actions-open'));
assert.equal(mobileActionButton['aria-expanded'], 'true');
click('[data-mobile-message-actions]', mobileActionButton);
assert(!mobileActionClasses.has('mobile-actions-open'));
click('[data-mobile-message-actions]', mobileActionButton);
events.keydown.forEach(callback => callback({ key: 'Escape' }));
assert(!mobileActionClasses.has('mobile-actions-open'));
assert.equal(mobileActionButton['aria-expanded'], 'false');
click('[data-conversation-filter]', filters[2]);
assert.equal(rooms[0].style.display, 'none');
assert.equal(rooms[1].style.display, '');
context.filterConversationList('missing');
assert.equal(empty.style.display, 'block');
click('[data-conversation-filter]', filters[3]);
assert.equal(rooms[0].style.display, 'none');
assert.equal(rooms[1].style.display, '');
click('[data-emoji-toggle]', trigger);
assert.equal(picker.hidden, false);
click('[data-chat-emoji]', { dataset: { chatEmoji: '👍' } });
assert.equal(input.value, '👍Room two');
assert.equal(picker.hidden, true);
click('[data-chat-list]', {});
assert(!bodyClasses.has('chat-mobile-room'));
assert(activeRoom.focused);
connection.callback({ current: 'unavailable' });
assert.equal(status.hidden, false);
connection.callback({ current: 'connected' });
assert.equal(status.hidden, true);
console.log('Chat workspace separate messages, filters, drafts, emoji, and reconnect checks passed.');

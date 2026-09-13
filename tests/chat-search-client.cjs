const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const trigger = {
    attributes: { 'aria-expanded': 'false' },
    hidden: false,
    focused: false,
    focus() { this.focused = true; },
    setAttribute(name, value) { this.attributes[name] = value; },
};
const bar = { hidden: true };
const input = { value: '', focused: false, focus() { this.focused = true; } };
const count = { textContent: '' };
const elements = {
    'open-room-search-btn': trigger,
    'room-search-bar': bar,
    'room-search-input': input,
    'room-search-count': count,
};
const document = {
    addEventListener(name, callback) { listeners[name] = callback; },
    getElementById(id) { return elements[id] || null; },
    querySelectorAll() { return []; },
    querySelector() { return null; },
};
const window = { chat: { activeRoomId: 7, nextCursor: null } };
let resolveSearch;
const axios = { get: () => new Promise(resolve => { resolveSearch = resolve; }) };
const context = vm.createContext({ window, document, axios, console, setTimeout, clearTimeout });

vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/search.js'), 'utf8'), context);

const clickTarget = selector => ({ closest: candidate => candidate === selector ? {} : null });

listeners.click({ target: clickTarget('#open-room-search-btn') });
assert.equal(bar.hidden, false);
assert.equal(trigger.hidden, true);
assert.equal(trigger.attributes['aria-expanded'], 'true');
assert.equal(input.focused, true);

input.value = 'lesson';
listeners.click({ target: clickTarget('#open-room-search-btn') });
assert.equal(bar.hidden, true);
assert.equal(trigger.hidden, false);
assert.equal(trigger.attributes['aria-expanded'], 'false');
assert.equal(input.value, '');
assert.equal(trigger.focused, true);

listeners.click({ target: clickTarget('#open-room-search-btn') });
let escapePrevented = false;
listeners.keydown({ key: 'Escape', preventDefault() { escapePrevented = true; } });
assert.equal(bar.hidden, true);
assert.equal(escapePrevented, true);

listeners.click({ target: clickTarget('#open-room-search-btn') });
input.value = 'old room';
const pendingSearch = context.runRoomSearch('old room');
window.resetRoomSearchState();
resolveSearch({ data: { results: [{ message_id: 99 }] } });

pendingSearch.then(() => {
    assert.equal(bar.hidden, true);
    assert.equal(count.textContent, '');
    console.log('Room search toggle, Escape, room reset, and stale response checks passed.');
}).catch(error => {
    console.error(error);
    process.exitCode = 1;
});

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
const results = { hidden: true, children: [], replaceChildren() { this.children = []; }, append(child) { this.children.push(child); } };
const elements = {
    'open-room-search-btn': trigger,
    'room-search-bar': bar,
    'room-search-input': input,
    'room-search-count': count,
    'room-search-results': results,
};
const document = {
    addEventListener(name, callback) { listeners[name] = callback; },
    getElementById(id) { return elements[id] || null; },
    querySelectorAll() { return []; },
    querySelector() { return null; },
    createElement() { return { dataset: {}, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; } }; },
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

pendingSearch.then(async () => {
    assert.equal(bar.hidden, true);
    assert.equal(count.textContent, '');
    context.toggleRoomSearchBar();
    input.value = 'lesson';
    const matchingSearch = context.runRoomSearch('lesson');
    resolveSearch({ data: { results: [{ message_id: 98, snippet: '<img onerror=alert(1)> lesson' }, { message_id: 99, snippet: 'Next lesson' }] } });
    await matchingSearch;
    assert.equal(results.hidden, false);
    assert.equal(results.children[0].textContent, '<img onerror=alert(1)> lesson');
    assert.equal(results.children[1].attributes['aria-current'], 'true');
    listeners.click({ target: { closest: selector => selector === '[data-room-search-result]' ? results.children[0] : null } });
    assert.equal(count.textContent, '1/2');
    const clearedSearch = context.runRoomSearch('lesson');
    input.value = '';
    listeners.input({ target: { matches: () => true, value: '' } });
    resolveSearch({ data: { results: [{ message_id: 99 }] } });
    await clearedSearch;
    assert.equal(count.textContent, '');
    assert.equal(results.hidden, true);
    assert.equal(results.children.length, 0);
    console.log('Room search toggle, Escape, safe snippets, result selection, reset, and stale response checks passed.');
}).catch(error => {
    console.error(error);
    process.exitCode = 1;
});

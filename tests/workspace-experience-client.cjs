const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const listeners = {};
let submitted = 0;
let mobile = false;
const input = {
    style: {}, scrollHeight: 240,
    matches: selector => selector === '#message-form textarea[name="body"]',
    form: { requestSubmit() { submitted++; } },
};
const document = { addEventListener(name, callback) { (listeners[name] ||= []).push(callback); } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/messages.js'), 'utf8'), {
    document, window: { matchMedia: () => ({ matches: mobile }) },
});
function key(options = {}) {
    const event = { target: input, key: 'Enter', preventDefault() { this.prevented = true; }, ...options };
    listeners.keydown.forEach(callback => callback(event));
    return event;
}
assert(key().prevented);
assert.equal(submitted, 1);
assert(!key({ shiftKey: true }).prevented);
assert(!key({ isComposing: true }).prevented);
assert(!key({ keyCode: 229 }).prevented);
key({ repeat: true });
assert.equal(submitted, 1);
mobile = true;
assert(!key().prevented);
key({ ctrlKey: true });
assert.equal(submitted, 2);
listeners.input.forEach(callback => callback({ target: input }));
assert.equal(input.style.height, '160px');
input.scrollHeight = 38;
listeners.input.forEach(callback => callback({ target: input }));
assert.equal(input.style.height, '38px');

let toggle;
const password = { type: 'password' };
const button = { setAttribute(name, value) { this[name] = value; }, addEventListener(name, callback) { toggle = callback; } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/js/login.js'), 'utf8'), {
    document: { getElementById: id => id === 'password' ? password : button },
});
toggle({ currentTarget: button });
assert.equal(password.type, 'text');
assert.equal(button['aria-pressed'], 'true');
assert.equal(button.textContent, 'Hide password');
toggle({ currentTarget: button });
assert.equal(password.type, 'password');
assert.equal(button['aria-pressed'], 'false');
console.log('Workspace experience interaction checks passed.');

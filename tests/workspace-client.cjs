const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
function element() {
    const classes = new Set();
    return { hidden: false, disabled: false, textContent: '', dataset: {}, events: {}, attributes: {},
        classList: { add: key => classes.add(key), remove: key => classes.delete(key), contains: key => classes.has(key),
            toggle(key, value) { const enabled = value === undefined ? !classes.has(key) : value; enabled ? classes.add(key) : classes.delete(key); return enabled; } },
        addEventListener(name, callback) { this.events[name] = callback; },
        setAttribute(key, value) { this.attributes[key] = value; },
        focus() { this.focused = true; },
    };
}
const body = element(), toggle = element(), backdrop = element(), close = element(), firstLink = element(), chatMenu = element(), chatList = element();
const submit = element(), status = element(), form = element();
submit.dataset.pendingLabel = 'Saving...';
form.querySelector = selector => selector === '[data-pending-label]' ? submit : status;
const menu = { querySelector: () => firstLink, querySelectorAll: () => [firstLink, close], contains: el => [firstLink, close].includes(el) };
const documentEvents = {};
const document = { body, activeElement: firstLink,
    getElementById: () => menu,
    querySelector: selector => ({ '[data-shell-toggle]': toggle, '.workspace-backdrop': backdrop, '[data-chat-menu]': chatMenu, '[data-chat-list]': chatList })[selector],
    querySelectorAll: selector => selector === '[data-shell-close]' ? [close] : [form],
    addEventListener(name, callback) { (documentEvents[name] ||= []).push(callback); },
};
const context = vm.createContext({ document, requestAnimationFrame: callback => callback(), window: { addEventListener() {} } });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/workspace.js'), 'utf8'), context);
documentEvents.DOMContentLoaded[0]();
toggle.events.click();
assert(body.classList.contains('workspace-nav-open'));
assert.equal(toggle.attributes['aria-expanded'], 'true');
assert.equal(backdrop.hidden, false);
assert(firstLink.focused);
let prevented = false;
documentEvents.keydown[0]({ key: 'Tab', shiftKey: true, preventDefault() { prevented = true; } });
assert(prevented && close.focused);
close.events.click();
assert(!body.classList.contains('workspace-nav-open'));
assert.equal(backdrop.hidden, true);
chatMenu.events.click();
assert(body.classList.contains('chat-mobile-navigation'));
body.classList.add('chat-mobile-room');
chatList.events.click();
assert(!body.classList.contains('chat-mobile-navigation'));
assert(!body.classList.contains('chat-mobile-room'));
form.events.submit();
assert(submit.disabled);
assert.equal(submit.textContent, 'Saving...');
assert.equal(form.attributes['aria-busy'], 'true');
assert(status.textContent.includes('Please wait'));

(async () => {
    let handler;
    const direct = element(), group = element(), error = element();
    direct.id = 'create-direct-btn'; direct.textContent = 'Create direct';
    group.id = 'create-group-btn'; group.textContent = 'Create group';
    const controls = { 'create-chat-error': error, 'direct-user-id': { value: '' }, 'group-name': { value: 'Study' }, 'group-members': { selectedOptions: [] } };
    const axios = { post: async () => { throw { response: { data: { errors: { user_id: ['Choose a user.'] } } } }; } };
    const createContext = vm.createContext({ document: {
        addEventListener(_name, callback) { handler = callback; }, querySelectorAll: () => [direct, group], getElementById: id => controls[id],
    }, axios, bootstrap: { Modal: { getInstance: () => ({ hide() {} }) } }, showChatLoadStatus() {}, loadRoom: async () => {} });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/create-chat.js'), 'utf8'), createContext);
    await handler({ target: { closest: () => direct } });
    assert.equal(error.textContent, 'Choose a user.');
    assert(!error.hidden && !direct.disabled && !group.disabled);
    axios.post = async () => { throw new Error('Network offline'); };
    await handler({ target: { closest: () => group } });
    assert(error.textContent.includes('Check your connection'));
    assert(!group.disabled);
    axios.post = async () => ({ data: { room_id: 9 } });
    axios.get = async () => { throw new Error('Refresh failed'); };
    await handler({ target: { closest: () => group } });
    assert(error.textContent.includes('Conversation created'));
    assert(group.disabled && direct.disabled);
    console.log('Workspace menu, focus trap, mobile chat, pending forms and creation failure checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

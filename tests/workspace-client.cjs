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
const body = element(), toggle = element(), backdrop = element(), close = element(), firstLink = element(), chatList = element();
const submit = element(), status = element(), form = element();
submit.dataset.pendingLabel = 'Saving...';
form.querySelector = selector => selector === '[data-pending-label]' ? submit : status;
const menu = { querySelector: () => firstLink, querySelectorAll: () => [firstLink, close], contains: el => [firstLink, close].includes(el) };
const documentEvents = {};
const document = { body, activeElement: firstLink,
    getElementById: () => menu,
    querySelector: selector => ({ '[data-shell-toggle]': toggle, '.workspace-backdrop': backdrop, '[data-chat-list]': chatList })[selector],
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
body.classList.add('chat-mobile-room');
chatList.events.click();
assert(!body.classList.contains('chat-mobile-room'));
assert(!body.classList.contains('workspace-nav-open'));
assert.equal(toggle.attributes['aria-expanded'], 'false');
toggle.events.click();
assert(body.classList.contains('workspace-nav-open'));
documentEvents.keydown.forEach(handler => handler({ key: 'Escape' }));
assert(!body.classList.contains('workspace-nav-open'));
assert.equal(backdrop.hidden, true);
form.events.submit();
assert(submit.disabled);
assert.equal(submit.textContent, 'Saving...');
assert.equal(form.attributes['aria-busy'], 'true');
assert(status.textContent.includes('Please wait'));

// Deep links open the requested classroom disclosure without changing permissions.
{
    const learningBody = element();
    learningBody.classList.add('learning-workspace');
    const disclosure = { open: false, parentElement: { closest: () => null } };
    const section = { closest: () => disclosure, scrollIntoView() { this.scrolled = true; } };
    const events = {};
    const learningWindow = { location: { hash: '#class-actions' }, addEventListener(name, callback) { events[name] = callback; } };
    let ready;
    const learningDocument = {
        body: learningBody,
        querySelector: () => null,
        querySelectorAll: () => [],
        getElementById: id => id === 'class-actions' ? section : null,
        addEventListener(name, callback) { if (name === 'DOMContentLoaded') ready = callback; },
    };
    const learningContext = vm.createContext({ document: learningDocument, window: learningWindow });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/workspace.js'), 'utf8'), learningContext);
    ready();
    assert(disclosure.open && section.scrolled);
    disclosure.open = false;
    learningWindow.location.hash = '#missing';
    events.hashchange();
    assert(!disclosure.open);
    learningWindow.location.hash = '#%broken';
    assert.doesNotThrow(() => events.hashchange());
    learningWindow.location.hash = '#class-actions';
    events.hashchange();
    assert(disclosure.open);
}

console.log('Workspace menu, focus trap, mobile chat and pending form checks passed.');

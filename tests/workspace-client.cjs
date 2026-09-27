const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const workspaceSource = fs.readFileSync(path.join(__dirname, '../public/js/workspace.js'), 'utf8');
assert.match(workspaceSource, /loadPageStyles/);
assert.match(workspaceSource, /loadPageScripts/);
assert.match(workspaceSource, /cacheChatContent/);
assert.match(workspaceSource, /restoreChatContent/);
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
const body = element(), toggle = element(), backdrop = element(), close = element(), collapse = element(), firstLink = element(), chatList = element();
const collapseLabel = element(), collapseIcon = element();
collapseIcon.classList.add('ti-layout-sidebar-left-collapse');
collapse.querySelector = selector => selector === 'i' ? collapseIcon : collapseLabel;
const submit = element(), status = element(), form = element();
submit.dataset.pendingLabel = 'Saving...';
form.querySelector = selector => selector === '[data-pending-label]' ? submit : status;
const menu = { querySelector: () => firstLink, querySelectorAll: () => [firstLink, close], contains: el => [firstLink, close].includes(el) };
const documentEvents = {};
const document = { body, activeElement: firstLink,
    getElementById: () => menu,
    querySelector: selector => ({ '[data-shell-toggle]': toggle, '[data-shell-collapse]': collapse, '.workspace-backdrop': backdrop, '[data-chat-list]': chatList })[selector],
    querySelectorAll: selector => selector === '[data-shell-close]' ? [close] : [form],
    addEventListener(name, callback) { (documentEvents[name] ||= []).push(callback); },
};
const workspaceWindow = {
    SettingsPage: { refreshed: 0, refresh() { this.refreshed++; } },
    location: { href: 'http://localhost/home', origin: 'http://localhost', hash: '' },
    matchMedia: () => ({ matches: true, addEventListener() {} }),
    localStorage: { value: null, getItem() { return this.value; }, setItem(key, value) { this.value = value; } },
    addEventListener() {},
};
const context = vm.createContext({ document, requestAnimationFrame: callback => callback(), window: workspaceWindow, URL });
vm.runInContext(workspaceSource, context);
documentEvents.DOMContentLoaded[0]();
assert.equal(workspaceWindow.SettingsPage.refreshed, 1);
const softLink = {
    href: 'http://localhost/classes',
    target: '',
    matches: selector => selector === '[data-workspace-nav]',
    hasAttribute: () => false,
};
const normalClick = { button: 0, metaKey: false, ctrlKey: false, shiftKey: false, altKey: false };
assert(workspaceWindow.WorkspaceNavigation.canNavigate(softLink, normalClick));
assert(workspaceWindow.WorkspaceNavigation.canNavigate({ ...softLink, href: 'http://localhost/chat' }, normalClick));
assert(!workspaceWindow.WorkspaceNavigation.canNavigate(softLink, { ...normalClick, ctrlKey: true }));
assert(!workspaceWindow.WorkspaceNavigation.canNavigate({ ...softLink, matches: () => false }, normalClick));
collapse.events.click();
assert(body.classList.contains('workspace-sidebar-collapsed'));
assert.equal(collapse.attributes['aria-expanded'], 'false');
assert.equal(collapse.attributes['aria-label'], 'Expand navigation');
assert(collapseIcon.classList.contains('ti-layout-sidebar-left-expand'));
assert(!collapseIcon.classList.contains('ti-layout-sidebar-left-collapse'));
assert.equal(workspaceWindow.localStorage.value, 'true');
collapse.events.click();
assert(!body.classList.contains('workspace-sidebar-collapsed'));
assert.equal(collapse.attributes['aria-expanded'], 'true');
assert(collapseIcon.classList.contains('ti-layout-sidebar-left-collapse'));
assert(!collapseIcon.classList.contains('ti-layout-sidebar-left-expand'));
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
let duplicatePrevented = false;
form.events.submit({ preventDefault() { duplicatePrevented = true; } });
assert(duplicatePrevented);

// Deep links open the requested classroom disclosure without changing permissions.
{
    const learningBody = element();
    learningBody.classList.add('learning-workspace');
    const disclosure = { open: false, parentElement: { closest: () => null } };
    const section = { closest: () => disclosure, scrollIntoView() { this.scrolled = true; } };
    const events = {};
    const learningWindow = {
        location: { hash: '#class-actions' },
        matchMedia: () => ({ matches: true, addEventListener() {} }),
        localStorage: { getItem: () => null, setItem() {} },
        addEventListener(name, callback) { events[name] = callback; },
    };
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

// Returning through soft navigation keeps the open room but replaces its stale conversation list.
{
    const testSource = workspaceSource.replace(
        'window.WorkspaceNavigation = { canNavigate, navigate };',
        'window.WorkspaceNavigation = { canNavigate, navigate, cacheChatContent, restoreChatContent };',
    );
    const searchInput = { value: '' };
    const buttons = ['all', 'unread'].map(conversationFilter => ({
        dataset: { conversationFilter },
        setAttribute(name, value) { this[name] = value; },
    }));
    const activeRoom = { classList: { add(name) { this[name] = true; } }, setAttribute(name, value) { this[name] = value; } };
    const freshSidebar = {
        querySelector(selector) {
            if (selector === '#chat-search-input') return searchInput;
            if (selector === '.room-item[data-room-id="7"]') return activeRoom;
            return null;
        },
        querySelectorAll: () => buttons,
    };
    const main = {
        sidebar: null,
        replaceChildren(...nodes) { this.childNodes = nodes; this.sidebar = nodes[0]; },
        querySelector: () => main.sidebar,
    };
    const cachedSidebar = {
        querySelector(selector) {
            if (selector === '#chat-search-input') return { value: 'assignment' };
            if (selector.includes('aria-pressed')) return { dataset: { conversationFilter: 'unread' } };
            return null;
        },
        replaceWith(next) { main.sidebar = next; },
    };
    const restoreDocument = {
        addEventListener() {},
        createElement() { return { childNodes: [], replaceChildren(...nodes) { this.childNodes = nodes; }, hasChildNodes() { return this.childNodes.length > 0; } }; },
    };
    let restoredSearch;
    const restoreWindow = { chat: { activeRoomId: 7 }, addEventListener() {}, filterConversationList(value) { restoredSearch = value; } };
    vm.runInNewContext(testSource, { document: restoreDocument, window: restoreWindow });
    restoreWindow.WorkspaceNavigation.cacheChatContent({ childNodes: [cachedSidebar, {}] });
    assert(restoreWindow.WorkspaceNavigation.restoreChatContent(main, { querySelector: () => freshSidebar }));
    assert.equal(main.sidebar, freshSidebar);
    assert.equal(searchInput.value, 'assignment');
    assert.equal(buttons[1]['aria-pressed'], 'true');
    assert.equal(activeRoom['aria-current'], 'true');
    assert.equal(restoredSearch, 'assignment');
}

console.log('Workspace menu, focus trap, mobile chat and pending form checks passed.');

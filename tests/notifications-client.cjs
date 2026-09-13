const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function element(tagName = 'div') {
    const classes = new Set();
    return {
        tagName,
        children: [],
        dataset: {},
        attributes: {},
        events: {},
        textContent: '',
        className: '',
        classList: {
            add(name) { classes.add(name); },
            contains(name) { return classes.has(name); },
        },
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.events[name] = callback; },
        append(...children) { this.children.push(...children); },
        appendChild(child) { this.children.push(child); },
        remove() { this.removed = true; },
    };
}

const container = element();
const seed = element('span');
seed.dataset.type = 'success';
seed.textContent = 'Profile updated successfully.';
const timers = new Map();
let nextTimer = 1;
const document = {
    readyState: 'complete',
    getElementById(id) { return id === 'app-notifications' ? container : null; },
    querySelectorAll(selector) { return selector === '[data-notification-seed]' ? [seed] : []; },
    createElement,
};
function createElement(tagName) { return element(tagName); }
const window = {};
const context = vm.createContext({
    document,
    window,
    Date,
    requestAnimationFrame: callback => callback(),
    setTimeout(callback) { const id = nextTimer++; timers.set(id, callback); return id; },
    clearTimeout(id) { timers.delete(id); },
});

vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/notifications.js'), 'utf8'), context);

assert.equal(container.children.length, 1);
assert.equal(container.children[0].children[1].textContent, 'Profile updated successfully.');
assert.equal(container.children[0].attributes.role, 'status');
assert.equal(window.AppNotifications.success('Profile updated successfully.'), false, 'active duplicates are suppressed');
assert.equal(container.children.length, 1);

assert.equal(window.AppNotifications.error('<img src=x onerror=alert(1)>', { duration: 0 }), true);
const errorToast = container.children[1];
assert.equal(errorToast.attributes.role, 'alert');
assert.equal(errorToast.attributes['aria-live'], 'assertive');
assert.equal(errorToast.children[1].textContent, '<img src=x onerror=alert(1)>');
assert.equal(errorToast.children[2].attributes['aria-label'], 'Close notification');

window.AppNotifications.warning('Check this file.', { duration: 0 });
window.AppNotifications.info('Queued information.', { duration: 0 });
assert.equal(container.children.length, 3, 'only three notifications are visible at once');
container.children[0].children[2].events.click();
const removalTimer = Math.max(...timers.keys());
timers.get(removalTimer)();
assert.equal(container.children.filter(child => !child.removed).length, 3, 'the queued toast is shown after closing one');

assert.equal(
    window.AppNotifications.fromAxios({ response: { status: 403 } }, 'Fallback'),
    true,
);

console.log('Toast type, accessibility, escaping, deduplication, queue and close checks passed.');

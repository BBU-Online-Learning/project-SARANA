const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/js/chat/chat.js', 'utf8');
let currentContainer;
let nextFrame = 0;
const frames = new Map();
const jumpButton = { hidden: true, dataset: { count: '0' }, querySelector: () => null };
const document = {
    readyState: 'loading',
    addEventListener() {},
    getElementById: id => id === 'jump-to-latest-btn' ? jumpButton : null,
    querySelector: selector => selector === '.messages-container' ? currentContainer : null,
};
const window = { chat: {}, addEventListener() {} };
const context = vm.createContext({
    document, window, console,
    setInterval() {},
    requestAnimationFrame(callback) { frames.set(++nextFrame, callback); return nextFrame; },
    cancelAnimationFrame(id) { frames.delete(id); },
});
vm.runInContext(source, context);

function createContainer() {
    const listeners = new Map();
    let top = 0;
    return {
        scrollHeight: 500,
        clientHeight: 200,
        get scrollTop() { return top; },
        set scrollTop(value) { top = Math.min(value, this.scrollHeight - this.clientHeight); },
        addEventListener(name, callback) { listeners.set(name, callback); },
        removeEventListener(name) { listeners.delete(name); },
        fire(name, selector = '.message-attachment-thumb') {
            listeners.get(name)?.({ target: { matches: () => selector !== '.other' } });
        },
        hasListener(name) { return listeners.has(name); },
    };
}
function flushFrames() {
    const queued = [...frames.values()];
    frames.clear();
    queued.forEach(callback => callback());
}

const direct = createContainer();
currentContainer = direct;
context.initializeInfiniteScroll();
context.scrollMessagesToBottom();
flushFrames();
assert.equal(direct.scrollTop, 300);

direct.scrollHeight = 740;
direct.fire('load');
flushFrames();
assert.equal(direct.scrollTop, 540, 'a late photo keeps the open direct chat at the latest message');

direct.scrollTop = 180;
direct.onscroll();
direct.scrollHeight = 900;
direct.fire('loadedmetadata', '.video-attachment-card video');
flushFrames();
assert.equal(direct.scrollTop, 180, 'media loading does not interrupt someone reading older messages');

const group = createContainer();
currentContainer = group;
context.initializeInfiniteScroll();
context.scrollMessagesToBottom();
flushFrames();
assert.equal(direct.hasListener('load'), false, 'switching rooms releases the old media listeners');
group.scrollHeight = 760;
group.fire('load', '.sticker-message img');
flushFrames();
assert.equal(group.scrollTop, 560, 'a late sticker keeps the open group chat at the latest message');

console.log('Direct and group chat media scroll checks passed.');

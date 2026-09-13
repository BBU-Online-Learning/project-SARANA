const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const sent = [];

function classList() {
    const values = new Set();

    return {
        add: value => values.add(value),
        remove: value => values.delete(value),
        contains: value => values.has(value),
    };
}

const option = {
    dataset: { stickerId: 'star_thumbs_up' },
    focused: false,
    focus() { this.focused = true; },
};
const button = {
    classList: classList(),
    attributes: {},
    focused: false,
    setAttribute(name, value) { this.attributes[name] = value; },
    focus() { this.focused = true; },
};
const picker = {
    hidden: true,
    querySelector: selector => selector === '.sticker-option' ? option : null,
    contains: target => target === option,
};
const voiceOverlay = { hidden: true };

const document = {
    addEventListener(name, callback) {
        listeners[name] = listeners[name] || [];
        listeners[name].push(callback);
    },
    getElementById(id) {
        return {
            'sticker-picker-btn': button,
            'sticker-picker': picker,
            'voice-overlay': voiceOverlay,
        }[id] || null;
    },
    querySelectorAll() { return []; },
};

const window = {
    chat: { voiceDraftFile: null },
    ChatAttachments: { hasPending: () => false },
    ChatMessages: { sendSticker: async stickerId => sent.push(stickerId) },
    AppNotifications: { warning() {} },
};

const context = vm.createContext({ window, document });
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/stickers.js'), 'utf8'), context);

function targetFor(match, value) {
    return { closest: selector => selector === match ? value : null };
}

async function dispatch(name, event) {
    for (const listener of listeners[name] || []) await listener(event);
}

(async () => {
    await dispatch('click', { target: targetFor('#sticker-picker-btn', button) });
    assert.equal(picker.hidden, false);
    assert.equal(button.attributes['aria-expanded'], 'true');
    assert(button.classList.contains('is-active'));
    assert.equal(option.focused, true);

    await dispatch('keydown', { key: 'Escape', preventDefault() {} });
    assert.equal(picker.hidden, true);
    assert.equal(button.focused, true);

    await dispatch('click', { target: targetFor('#sticker-picker-btn', button) });
    await dispatch('click', { target: targetFor('.sticker-option', option) });
    assert.deepEqual(sent, ['star_thumbs_up']);
    assert.equal(picker.hidden, true);

    await dispatch('click', { target: targetFor('#sticker-picker-btn', button) });
    await dispatch('click', { target: targetFor('', null) });
    assert.equal(picker.hidden, true);

    console.log('Sticker picker open, close, keyboard, selection, and outside-click checks passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

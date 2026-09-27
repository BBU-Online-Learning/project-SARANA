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
    dataset: {
        stickerId: 'bbu_wave',
        stickerName: 'Hello',
        stickerPack: 'Dev Reactions',
        stickerUrl: 'http://localhost/images/stickers/bbu-reactions/wave.png',
    },
    focused: false,
    focus() { this.focused = true; },
    closest: selector => selector === '#sticker-picker' ? picker : null,
};
const studyPanel = { dataset: { stickerPackPanel: 'study-buddies' }, hidden: false };
const devPanel = { dataset: { stickerPackPanel: 'dev-reactions' }, hidden: true };
const studyTab = {
    dataset: { stickerPackTab: 'study-buddies' },
    attributes: {},
    setAttribute(name, value) { this.attributes[name] = value; },
};
const devTab = {
    dataset: { stickerPackTab: 'dev-reactions' },
    attributes: {},
    setAttribute(name, value) { this.attributes[name] = value; },
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
    querySelector: selector => selector === '.sticker-picker-grid:not([hidden]) .sticker-option' ? option : null,
    querySelectorAll(selector) {
        if (selector === '[data-sticker-pack-tab]') return [studyTab, devTab];
        if (selector === '[data-sticker-pack-panel]') return [studyPanel, devPanel];
        if (selector === '.sticker-option[data-sticker-id]') return [option];
        return [];
    },
    contains: target => target === option,
};
studyTab.closest = devTab.closest = selector => selector === '#sticker-picker' ? picker : null;
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
    chat: {
        voiceDraftFile: null,
        stickers: [{ id: 'star_thumbs_up', name: 'Great job', pack: 'Study Buddies', url: '/old.png' }],
    },
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
    assert.equal(JSON.stringify(window.chat.stickers), JSON.stringify([{
        id: 'bbu_wave',
        name: 'Hello',
        pack: 'Dev Reactions',
        url: 'http://localhost/images/stickers/bbu-reactions/wave.png',
    }]));

    await dispatch('keydown', { key: 'Escape', preventDefault() {} });
    assert.equal(picker.hidden, true);
    assert.equal(button.focused, true);

    await dispatch('click', { target: targetFor('[data-sticker-pack-tab]', devTab) });
    assert.equal(studyTab.attributes['aria-selected'], 'false');
    assert.equal(devTab.attributes['aria-selected'], 'true');
    assert.equal(studyPanel.hidden, true);
    assert.equal(devPanel.hidden, false);

    await dispatch('click', { target: targetFor('#sticker-picker-btn', button) });
    await dispatch('click', { target: targetFor('.sticker-option', option) });
    assert.deepEqual(sent, ['bbu_wave']);
    assert.equal(picker.hidden, true);

    await dispatch('click', { target: targetFor('#sticker-picker-btn', button) });
    await dispatch('click', { target: targetFor('', null) });
    assert.equal(picker.hidden, true);

    console.log('Sticker picker open, close, keyboard, selection, and outside-click checks passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

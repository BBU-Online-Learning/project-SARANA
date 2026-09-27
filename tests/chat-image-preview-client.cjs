const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const elements = {};
const classList = () => {
    const values = new Set();
    return {
        add(value) { values.add(value); },
        remove(value) { values.delete(value); },
        contains(value) { return values.has(value); },
    };
};
const element = () => ({
    classList: classList(),
    dataset: {},
    hidden: false,
    disabled: false,
    attributes: {},
    addEventListener(name, callback) { this.listeners ||= {}; this.listeners[name] = callback; },
    setAttribute(name, value) { this.attributes[name] = value; },
    removeAttribute(name) { delete this.attributes[name]; },
    focus() { document.activeElement = this; },
});

for (const id of [
    'image-preview-modal', 'image-preview-modal-img', 'image-preview-caption',
    'image-preview-close', 'image-preview-previous', 'image-preview-next',
    'image-preview-counter', 'image-preview-download', 'image-preview-status',
]) elements[id] = element();

const thumbs = ['/photo-1', '/photo-2', '/photo-3'].map((src, index) => ({
    dataset: { fullSrc: src, filename: `Photo ${index + 1}`, downloadSrc: `${src}/download` },
}));
const document = {
    activeElement: thumbs[0],
    body: { classList: classList() },
    getElementById(id) { return elements[id]; },
    querySelectorAll(selector) {
        assert.equal(selector, '#chat-room-container .messages-container .message-attachment-thumb');
        return thumbs;
    },
    addEventListener(name, callback) { (listeners[name] ||= []).push(callback); },
};
class ImageLoader {
    set src(value) {
        this.source = value;
        queueMicrotask(() => this.onload());
    }
}
const window = { AppNotifications: { error() { throw new Error('Image should load'); } } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/image-preview.js'), 'utf8'), {
    document, window, Image: ImageLoader, console,
});

const clickThumbnail = (thumb) => listeners.click[0]({ target: { closest: () => thumb } });
const keydown = (key) => listeners.keydown.forEach(listener => listener({
    key,
    target: { closest: () => null },
    preventDefault() {},
    shiftKey: false,
}));
const settleImage = () => new Promise(resolve => setImmediate(resolve));

(async () => {
    clickThumbnail(thumbs[0]);
    await settleImage();
    assert.equal(elements['image-preview-modal-img'].src, '/photo-1');
    assert.equal(elements['image-preview-counter'].textContent, '1 of 3');
    assert.equal(elements['image-preview-previous'].disabled, true);
    assert.equal(elements['image-preview-next'].disabled, false);

    elements['image-preview-next'].listeners.click();
    await settleImage();
    assert.equal(elements['image-preview-modal-img'].src, '/photo-2');
    assert.equal(elements['image-preview-download'].href, '/photo-2/download');

    keydown('ArrowRight');
    await settleImage();
    assert.equal(elements['image-preview-modal-img'].src, '/photo-3');
    assert.equal(elements['image-preview-next'].disabled, true);
    assert.equal(elements['image-preview-counter'].textContent, '3 of 3');

    keydown('ArrowLeft');
    await settleImage();
    assert.equal(elements['image-preview-modal-img'].src, '/photo-2');

    keydown('Escape');
    assert.equal(elements['image-preview-modal'].attributes['aria-hidden'], 'true');
    assert.equal(window.ChatImagePreview.current(), null);

    thumbs.splice(1);
    clickThumbnail(thumbs[0]);
    await settleImage();
    assert.equal(elements['image-preview-previous'].hidden, true);
    assert.equal(elements['image-preview-next'].hidden, true);
    keydown('Escape');
    console.log('Photo preview navigation checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

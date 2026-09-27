const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const status = { textContent: '' };
const input = { value: 'https://example.test/chat/invites/1?signature=safe' };
const qrImage = { src: 'data:image/svg+xml;base64,safe' };
const panel = {
    busy: false,
    classList: { toggle(name, value) { if (name === 'is-busy') panel.busy = value; } },
    setAttribute(name, value) { if (name === 'aria-busy') this.ariaBusy = value; },
    querySelector(selector) {
        return { '[data-group-invite-status]': status, '[data-group-invite-url]': input, '.group-invite-qr img': qrImage }[selector] || null;
    },
    replaceWith(next) { this.replacement = next; },
};
const nextPanel = { querySelector: selector => selector === '[data-group-invite-status]' ? status : null };
const form = {
    action: 'https://example.test/chat/groups/1/invite', method: 'post',
    closest: () => panel, matches: selector => selector.includes('data-group-invite-create'), querySelector: () => null,
};
let requestOptions;
let copied;
let downloaded;
let pngType;
const downloadLink = {
    click() { downloaded = this.download; },
    remove() {},
};
const context = vm.createContext({
    document: {
        addEventListener(name, callback) { listeners[name] = callback; },
        execCommand: () => true,
        createElement(tag) {
            if (tag === 'a') return downloadLink;
            return {
                width: 0, height: 0,
                getContext: () => ({ fillStyle: '', fillRect() {}, drawImage() {} }),
                toBlob(callback, type) { pngType = type; callback({ type }); },
            };
        },
        body: { append() {} },
    },
    navigator: { clipboard: { async writeText(value) { copied = value; } } },
    axios: { async request(options) { requestOptions = options; return { data: { html: '<section></section>', message: 'Invitation created.' } }; } },
    DOMParser: class { parseFromString() { return { querySelector: () => nextPanel }; } },
    window: { AppConfirm: { ask: async () => true } },
    Image: class {
        set src(value) { this.value = value; setImmediate(() => this.onload()); }
    },
    URL: { createObjectURL: () => 'blob:png', revokeObjectURL() {} },
    setImmediate,
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/group-invite.js'), 'utf8'), context);

(async () => {
    let prevented = false;
    await listeners.submit({ target: { closest: () => form }, preventDefault() { prevented = true; } });
    assert(prevented);
    assert.equal(requestOptions.method, 'post');
    assert.equal(requestOptions.headers.Accept, 'application/json');
    assert.equal(panel.replacement, nextPanel);
    assert.equal(status.textContent, 'Invitation created.');

    const copyButton = { closest: () => panel };
    listeners.click({ target: { closest: selector => selector === '[data-copy-group-invite]' ? copyButton : null } });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(copied, input.value);
    assert.equal(status.textContent, 'Invitation link copied.');

    const downloadButton = { closest: () => panel, dataset: { downloadName: 'study-group-invite.png' } };
    listeners.click({ target: { closest: selector => selector === '[data-download-group-invite-qr]' ? downloadButton : null } });
    await new Promise(resolve => setImmediate(resolve));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(pngType, 'image/png');
    assert.equal(downloaded, 'study-group-invite.png');
    assert.equal(status.textContent, 'QR code downloaded as PNG.');
    console.log('Group invitation create, copy and PNG download checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

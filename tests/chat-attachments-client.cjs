const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../public/js/chat/attachments.js'), 'utf8');
const listeners = {};
const warnings = [];

function element() {
    return {
        children: [],
        dataset: {},
        style: {},
        hidden: false,
        appendChild(child) { this.children.push(child); return child; },
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
        setAttribute(name, value) { this[name] = value; },
    };
}

const bar = element();
const list = element();
const summary = element();
const overlay = element();
const input = { files: [], value: '', click() {} };
const ids = {
    'attachment-input': input,
    'attachment-preview': bar,
    'attachment-preview-list': list,
    'attachment-limit-summary': summary,
};

const document = {
    addEventListener(name, callback) { listeners[name] = callback; },
    getElementById(id) { return ids[id] || null; },
    querySelector(selector) { return selector === '[data-attachment-drop-overlay]' ? overlay : null; },
    createElement() { return element(); },
};

class TestFormData {
    constructor() { this.entries = []; }
    append(name, value) { this.entries.push([name, value]); }
}

const window = {
    chat: { activeRoomId: 9 },
    chatAttachmentConfig: {
        maxFiles: 3,
        maxFileSizeBytes: 5,
        maxTotalSizeBytes: 8,
        allowedExtensions: ['png', 'pdf'],
    },
    AppNotifications: { warning(message) { warnings.push(message); } },
    addEventListener(name, callback) { listeners[`window:${name}`] = callback; },
};

let objectUrl = 0;
vm.runInContext(source, vm.createContext({
    window,
    document,
    FormData: TestFormData,
    URL: {
        createObjectURL() { objectUrl += 1; return `blob:${objectUrl}`; },
        revokeObjectURL() {},
    },
    console,
}));

const first = { name: 'lesson.pdf', size: 4, lastModified: 1, type: 'application/pdf' };
const image = { name: 'diagram.png', size: 3, lastModified: 2, type: 'image/png' };
assert.equal(window.ChatAttachments.addFiles([first, image]), 2);
assert.equal(window.ChatAttachments.count(), 2);
assert.equal(window.ChatAttachments.totalBytes(), 7);
assert.match(summary.textContent, /2\/3 files/);
assert.equal(list.children.length, 2);
assert.equal(bar.style.display, 'flex');

window.ChatAttachments.setSending(true);
assert.equal(bar.style.display, 'none');
assert.equal(window.ChatAttachments.count(), 2);
assert.equal(window.ChatAttachments.addFiles([{ name: 'later.pdf', size: 1, lastModified: 9, type: 'application/pdf' }]), 0);
assert.match(warnings.at(-1), /current upload/);
window.ChatAttachments.setSending(false);
assert.equal(bar.style.display, 'flex');
assert.equal(list.children.length, 2);

assert.equal(window.ChatAttachments.addFiles([{ name: 'large.pdf', size: 6, lastModified: 3, type: 'application/pdf' }]), 0);
assert.match(warnings.at(-1), /file limit/);
assert.equal(window.ChatAttachments.addFiles([{ name: 'notes.txt', size: 1, lastModified: 4, type: 'text/plain' }]), 0);
assert.match(warnings.at(-1), /not a supported file type/);
assert.equal(window.ChatAttachments.addFiles([{ name: 'second.pdf', size: 2, lastModified: 5, type: 'application/pdf' }]), 0);
assert.match(warnings.at(-1), /total up to/);

const formData = window.ChatAttachments.buildFormData('Study notes', 'uuid', 14);
assert.deepEqual(formData.entries.map(([name]) => name), [
    'body', 'client_uuid', 'reply_to_message_id', 'attachments[]', 'attachments[]',
]);

const selectedSignature = window.ChatAttachments.signature();
window.ChatAttachments.clearIfSignature('different-selection');
assert.equal(window.ChatAttachments.count(), 2);
window.ChatAttachments.clearIfSignature(selectedSignature);
assert.equal(window.ChatAttachments.count(), 0);

window.ChatAttachments.addFiles([first, image]);

window.ChatAttachments.remove(0);
assert.equal(window.ChatAttachments.count(), 1);
window.ChatAttachments.clear();
assert.equal(window.ChatAttachments.count(), 0);
assert.equal(bar.style.display, 'none');

const voiceOverlay = { hidden: false, dataset: { mode: 'preview' } };
const voiceAudio = { hidden: false, src: 'blob:old', removeAttribute(name) { if (name === 'src') this.src = ''; } };
const voiceStatus = { hidden: true };
const voiceInput = { disabled: true };
const voiceComposer = { classList: { toggle(_name, locked) { voiceComposer.locked = locked; } } };
const voiceWindow = { chat: { voiceDraftFile: { name: 'recording.webm' } }, addEventListener() {} };
const voiceElements = {
    'voice-overlay': voiceOverlay,
    'voice-preview-audio': voiceAudio,
    'voice-record-status': voiceStatus,
};
const voiceDocument = {
    getElementById: id => voiceElements[id] || null,
    querySelector: selector => selector === '.teams-composer' ? voiceComposer
        : selector === '#message-form [name="body"]' ? voiceInput : null,
    addEventListener() {},
};
vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/voice.js'), 'utf8'), vm.createContext({
    window: voiceWindow,
    document: voiceDocument,
    URL: { createObjectURL: () => 'blob:recording', revokeObjectURL() {} },
}));
voiceWindow.ChatVoice.setSending(true);
assert.equal(voiceOverlay.hidden, true);
assert.equal(voiceInput.disabled, false);
voiceWindow.ChatVoice.setSending(false);
assert.equal(voiceOverlay.hidden, false);
assert.equal(voiceOverlay.dataset.mode, 'preview');
assert.equal(voiceAudio.src, 'blob:recording');
voiceWindow.ChatVoice.reset();
assert.equal(voiceOverlay.hidden, true);
assert.equal(voiceWindow.chat.voiceDraftFile, null);

console.log('Chat attachment checks passed: shared limits, previews, validation, form data, and cleanup.');

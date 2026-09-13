const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const dialogListeners = {};
const documentListeners = {};
const title = {};
const message = {};
const accept = {};
const icon = {};
const dialog = {
    open: false,
    returnValue: '',
    dataset: {},
    querySelector(selector) {
        return {
            '#app-confirm-title': title,
            '#app-confirm-message': message,
            '[data-confirm-accept]': accept,
            '[data-confirm-icon]': icon,
        }[selector];
    },
    addEventListener(name, callback) { dialogListeners[name] = callback; },
    showModal() { this.open = true; },
    close(value) {
        this.open = false;
        this.returnValue = value;
        dialogListeners.close();
    },
};
const document = {
    getElementById: id => id === 'app-confirm-dialog' ? dialog : null,
    addEventListener: (name, callback) => { documentListeners[name] = callback; },
};
const window = {};

vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/js/confirmation-dialog.js'), 'utf8'), {
    window,
    document,
    setTimeout,
});

(async () => {
    const decision = window.AppConfirm.ask({ title: 'Delete message?', message: 'This cannot be undone.', confirmLabel: 'Delete' });
    assert(dialog.open);
    assert.equal(title.textContent, 'Delete message?');
    assert.equal(message.textContent, 'This cannot be undone.');
    assert.equal(accept.textContent, 'Delete');
    dialog.close('confirm');
    assert.equal(await decision, true);

    let submitted = 0;
    const form = {
        dataset: { confirmTitle: 'Leave?', confirmMessage: 'Leave this group?', confirmLabel: 'Leave', confirmTone: 'danger' },
        closest: selector => selector === 'form[data-confirm-message]' ? form : null,
        requestSubmit() {
            submitted++;
            documentListeners.submit({ target: form, preventDefault() {}, submitter: undefined });
        },
    };
    const event = { target: { closest: () => form }, preventDefault() {}, submitter: undefined };
    const submission = documentListeners.submit(event);
    await Promise.resolve();
    dialog.close('confirm');
    await submission;
    assert.equal(submitted, 1);
    console.log('Reusable confirmation dialog and confirmed form submission checks passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../public/js/chat/messages.js'), 'utf8');

const calls = [];
const listeners = {};

const container = {
    insertAdjacentHTML(position, html) {
        calls.push(['insert', position, html]);
    },
};

const replyPreview = {
    style: {
        setProperty(name, value) {
            calls.push(['reply-preview', name, value]);
        },
    },
};

const input = { value: 'Hello there' };

const form = {
    matches(selector) {
        return selector === '#message-form';
    },
    querySelector(selector) {
        if (selector === 'input[name="body"]') {
            return input;
        }

        return null;
    },
};

const document = {
    addEventListener(name, callback) {
        listeners[name] = callback;
    },
    querySelector(selector) {
        if (selector === '.messages-container') {
            return container;
        }

        if (selector === '#message-form input[name="body"]') {
            return input;
        }

        return null;
    },
    getElementById(id) {
        if (id === 'reply-preview') {
            return replyPreview;
        }

        return null;
    },
    createElement() {
        return {
            textContent: '',
            get innerHTML() {
                return this.textContent;
            },
            set innerHTML(value) {
                this.textContent = value;
            },
        };
    },
};

const axios = {
    calls: [],
    post: async (url, payload) => {
        axios.calls.push([url, payload]);
        return { data: { success: true } };
    },
};

const window = {
    chat: {
        activeRoomId: 1,
        currentUserInitial: 'A',
        currentUserName: 'Alice',
        allowedReactions: ['👍'],
        replyingToMessageId: null,
        replyingToMessageText: null,
        voiceDraftFile: null,
        isSendingMessage: false,
    },
    ChatAttachments: null,
    ChatVoice: null,
};

const context = vm.createContext({
    window,
    document,
    axios,
    crypto: { randomUUID: () => 'client-uuid-1' },
    console,
    setTimeout,
    clearTimeout,
});

vm.runInContext(source, context);

async function submitMessage(body) {
    input.value = body;
    const event = {
        target: form,
        preventDefault() {},
    };

    await listeners.submit(event);
}

(async () => {
    await submitMessage('Hello there');
    assert.equal(axios.calls.length, 1);
    assert.equal(window.chat.isSendingMessage, false);

    await submitMessage('Second message');
    assert.equal(axios.calls.length, 2);
    assert.equal(window.chat.isSendingMessage, false);
    assert.equal(input.value, '');

    input.value = '   ';
    await submitMessage('   ');
    assert.equal(axios.calls.length, 2);
    assert.equal(window.chat.isSendingMessage, false);

    console.log('Chat composer send-lock regression test passed.');
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/js/chat/read-receipts.js'), 'utf8');

function client() {
    const listeners = {}, timers = new Map(), calls = [], badges = [];
    let timerId = 0, focused = true, readCount = 0, failPost = false, delayedGet = null;
    class Element {
        constructor() { this.children = []; this.dataset = {}; this.isConnected = true; this.textContent = ''; }
        append(...nodes) { this.children.push(...nodes); }
        replaceChildren(...nodes) { this.children = nodes; this.textContent = ''; }
        setAttribute(key, value) { this[key] = value; }
        focus() { this.focused = true; }
        close() { this.open = false; }
        showModal() { this.open = true; }
        addEventListener() {}
    }
    const button = new Element();
    button.dataset = { messageId: '10', isGroup: '1', read: '0' };
    button.disabled = true;
    const incoming = new Element(); incoming.dataset.messageId = '20';
    incoming.getBoundingClientRect = () => ({ top: 20, bottom: 80, height: 60 });
    const offscreen = new Element(); offscreen.dataset.messageId = '21';
    offscreen.getBoundingClientRect = () => ({ top: 200, bottom: 260, height: 60 });
    const container = new Element();
    container.getBoundingClientRect = () => ({ top: 0, bottom: 100, width: 400, height: 100 });
    container.querySelectorAll = selector => selector.includes('is-other') ? [incoming, offscreen]
        : selector.includes('read-status') ? [button] : [incoming, offscreen];
    container.querySelector = () => button;
    const elements = Object.fromEntries(['chat-read-dialog', 'chat-read-title', 'chat-read-list'].map(id => [id, new Element()]));
    const document = {
        hidden: false, hasFocus: () => focused,
        querySelector: () => container, getElementById: id => elements[id], createElement: () => new Element(),
        addEventListener: (name, callback) => { listeners[name] = callback; },
    };
    const window = { chat: { activeRoomId: 1 }, location: { href: 'https://example.test/chat' },
        addEventListener: (name, callback) => { listeners[name] = callback; } };
    const axios = {
        post: async (url, data) => {
            calls.push({ method: 'POST', url, data });
            if (failPost) throw Error('Offline');
            return { data: { unread_count: 1 } };
        },
        get: async (url) => {
            calls.push({ method: 'GET', url });
            if (delayedGet) return delayedGet;
            if (url.includes('/messages/')) return { data: { read_count: readCount, eligible_reader_count: 2,
                readers: readCount ? [{ id: 2, name: '<Alice>', avatar: '/avatar.png', read_at: '2026-09-07T10:32:00.000000Z' }] : [] } };
            return { data: { statuses: [{ message_id: 10, read_count: readCount }], unread_count: 1 } };
        },
    };
    vm.runInNewContext(source, { window, document, axios, URL, console,
        IntersectionObserver: class { observe() {} disconnect() {} },
        MutationObserver: class { observe() {} disconnect() {} },
        setInterval() {}, setTimeout: callback => { timers.set(++timerId, callback); return timerId; },
        clearTimeout: id => timers.delete(id), handleUnreadCountUpdated: data => badges.push(data),
    });
    async function flush() {
        const queued = [...timers.values()]; timers.clear();
        for (const callback of queued) await callback();
        await new Promise(resolve => setImmediate(resolve));
    }
    return { window, document, listeners, calls, badges, button, incoming, container, elements, flush,
        setReadCount: count => { readCount = count; }, setFocused: value => { focused = value; },
        failPosts: value => { failPost = value; }, delayGets: value => { delayedGet = value; } };
}

(async () => {
    const c = client();
    c.window.ChatReads.attach(); await c.flush();
    assert.equal(c.calls.filter(call => call.method === 'POST').length, 1);
    assert.equal(c.calls.find(call => call.method === 'POST').data.up_to_message_id, 20, 'offscreen newest message is excluded');
    assert.equal(c.button.textContent, '✓'); assert(c.button.disabled);
    c.window.ChatReads.schedule(); c.window.ChatReads.schedule(); await c.flush();
    assert.equal(c.calls.filter(call => call.method === 'POST').length, 1, 'confirmed boundary is not repeatedly submitted');
    c.setReadCount(1); c.window.ChatReads.refresh(); await c.flush();
    assert.equal(c.button.textContent, '✓✓'); assert.equal(c.button.disabled, false);
    const clickTarget = { closest: selector => selector.includes('read-status') ? c.button : null };
    c.listeners.click({ target: clickTarget }); await c.flush();
    assert(c.elements['chat-read-dialog'].open);
    assert.equal(c.elements['chat-read-title'].textContent, 'Read by 1 of 2');
    assert.equal(c.elements['chat-read-list'].children[0].children[1].children[0].textContent, '<Alice>');
    assert.equal(c.elements['chat-read-list'].children[0].children[1].children[1].dateTime, '2026-09-07T10:32:00.000000Z');
    c.setReadCount(2); c.window.ChatReads.refresh(); await c.flush();
    assert.equal(c.elements['chat-read-title'].textContent, 'Read by 2 of 2', 'open panel refreshes with read event');
    c.button.dataset.isGroup = '0'; c.window.ChatReads.refresh(); await c.flush();
    assert.equal(c.button.textContent, '✓✓'); assert(c.button.disabled, 'direct status has no group popup');
    c.window.ChatReads.close(); assert(!c.elements['chat-read-dialog'].open); assert(c.button.focused);

    const hidden = client(); hidden.document.hidden = true;
    hidden.window.ChatReads.attach(); await hidden.flush(); assert.equal(hidden.calls.length, 0);
    hidden.document.hidden = false; hidden.setFocused(false);
    hidden.window.ChatReads.schedule(); await hidden.flush(); assert.equal(hidden.calls.length, 0);
    hidden.setFocused(true); hidden.listeners.focus(); await hidden.flush();
    assert.equal(hidden.calls.filter(call => call.method === 'POST').length, 1);

    const retry = client(); retry.failPosts(true); retry.window.ChatReads.attach(); await retry.flush();
    retry.failPosts(false); retry.listeners.online(); await retry.flush();
    assert.equal(retry.calls.filter(call => call.method === 'POST').length, 2, 'failed receipt retries on reconnect');

    const race = client(); let resolve;
    race.delayGets(new Promise(done => { resolve = done; }));
    race.window.ChatReads.attach(); const loading = race.flush();
    await new Promise(done => setImmediate(done));
    race.window.chat.activeRoomId = 2; race.window.ChatReads.attach();
    resolve({ data: { statuses: [{ message_id: 10, read_count: 2 }], unread_count: 0 } });
    await loading;
    assert.equal(race.button.textContent, '', 'response from previous room is ignored');
    console.log('Read receipt client tests passed: visibility, batching, retry, direct/group status, popup, realtime refresh, room races.');
})().catch(error => { console.error(error); process.exitCode = 1; });

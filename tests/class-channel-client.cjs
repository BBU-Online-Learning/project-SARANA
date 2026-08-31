const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const { randomUUID } = require('node:crypto');

class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.dataset = {}; this.style = {}; this.handlers = {}; this.textContent = ''; this.scrollHeight = 50; this.scrollTop = 0; this.clientHeight = 50; }
    append(...nodes) { nodes.forEach(node => this.insertBefore(node, null)); }
    insertBefore(node, next) { node.remove(); node.parent = this; const index = this.children.indexOf(next); this.children.splice(index < 0 ? this.children.length : index, 0, node); }
    replaceChildren(...nodes) { this.children.forEach(node => { node.parent = null; }); this.children = []; this.append(...nodes); }
    replaceWith(node) { const parent = this.parent; parent.insertBefore(node, this); this.remove(); }
    remove() { if (this.parent) { this.parent.children = this.parent.children.filter(node => node !== this); this.parent = null; } }
    setAttribute(name, value) { this[name] = value; }
    addEventListener(name, fn) { this.handlers[name] = fn; }
    focus() {}
    requestSubmit() { this.handlers.submit({ preventDefault() {} }); }
    matches(selector) {
        if (selector.includes(',')) return selector.split(',').some(part => this.matches(part.trim()));
        if (selector === '.alert') return (this.className || '').split(' ').includes('alert');
        if (selector === '[data-message-editor]') return !!this.dataset.messageEditor;
        if (selector.startsWith('[data-class-message-id=')) return String(this.dataset.classMessageId) === selector.match(/"(\d+)"/)[1];
        if (selector === 'button[type=submit]') return this.tag === 'button' && this.type === 'submit';
        return this.tag === selector;
    }
    querySelectorAll(selector) { return this.children.flatMap(node => [...(node.matches(selector) ? [node] : []), ...node.querySelectorAll(selector)]); }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

const source = fs.readFileSync(process.argv[2], 'utf8');
const server = { records: new Map(), next: 0, uuids: new Map(), clients: [] };
const flush = async () => { for (let i = 0; i < 15; i++) await new Promise(resolve => setTimeout(resolve, 1)); };
const serialized = (record, client) => ({ ...record, can_modify: record.sender_id === client.id && client.allowed && !record.deleted });
const signal = () => server.clients.forEach(client => client.listener?.({ body: 'Never trust socket content' }));

function client(id, initial = []) {
    const state = { id, allowed: true, calls: 0, revoked: false, offline: false, loseResponse: false };
    const page = new Element();
    page.dataset = { membershipId: String(id), messagesUrl: 'http://localhost/classes/1/channels/1/messages', pageUrl: 'http://localhost/classes/1/channels/1', historyPage: '0' };
    const list = new Element();
    const form = new Element('form');
    form.action = page.dataset.messagesUrl;
    const input = new Element('textarea'); input.value = '';
    const send = new Element('button'); send.type = 'submit';
    form.elements = { body: input, client_uuid: { value: randomUUID() } }; form.append(input, send);
    const nodes = {
        'school-class-channel-page': page, 'class-channel-message-list': list,
        'class-channel-message-form': form, 'class-channel-read-only': new Element(),
        'class-channel-sync-status': new Element(), 'class-channel-older': new Element(),
        'class-channel-send-error': new Element(), 'class-channel-initial': { textContent: JSON.stringify(initial) },
    };
    state.nodes = nodes; state.form = form; state.list = list; state.page = page;
    const events = {};
    const echoChannel = { listen(_name, fn) { state.listener = fn; return this; }, subscribed(fn) { state.resubscribe = fn; return this; }, error() { return this; } };
    vm.runInNewContext(source, {
        URL, AbortController, crypto: { randomUUID }, location: { origin: 'http://localhost' },
        setTimeout, clearTimeout,
        setInterval(fn) { state.poll = fn; return 42; }, clearInterval(id) { assert.equal(id, 42); state.cleared = true; },
        document: { addEventListener(_name, fn) { state.start = fn; }, getElementById(id) { return nodes[id] || null; }, createElement: tag => new Element(tag), querySelector: () => ({ content: 'test-csrf' }) },
        window: { confirm: () => true, location: { origin: 'http://localhost', reload() {} }, addEventListener(name, fn) { events[name] = fn; },
            Echo: { private(topic) { state.topic = topic; return echoChannel; }, leave(topic) { state.left = topic; }, connector: { pusher: { connection: { bind(_name, fn) { state.reconnect = fn; }, unbind() {} } } } } },
        fetch: async (address, options) => {
            state.calls++;
            assert.equal(options.cache, 'no-store'); assert.equal(options.credentials, 'same-origin');
            if (state.offline) throw Error('Network unavailable');
            if (state.revoked) return { ok: false, status: 403, json: async () => ({ message: 'Forbidden' }) };
            const url = new URL(address);
            let result;
            if (!options.method) {
                const ids = url.searchParams.getAll('visible_ids[]').map(Number);
                assert(ids.length <= 200);
                const after = Number(url.searchParams.get('after_id'));
                const records = [...server.records.values()].filter(record => record.message_id > after && !record.deleted).slice(0, 50);
                result = { messages: records.map(record => serialized(record, state)), updates: ids.map(id => server.records.get(id)).filter(Boolean).map(record => serialized(record, state)), next_id: records.at(-1)?.message_id || after, has_more: false, can_send: state.allowed };
            } else {
                const body = JSON.parse(options.body || '{}');
                if (options.method === 'POST') {
                    let record = server.records.get(server.uuids.get(body.client_uuid));
                    if (!record) {
                        record = { message_id: ++server.next, sender_id: id, sender_name: 'Member '+id, body: body.body, created_at: new Date().toISOString(), is_edited: false, deleted: false };
                        server.records.set(record.message_id, record); server.uuids.set(body.client_uuid, record.message_id);
                    }
                    result = { message: serialized(record, state) };
                    if (state.loseResponse) { state.loseResponse = false; throw Error('Response lost after commit'); }
                } else {
                    const record = server.records.get(Number(url.pathname.split('/').at(-1)));
                    assert.equal(record.sender_id, id);
                    if (options.method === 'PATCH') { record.body = body.body; record.is_edited = true; }
                    else { record.deleted = true; record.body = null; }
                    result = { message: serialized(record, state) };
                }
                signal();
            }
            return { ok: true, status: 200, json: async () => result };
        },
    });
    server.clients.push(state); state.start(); return state;
}

(async () => {
    const mode = process.argv[3];
    const a = client(7); await flush();
    if (mode === 'revoked') {
        a.revoked = true; a.listener({body: 'Untrusted socket data'}); await flush();
        assert(a.page.children[0].textContent.includes('no longer available'));
        assert.equal(a.left, a.topic); assert(a.cleared);
        const calls = a.calls; a.poll(); await flush(); assert.equal(a.calls, calls);
    } else if (mode === 'archive') {
        a.allowed = false; a.poll(); await flush();
        assert(a.form.querySelectorAll('textarea, button').every(node => node.disabled));
        assert.equal(a.nodes['class-channel-read-only'].hidden, false); assert(!a.cleared);
        a.allowed = true; a.poll(); await flush();
        assert(a.form.querySelectorAll('textarea, button').every(node => !node.disabled));
        assert.equal(a.nodes['class-channel-read-only'].hidden, true);
    } else {
        const b = client(8); await flush();
        a.form.elements.body.value = '<script>text only</script>';
        a.form.requestSubmit(); a.form.requestSubmit(); await flush();
        assert.equal(server.records.size, 1); assert.equal(a.list.children.length, 1); assert.equal(b.list.children.length, 1);
        signal(); signal(); await flush(); assert.equal(b.list.children.length, 1);
        assert.equal(b.list.querySelector('script'), null);
        const edit = a.list.querySelectorAll('button').find(node => node.textContent === 'Edit'); edit.handlers.click();
        let editor = a.list.querySelector('form'); editor.querySelector('textarea').value = 'Edited in first browser'; editor.requestSubmit(); await flush();
        assert(b.list.children[0].children.some(node => node.textContent === 'Edited in first browser'));
        assert(b.list.querySelectorAll('span').some(node => node.textContent.includes('(edited)')));
        a.list.querySelectorAll('button').find(node => node.textContent === 'Delete').handlers.click(); await flush();
        assert.equal(b.list.children.filter(node => node.dataset.classMessageId).length, 0);
        a.form.elements.body.value = 'Retry safely'; const uuid = a.form.elements.client_uuid.value;
        a.loseResponse = true; a.form.requestSubmit(); await flush();
        assert.equal(a.form.elements.body.value, 'Retry safely'); assert.equal(a.form.elements.client_uuid.value, uuid);
        a.form.requestSubmit(); await flush(); assert.equal(server.records.size, 2);
        assert.equal(a.form.elements.body.value, ''); assert.equal(b.list.children.length, 1);
        b.offline = true;
        a.form.elements.body.value = 'During disconnect'; a.form.requestSubmit(); await flush();
        assert.equal(b.list.children.length, 1);
        b.offline = false; b.reconnect(); await flush(); assert.equal(b.list.children.length, 2);
        server.records.get(2).body = 'Missed edit without any broadcast'; b.poll(); await flush();
        assert(b.list.children[0].children.some(node => node.textContent === 'Missed edit without any broadcast'));
        server.records.get(2).deleted = true; server.records.get(2).body = null; b.poll(); await flush();
        assert.equal(b.list.children.length, 1);
    }
    console.log('Class client '+mode+' passed');
})().catch(error => { console.error(error); process.exitCode = 1; });

const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const source = fs.readFileSync("public/js/chat/message-sound.js", "utf8");
const stored = new Map();

function load(permission = "granted", preferences = {}, visibleRoom = null) {
    let incoming;
    const played = [];
    const desktop = [];
    const toasts = [];
    const notes = [];
    const gains = [];
    function element(tagName) {
        return {
            tagName, children: [], listeners: {}, dataset: {},
            append(...children) { this.children.push(...children); },
            prepend(child) { this.children.unshift(child); },
            setAttribute() {},
            addEventListener(name, callback) { this.listeners[name] = callback; },
            remove() { this.removed = true; },
        };
    }
    const region = element("div");
    region.prepend = toast => { toasts.push(toast); };
    const document = {
        readyState: "complete",
        currentScript: { dataset: { userId: "2", soundUrl: "/sound/notification.wav", chatUrl: "/chat" } },
        createElement: element,
        getElementById(id) { return id === "app-notifications" ? region : null; },
    };
    class Audio {
        constructor(url) { assert.equal(url, "/sound/notification.wav"); }
        pause() {}
        play() { played.push(this.volume); return Promise.resolve(); }
    }
    class Notification {
        static permission = permission;
        static requestPermission() { this.permission = "granted"; return Promise.resolve("granted"); }
        constructor(title, options) { this.title = title; this.options = options; desktop.push(this); }
        close() { this.closed = true; }
    }
    const audioContext = {
        state: "running", currentTime: 0, destination: {},
        resume() { return Promise.resolve(); },
        createOscillator() { return {
            frequency: {}, connect() {}, disconnect() {},
            start() { notes.push(this.frequency.value); }, stop() {},
        }; },
        createGain() { return { gain: { setValueAtTime() {}, linearRampToValueAtTime(value) { if (value > 0) gains.push(value); } }, connect() {}, disconnect() {} }; },
    };
    const window = { Notification, AudioContext: class { constructor() { return audioContext; } }, appPreferences: preferences,
        ChatRealtime: { isRoomVisible: roomId => roomId === visibleRoom }, location: { href: "https://school.test/dashboard" }, focus() {}, Echo: { private(topic) {
        assert.equal(topic, "user.2");
        return { listen(name, callback) { assert.equal(name, ".sidebar.updated"); incoming = callback; } };
    } } };
    const localStorage = {
        getItem(key) { return stored.get(key) ?? null; },
        setItem(key, value) { stored.set(key, value); },
    };
    vm.runInNewContext(source, { document, window, Audio, Notification, localStorage, URL, setTimeout() {} });
    return { incoming, played, desktop, toasts, notes, gains, window, Notification };
}

const first = load("granted", { message_tone: "classic" });
const message = { message_id: 10, room_id: 7, sender_id: 1, sender: "Alice", body: "New lesson" };
first.incoming(message);
assert.equal(first.played.length, 1);
assert.equal(first.played[0], 1);
assert.equal(first.toasts.length, 1);
assert.equal(first.desktop.length, 1);
assert.equal(first.desktop[0].title, "Alice");
assert.equal(first.desktop[0].options.body, "New lesson");
assert.equal(first.toasts[0].children[1].children[0].href, "https://school.test/chat?room=7");
first.desktop[0].onclick();
assert.equal(first.window.location.href, "https://school.test/chat?room=7");
first.incoming(message);
first.incoming({ message_id: 11, room_id: 7, sender_id: 2 });
first.incoming({ room_id: 1, sender_id: 1 });
assert.equal(first.played.length, 1, "duplicate, own, and non-message updates stay silent");
assert.equal(first.toasts.length, 1);
assert.equal(first.desktop.length, 1);
first.incoming({ ...message, message_id: 12 });
assert.equal(first.played.length, 2, "another incoming message plays once");

const openRoom = load("granted", { message_tone: "classic" }, 7);
openRoom.incoming({ ...message, message_id: 150 });
assert.equal(openRoom.played.length, 0, "the visible conversation stays silent");
assert.equal(openRoom.toasts.length, 0);
assert.equal(openRoom.desktop.length, 0);
openRoom.incoming({ ...message, message_id: 151, room_id: 8 });
assert.equal(openRoom.played.length, 1, "another conversation still notifies");
assert.equal(openRoom.toasts.length, 1);

const second = load("granted", { message_tone: "classic" });
second.incoming(message);
assert.equal(second.played.length, 0, "another tab does not replay a recent message");
second.incoming({ ...message, message_id: 13 });
assert.equal(second.played.length, 1);

const muted = load("granted", { message_sound: false, message_popups: false, desktop_messages: false });
muted.incoming({ ...message, message_id: 100 });
assert.equal(muted.played.length, 0);
assert.equal(muted.toasts.length, 0);
assert.equal(muted.desktop.length, 0);
muted.window.MessageSounds.preview("chime");
assert.deepEqual(muted.notes, [740, 988], "preview works while message sound is off");

const chime = load("granted", { message_tone: "chime" });
chime.incoming({ ...message, message_id: 101 });
assert.deepEqual(chime.notes, [740, 988]);
assert.deepEqual(chime.gains, [0.35, 0.35]);
assert.equal(chime.played.length, 0);
chime.window.MessageSounds.preview("pulse");
assert.deepEqual(chime.notes.slice(-3), [523, 523, 659]);

const defaultTone = load();
defaultTone.incoming({ ...message, message_id: 102 });
assert.deepEqual(defaultTone.notes, [740, 988], "Chime plays by default when no tone is saved");
assert.equal(defaultTone.played.length, 0);

const pending = load("default");
pending.incoming({ ...message, message_id: 14 });
assert.equal(pending.desktop.length, 0);
const enable = pending.toasts[0].children[1].children[1];
assert.equal(enable.textContent, "Enable desktop notifications");
enable.listeners.click().then(() => {
    assert.equal(pending.Notification.permission, "granted");
    pending.incoming({ ...message, message_id: 15 });
    assert.equal(pending.desktop.length, 1);
    console.log("Message notifications: in-app, desktop, permission, own message, and duplicate checks passed.");
}).catch(error => { console.error(error); process.exitCode = 1; });

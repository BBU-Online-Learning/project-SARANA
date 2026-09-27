const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const source = fs.readFileSync("public/js/notification-center.js", "utf8");
const requests = [];
const controls = new Map();
const listeners = {};
const echoListeners = {};
let visibleRoom = null;
let unreadCount = 20;
let holdNextRoomRead = false;
let releaseRoomRead;

function element() {
    const classes = new Set();
    return {
        children: [], dataset: {}, hidden: false, listeners: {},
        classList: {
            add(name) { classes.add(name); },
            remove(name) { classes.delete(name); },
            contains(name) { return classes.has(name); },
        },
        set className(value) { classes.clear(); value.split(" ").forEach(name => classes.add(name)); },
        get className() { return [...classes].join(" "); },
        append(...items) { this.children.push(...items); },
        replaceChildren(...items) { this.children = items; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        setAttribute(name, value) { this[name] = value; },
        querySelectorAll(selector) { return selector === ".is-unread" ? this.children.filter(item => item.classList.contains("is-unread")) : []; },
        focus() {},
    };
}

for (const id of ["count", "toggle", "panel", "list", "more", "read-all", "clear-all"]) controls.set(id, element());
controls.get("panel").hidden = true;
const document = {
    readyState: "complete",
    currentScript: { dataset: { feedUrl: "/notifications", readUrl: "/notifications/__ID__/read", readAllUrl: "/notifications/read-all", clearAllUrl: "/notifications", readRoomUrl: "/notifications/read-room", userId: "7" } },
    getElementById(id) { return controls.get(id.replace("notification-center-", "")); },
    createElement: element,
    querySelector() { return { content: "csrf" }; },
    addEventListener(name, callback) { listeners[name] = callback; },
};
const window = {
    AppConfirm: { ask: async () => clearApproved },
    Echo: { private(name) { assert.equal(name, "user.7"); return { listen(event, callback) { echoListeners[event] = callback; } }; } },
    ChatRealtime: { isRoomVisible: roomId => roomId === visibleRoom },
    location: { href: "https://school.test/home", origin: "https://school.test", assign(url) { this.href = url; } },
    addEventListener(name, callback) { listeners[name] = callback; },
};
let clearApproved = false;
const first = Array.from({ length: 20 }, (_, index) => ({
    id: String(index + 1), title: `Update ${index + 1}`, body: "New activity", url: "/chat?room=1", room_id: 1, read_at: null, created_at: "now",
}));
async function fetch(url, options = {}) {
    requests.push([String(url), options.method || "GET"]);
    if (options.method === "DELETE") {
        first.splice(0);
        unreadCount = 0;
        return { ok: true, json: async () => ({ unread_count: 0 }) };
    }
    if (options.method === "PATCH") return { ok: true, json: async () => ({ unread_count: unreadCount = 18 }) };
    if (options.method === "POST") {
        if (String(url).endsWith("read-room") && holdNextRoomRead) {
            holdNextRoomRead = false;
            await new Promise(resolve => { releaseRoomRead = resolve; });
        }
        return { ok: true, json: async () => ({ unread_count: unreadCount = String(url).endsWith("read-room") ? 18 : 0 }) };
    }
    const page = new URL(url).searchParams.get("page");
    return { ok: true, json: async () => ({ notifications: page === "2" ? [{ id: "21", title: "Older", body: "Earlier", url: "/classes", read_at: "today", created_at: "yesterday" }] : first, unread_count: unreadCount, next_page: page === "2" ? null : 2 }) };
}
vm.runInNewContext(source, { document, window, URL, fetch, setInterval() {} });
const flush = () => new Promise(resolve => setImmediate(resolve));

(async () => {
    await flush();
    assert.equal(controls.get("count").textContent, "20");
    controls.get("toggle").listeners.click();
    await flush();
    assert.equal(controls.get("panel").hidden, false);
    assert.equal(controls.get("list").children.length, 20);
    assert.equal(controls.get("list").children[0].classList.contains("is-unread"), true);
    assert.equal(controls.get("list").children[0].children[0].className, "notification-center-item-icon");
    assert.equal(controls.get("more").hidden, false);

    controls.get("more").listeners.click();
    await flush();
    assert.equal(controls.get("list").children.length, 21);
    assert.equal(controls.get("more").hidden, true);

    const beforeLive = requests.length;
    echoListeners[".activity.notification.changed"]();
    await flush();
    assert.equal(requests.length, beforeLive + 1);

    visibleRoom = 1;
    const beforeOpenRoom = requests.length;
    echoListeners[".activity.notification.changed"]({ room_id: 1 });
    await flush();
    await flush();
    assert.equal(requests[beforeOpenRoom][1], "POST", "the visible room is read before refreshing its badge");
    assert.equal(controls.get("count").textContent, "18");
    const beforeOtherRoom = requests.length;
    echoListeners[".activity.notification.changed"]({ room_id: 2 });
    await flush();
    assert.equal(requests[beforeOtherRoom][1], "GET", "another room remains unread");

    await window.NotificationCenter.readRoom(1);
    assert.equal(controls.get("count").textContent, "18");
    assert.equal(controls.get("list").children[0].classList.contains("is-unread"), false);
    assert.equal(requests.some(([url, method]) => url.endsWith("/notifications/read-room") && method === "POST"), true);

    holdNextRoomRead = true;
    const previousRoomReads = requests.filter(([url, method]) => url.endsWith("/notifications/read-room") && method === "POST").length;
    const pendingRead = window.NotificationCenter.readRoom(1);
    window.NotificationCenter.readRoom(1);
    releaseRoomRead();
    await pendingRead;
    assert.equal(requests.filter(([url, method]) => url.endsWith("/notifications/read-room") && method === "POST").length,
        previousRoomReads + 2, "new arrivals during a read trigger another read");

    await controls.get("read-all").listeners.click();
    assert.equal(controls.get("count").hidden, true);
    assert.equal(controls.get("list").children[0].classList.contains("is-unread"), false);

    const link = controls.get("list").children[0];
    await controls.get("list").listeners.click({ target: { closest: () => link }, preventDefault() {} });
    assert.equal(window.location.href, "https://school.test/chat?room=1");
    assert.equal(requests.some(([url, method]) => url.endsWith("/notifications/1/read") && method === "PATCH"), true);
    await controls.get("clear-all").listeners.click();
    assert.equal(requests.some(([, method]) => method === "DELETE"), false, "cancel keeps notification history");
    clearApproved = true;
    await controls.get("clear-all").listeners.click();
    await flush();
    assert.equal(requests.some(([url, method]) => url.endsWith("/notifications") && method === "DELETE"), true);
    assert.equal(controls.get("list").children[0].textContent, "No notifications yet.");
    assert.equal(controls.get("count").hidden, true);
    assert.equal(controls.get("clear-all").disabled, true);
    console.log("Notification center: listing, pagination, read controls, and navigation passed.");
})().catch(error => { console.error(error); process.exitCode = 1; });

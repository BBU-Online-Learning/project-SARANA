// Run with PLAYWRIGHT_MODULE pointing to an existing Playwright installation.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const fs = require("node:fs");
const assert = require("node:assert/strict");
const path = require("node:path");
const overlay = fs.readFileSync("resources/views/chat/partials/voice-call-overlay.blade.php", "utf8").replace(/\{\{.*?\}\}/g, "#");
const css = fs.readFileSync("public/css/voice-call.css", "utf8");
const scripts = ["call-media", "call-ui", "voice-call"].map(name =>
    '<script>' + fs.readFileSync("public/js/chat/" + name + ".js", "utf8") + '</script>').join("");
const html = '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="test"><link rel="stylesheet" href="/backend/assets/css/icons.min.css"><style>body{font-family:Arial;margin:0}button{cursor:pointer} [hidden]{display:none!important}' + css +
    '</style></head><body><button data-start-voice-call data-start-url="/start" data-call-type="video" data-peer-name="Test participant">Video call</button>' +
    '<button data-start-voice-call data-start-url="/start" data-call-type="audio" data-peer-name="Test participant">Audio call</button>' + overlay + scripts + '</body></html>';
const clone = value => JSON.parse(JSON.stringify(value));
let active = null, nextId = 0, failEnd = false;
const clients = [];
async function deliver(name, payload, target = null) {
    await Promise.all(clients.filter(client => target === null || client.userId === target).map(async client => {
        if (!client.page.isClosed()) await client.page.evaluate(({ name, payload }) => window.__listeners[name]?.(payload), { name, payload: clone(payload) });
    }));
}
async function api(userId, method, url, body = {}) {
    if (url === "/ice") return { ice_servers: [] };
    if (url === "/current") return { call: active && ["ringing", "active"].includes(active.status) ? clone(active) : null };
    if (url === "/start") {
        if (active && ["ringing", "active"].includes(active.status)) throw new Error("Busy");
        active = { id: ++nextId, room_id: 1, call_type: body.call_type, status: "ringing", initiated_by: userId,
            expires_at: new Date(Date.now() + 45000).toISOString(),
            participants: [{ id: userId, name: "Caller", client_id: body.client_id }, { id: userId === 1 ? 2 : 1, name: "Receiver", client_id: null }] };
        await deliver(".voice-call.state", { call: active }); return { call: clone(active) };
    }
    const action = url.split("/").at(-1);
    if (action === "signal") {
        const target = active.participants.find(person => person.id !== userId).id;
        await deliver(".voice-call.signal", { call_id: active.id, from_user_id: userId, type: body.type, data: body.data }, target);
        return { success: true };
    }
    if (action === "heartbeat") return { call: clone(active) };
    if (action === "accept") {
        active.status = "active"; active.answered_at = new Date().toISOString();
        active.participants.find(person => person.id === userId).client_id = body.client_id;
    } else {
        if (failEnd) throw new Error("Network unavailable");
        active.status = { end: "ended", cancel: "cancelled", fail: "failed", decline: "declined", timeout: "missed" }[action];
    }
    await deliver(".voice-call.state", { call: active });
    return { call: clone(active) };
}
async function createClient(browser, userId, mobile = false) {
    const context = await browser.newContext({ permissions: ["camera", "microphone"], viewport: mobile ? { width: 390, height: 844 } : { width: 1280, height: 900 } });
    const page = await context.newPage();
    await page.exposeFunction("testApi", (method, url, body) => api(userId, method, url, body));
    await page.addInitScript(({ userId }) => {
        window.__listeners = {}; window.__errors = []; window.__tracks = []; window.__pcs = [];
        window.voiceCallConfig = { userId, userName: "Test " + userId, currentUrl: "/current", iceUrl: "/ice", callBaseUrl: "/calls", debug: true };
        const nativeGet = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
        navigator.mediaDevices.getUserMedia = async constraints => {
            if (constraints.video && window.__denyCamera) throw new DOMException("Camera denied", "NotAllowedError");
            if (constraints.audio && window.__denyMic) throw new DOMException("Microphone denied", "NotAllowedError");
            const stream = await nativeGet(constraints); window.__tracks.push(...stream.getTracks()); return stream;
        };
        const NativePC = window.RTCPeerConnection;
        window.RTCPeerConnection = class extends NativePC { constructor(options) { super(options); window.__pcs.push(this); } };
        window.AppNotifications = Object.fromEntries(["error", "warning", "info"].map(type => [type, text => window.__errors.push(type + ": " + text)]));
        window.axios = {
            get: async url => ({ data: await window.testApi("get", url) }),
            post: async (url, body) => ({ data: await window.testApi("post", url, body) }),
        };
        const channel = {
            subscribed(fn) { setTimeout(fn, 0); return channel; },
            error() { return channel; },
            listen(name, fn) { window.__listeners[name] = fn; return channel; },
        };
        window.Echo = { private: () => channel, connector: { pusher: { connection: { bind() {} } } } };
    }, { userId });
    await page.route("https://calls.test/**", route => {
        const asset = new URL(route.request().url()).pathname;
        const publicRoot = path.resolve("public") + path.sep;
        const file = path.resolve("public", "." + asset);
        if (asset.startsWith("/backend/assets/") && file.startsWith(publicRoot) && fs.existsSync(file) && fs.statSync(file).isFile()) {
            return route.fulfill({ path: file });
        }
        return route.fulfill({ contentType: "text/html", body: html });
    });
    page.on("pageerror", error => console.error("Browser error:", error.message));
    clients.push({ page, userId });
    await page.goto("https://calls.test/");
    return page;
}
async function connected(page) {
    await page.waitForFunction(() => window.__pcs.at(-1)?.connectionState === "connected", { timeout: 30000 });
}
async function stopped(page) {
    await page.waitForFunction(() => window.__tracks.every(track => track.readyState === "ended"));
}
async function begin(from, to, type = "video", audioOnly = false) {
    await from.locator('[data-start-voice-call][data-call-type="' + type + '"]').click();
    await to.locator("#voice-call-incoming-actions").waitFor({ state: "visible" });
    await to.locator('[data-voice-call-action="' + (audioOnly ? "accept-audio" : "accept") + '"]').click();
    await Promise.all([connected(from), connected(to)]);
}
(async () => {
    const browser = await chromium.launch({ headless: true, channel: process.env.CALL_TEST_BROWSER || "chrome",
        args: ["--use-fake-device-for-media-stream", "--use-fake-ui-for-media-stream", "--autoplay-policy=no-user-gesture-required"] });
    try {
        const pc = await createClient(browser, 1);
        const phone = await createClient(browser, 2, true);
        await begin(pc, phone);
        await Promise.all([pc, phone].map(page => page.waitForFunction(() => {
            const video = document.getElementById("voice-call-remote-video");
            return video.videoWidth > 0 && video.currentTime > 0;
        }, { timeout: 20000 })));
        console.log("Real WebRTC: bidirectional video and audio connected.");
        await Promise.all([pc, phone].map(page => page.waitForFunction(async () =>
            [...(await window.__pcs.at(-1).getStats()).values()].some(report =>
                report.type === "inbound-rtp" && report.kind === "audio" && report.bytesReceived > 0))));
        const beforeRestart = await pc.evaluate(() => window.__pcs.at(-1).localDescription.sdp);
        await pc.evaluate(({ callId }) => window.__listeners[".voice-call.signal"]({
            call_id: callId, from_user_id: 2, type: "restart", data: {},
        }), { callId: active.id });
        await pc.waitForFunction(previous => window.__pcs.at(-1).localDescription.sdp !== previous && window.__pcs.at(-1).signalingState === "stable", beforeRestart);
        await Promise.all([connected(pc), connected(phone)]);
        console.log("Audio packets received by both peers; ICE restart renegotiated successfully.");
        assert.equal(await phone.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        await phone.screenshot({ path: path.join("storage", "logs", "video-call-mobile.png") });
        await pc.screenshot({ path: path.join("storage", "logs", "video-call-desktop.png") });
        await pc.locator('[data-voice-call-action="mute"]').click();
        assert.equal(await pc.evaluate(() => window.__tracks.filter(t => t.kind === "audio" && t.readyState === "live").every(t => !t.enabled)), true);
        await pc.locator('[data-voice-call-action="camera"]').click();
        await pc.waitForFunction(() => window.__tracks.filter(t => t.kind === "video").every(t => t.readyState === "ended"));
        await pc.locator('[data-voice-call-action="camera"]').click();
        await pc.waitForFunction(() => window.__tracks.some(t => t.kind === "video" && t.readyState === "live"));
        const otherTab = await createClient(browser, 1);
        assert.equal(active.status, "active", "opening another tab must not end the call");
        assert.equal(await otherTab.locator("#voice-call-layer").isVisible(), false);
        await otherTab.close();
        await pc.locator('[data-voice-call-action="end"]').click();
        await Promise.all([stopped(pc), stopped(phone)]);
        console.log("Controls: mute, camera release/reacquire, second-tab isolation and hangup passed.");

        await phone.evaluate(() => { window.__denyCamera = true; });
        await begin(phone, pc, "video", true);
        assert.equal(await phone.evaluate(() => window.__tracks.some(t => t.kind === "video" && t.readyState === "live")), false);
        await phone.evaluate(() => { window.__denyCamera = false; });
        await phone.locator('[data-voice-call-action="camera"]').click();
        await pc.waitForFunction(() => document.getElementById("voice-call-remote-video").videoWidth > 0);
        await phone.locator('[data-voice-call-action="end"]').click();
        await Promise.all([stopped(pc), stopped(phone)]);
        console.log("Phone to PC: camera denial, audio-only join, and enabling video later passed.");

        await begin(pc, phone, "audio");
        assert.equal(await pc.locator("#voice-call-video-stage").isVisible(), false);
        failEnd = true;
        await pc.locator('[data-voice-call-action="end"]').click();
        await stopped(pc);
        failEnd = false;
        await phone.locator('[data-voice-call-action="end"]').click();
        await stopped(phone);
        console.log("Audio regression and track cleanup after failed end request passed.");
        await pc.evaluate(() => { window.__denyMic = true; });
        await pc.locator('[data-start-voice-call][data-call-type="video"]').click();
        await pc.waitForFunction(() => document.getElementById("voice-call-status").textContent.includes("Microphone access is required"));
        await stopped(pc);
        console.log("Microphone denial shows a friendly error and releases all tracks.");
    } catch (error) {
        for (const client of clients) {
            if (!client.page.isClosed()) console.error("Peer", client.userId, JSON.stringify(await client.page.evaluate(() => ({
                errors: window.__errors, status: document.getElementById("voice-call-status").textContent,
                peers: window.__pcs.map(pc => ({ state: pc.connectionState, signaling: pc.signalingState,
                    transceivers: pc.getTransceivers().map(t => ({ mid: t.mid, direction: t.currentDirection, sender: t.sender.track?.kind, receiver: t.receiver.track.kind })) })),
                videos: [...document.querySelectorAll("video")].map(v => ({ id: v.id, width: v.videoWidth, time: v.currentTime, paused: v.paused, tracks: v.srcObject?.getTracks().map(t => ({ kind: t.kind, muted: t.muted, enabled: t.enabled })) })),
            })), null, 2));
        }
        throw error;
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });

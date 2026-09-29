const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");

const source = fs.readFileSync("resources/js/meeting-room.js", "utf8")
    .replace("import { Room, RoomEvent, Track } from 'livekit-client';", "const { Room, RoomEvent, Track } = window.__liveKitMock;");
const css = fs.readFileSync("public/css/learning-workspace.css", "utf8");

const fixture = `<!doctype html><html><head><meta name="csrf-token" content="test"><style>${css}</style></head>
<body class="learning-workspace meeting-room-page"><div id="class-meeting-room" class="meeting-room-shell" data-credentials-url="/credentials" data-end-at="${new Date(Date.now() + 60 * 60 * 1000).toISOString()}">
<div class="meeting-room-topbar"><div class="meeting-room-heading"><div><h2>Live classroom</h2><p id="meeting-room-status"></p></div></div>
<div class="meeting-room-top-actions"><span id="meeting-participant-count">0 participants</span>
<button id="meeting-chat-toggle" class="meeting-top-button" aria-expanded="false">Chat</button><button id="meeting-details-toggle" class="meeting-top-button" aria-expanded="false">Details</button><button id="meeting-fullscreen" class="meeting-top-button" aria-pressed="false"><span>Full screen</span></button></div>
</div><div class="meeting-room-content">
<div class="meeting-stage"><div id="meeting-prejoin" class="meeting-prejoin">Ready for class?<button id="meeting-connect">Connect</button></div>
<div id="meeting-share-stage" class="meeting-share-stage" hidden><strong id="meeting-share-name"></strong><div id="meeting-share-media" class="meeting-share-media"></div></div>
<div id="meeting-participants" class="meeting-participants"></div></div>
<aside id="meeting-room-side" class="meeting-room-side">Room details</aside>
<aside id="meeting-chat-side" class="meeting-chat-side"><div id="meeting-chat-messages"></div>
<form id="meeting-chat-form"><input id="meeting-chat-input" disabled><button id="meeting-chat-send" disabled>Send</button></form></aside></div>
<div class="meeting-controls">
<button id="meeting-microphone" class="meeting-control" disabled>Microphone</button>
<button id="meeting-camera" class="meeting-control" disabled>Camera</button><button id="meeting-screen" class="meeting-control" disabled>Share</button>
<button id="meeting-hand" class="meeting-control" aria-pressed="false" disabled><span>Raise hand</span></button>
<label class="meeting-share-audio-option"><input id="meeting-share-audio" type="checkbox"> Include tab audio</label>
<button id="meeting-sound" class="meeting-control" disabled>Sound</button><button id="meeting-leave" class="meeting-control meeting-control-leave" disabled>Leave</button></div>
<select id="meeting-microphone-device" disabled></select><select id="meeting-camera-device" disabled></select>
</div></body></html>`;

(async () => {
    const browser = await chromium.launch({ headless: true, channel: process.env.CALL_TEST_BROWSER || "chrome" });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.setContent(fixture);
        await page.evaluate(() => {
            const devices = {
                audioinput: [{ deviceId: "mic-a", label: "Microphone A" }, { deviceId: "mic-b", label: "Microphone B" }],
                videoinput: [{ deviceId: "cam-a", label: "Camera A" }, { deviceId: "cam-b", label: "Camera B" }],
            };
            class Room {
                constructor() {
                    window.__room = this;
                    this.handlers = {};
                    this.activeDevices = { audioinput: "mic-a", videoinput: "cam-a" };
                    this.remoteParticipants = new Map();
                    this.localParticipant = {
                        identity: "teacher",
                        isMicrophoneEnabled: false,
                        isCameraEnabled: false,
                        isScreenShareEnabled: false,
                        isLocal: true,
                        attributes: {},
                        setMicrophoneEnabled: async (enabled) => { this.localParticipant.isMicrophoneEnabled = enabled; },
                        setCameraEnabled: async (enabled) => { this.localParticipant.isCameraEnabled = enabled; },
                        setScreenShareEnabled: async (enabled, options) => {
                            this.localParticipant.isScreenShareEnabled = enabled;
                            window.__screenShareOptions = options;
                        },
                        sendChatMessage: async (message) => ({ id: "own-1", message, timestamp: Date.now() }),
                        setAttributes: async (attributes) => {
                            Object.assign(this.localParticipant.attributes, attributes);
                            this.handlers.participantAttributesChanged?.(attributes, this.localParticipant);
                        },
                    };
                }
                static async getLocalDevices(kind) { return devices[kind]; }
                on(event, callback) { this.handlers[event] = callback; }
                async connect() {
                    window.__connectCount = (window.__connectCount || 0) + 1;
                    if (window.__connectCount > 1) {
                        this.localParticipant.isMicrophoneEnabled = false;
                        this.localParticipant.isCameraEnabled = false;
                        this.localParticipant.isScreenShareEnabled = false;
                    }
                }
                disconnect() { this.handlers.disconnected?.(); }
                getActiveDevice(kind) { return this.activeDevices[kind]; }
                async switchActiveDevice(kind, id) { this.activeDevices[kind] = id; return true; }
                async startAudio() {}
            }
            window.__liveKitMock = {
                Room,
                RoomEvent: { ParticipantConnected: "participantConnected", ParticipantDisconnected: "participantDisconnected",
                    TrackSubscribed: "trackSubscribed", TrackUnsubscribed: "trackUnsubscribed",
                    LocalTrackPublished: "localTrackPublished", LocalTrackUnpublished: "localTrackUnpublished",
                    Disconnected: "disconnected", AudioPlaybackStatusChanged: "audioPlaybackStatusChanged",
                    ChatMessage: "chatMessage", ParticipantAttributesChanged: "participantAttributesChanged" },
                Track: { Kind: { Video: "video" }, Source: { ScreenShare: "screen_share" } },
            };
            Object.defineProperty(document, "fullscreenElement", { configurable: true, get: () => window.__fullScreenRoom || null });
            document.getElementById("class-meeting-room").requestFullscreen = async () => {
                window.__fullScreenRoom = document.getElementById("class-meeting-room");
                document.dispatchEvent(new Event("fullscreenchange"));
            };
            document.exitFullscreen = async () => {
                window.__fullScreenRoom = null;
                document.dispatchEvent(new Event("fullscreenchange"));
            };
            window.fetch = async () => {
                window.__credentialsRequests = (window.__credentialsRequests || 0) + 1;
                if (window.__denyCredentials) return { ok: false, status: 403 };
                return { ok: true, json: async () => ({ url: "wss://example.livekit.cloud", token: "test" }) };
            };
        });
        await page.addScriptTag({ content: source });
        assert.equal(await page.locator("#meeting-prejoin").isVisible(), true);
        await page.locator("#meeting-connect").click();
        assert.equal(await page.locator("#class-meeting-room").evaluate((element) => element.classList.contains("is-connected")), true);
        assert.equal(await page.locator("#meeting-prejoin").isVisible(), false);
        assert.equal(await page.locator("#meeting-participant-count").textContent(), "1 participant");
        await page.evaluate(() => {
            for (let number = 1; number <= 5; number += 1) {
                const participant = { identity: `student-${number}`, name: `Student ${number}` };
                window.__room.remoteParticipants.set(participant.identity, participant);
                window.__room.handlers.participantConnected(participant);
            }
        });
        assert.equal(await page.locator("#meeting-participant-count").textContent(), "6 participants");
        assert.deepEqual(await page.locator("#meeting-participants h2").allTextContents(),
            ["You", "Student 1", "Student 2", "Student 3", "Student 4", "Student 5"]);
        assert.deepEqual(await page.locator(".meeting-participant-placeholder").allTextContents(),
            ["Y", "S1", "S2", "S3", "S4", "S5"]);
        await page.locator("#meeting-hand").click();
        assert.equal(await page.locator("#meeting-hand").getAttribute("aria-pressed"), "true");
        assert.equal(await page.locator(".meeting-participant[data-identity='teacher'] .meeting-participant-hand").isVisible(), true);
        await page.evaluate(() => {
            const participant = window.__room.remoteParticipants.get("student-1");
            participant.attributes = { "class.handRaised": "true" };
            window.__room.handlers.participantAttributesChanged({ "class.handRaised": "true" }, participant);
        });
        assert.equal(await page.locator(".meeting-participant[data-identity='student-1'] .meeting-participant-hand").isVisible(), true);
        await page.locator("#meeting-hand").click();
        assert.equal(await page.locator("#meeting-hand").getAttribute("aria-pressed"), "false");
        await page.evaluate(() => {
            function screenTrack() {
                const elements = [];
                return {
                    kind: "video",
                    attach: () => { const element = document.createElement("video"); elements.push(element); return element; },
                    detach: () => elements,
                };
            }
            window.__remoteScreenTrack = screenTrack();
            window.__room.handlers.trackSubscribed(window.__remoteScreenTrack, { source: "screen_share" },
                window.__room.remoteParticipants.get("student-1"));
            window.__localScreenTrack = screenTrack();
            window.__room.handlers.localTrackPublished({ track: window.__localScreenTrack, source: "screen_share" });
        });
        assert.equal(await page.locator("#class-meeting-room").evaluate((element) => element.classList.contains("has-share")), true);
        assert.equal(await page.locator("#meeting-share-stage").isVisible(), true);
        assert.equal(await page.locator("#meeting-share-name").textContent(), "You are sharing");
        assert.equal(await page.locator("#meeting-share-media video").count(), 1);
        assert.equal(await page.locator("#meeting-participant-count").textContent(), "6 participants");
        await page.setViewportSize({ width: 1440, height: 900 });
        assert.equal(await page.locator("#meeting-participants").evaluate((element) => getComputedStyle(element).display), "flex");
        assert.ok((await page.locator("#meeting-share-stage").boundingBox()).width > 900);
        await page.setViewportSize({ width: 390, height: 844 });
        await page.evaluate(() => window.__room.handlers.localTrackUnpublished({ track: window.__localScreenTrack, source: "screen_share" }));
        assert.equal(await page.locator("#meeting-share-name").textContent(), "Student 1 is sharing");
        await page.evaluate(() => window.__room.handlers.trackUnsubscribed(window.__remoteScreenTrack));
        assert.equal(await page.locator("#meeting-share-stage").isVisible(), false);
        assert.equal(await page.locator("#class-meeting-room").evaluate((element) => element.classList.contains("has-share")), false);
        await page.locator("#meeting-details-toggle").click();
        assert.equal(await page.locator("#meeting-details-toggle").getAttribute("aria-expanded"), "true");
        await page.locator("#meeting-chat-toggle").click();
        assert.equal(await page.locator("#meeting-details-toggle").getAttribute("aria-expanded"), "false");
        assert.equal(await page.locator("#meeting-chat-toggle").getAttribute("aria-expanded"), "true");
        await page.locator("#meeting-chat-input").fill("Hello class");
        await page.locator("#meeting-chat-send").click();
        assert.match(await page.locator("#meeting-chat-messages").textContent(), /You\s+Hello class/);
        await page.evaluate(() => window.__room.handlers.chatMessage({ id: "remote-1", message: "<img src=x>", timestamp: Date.now() },
            window.__room.remoteParticipants.get("student-1")));
        assert.equal(await page.locator("#meeting-chat-messages img").count(), 0);
        assert.match(await page.locator("#meeting-chat-messages").textContent(), /Student 1\s+<img src=x>/);
        await page.locator("#meeting-fullscreen").click();
        assert.equal(await page.locator("#meeting-fullscreen").getAttribute("aria-pressed"), "true");
        assert.equal(await page.locator("#meeting-fullscreen span").textContent(), "Exit full screen");
        await page.locator("#meeting-fullscreen").click();
        assert.equal(await page.locator("#meeting-fullscreen").getAttribute("aria-pressed"), "false");
        await page.evaluate(() => {
            const participant = window.__room.remoteParticipants.get("student-5");
            window.__room.remoteParticipants.delete(participant.identity);
            window.__room.handlers.participantDisconnected(participant);
        });
        assert.equal(await page.locator("#meeting-participant-count").textContent(), "5 participants");
        assert.deepEqual(await page.locator("#meeting-microphone-device option").allTextContents(), ["Microphone A", "Microphone B"]);
        assert.deepEqual(await page.locator("#meeting-camera-device option").allTextContents(), ["Camera A", "Camera B"]);
        await page.locator("#meeting-microphone-device").selectOption("mic-b");
        await page.locator("#meeting-camera-device").selectOption("cam-b");
        assert.deepEqual(await page.evaluate(() => window.__room.activeDevices), { audioinput: "mic-b", videoinput: "cam-b" });
        await page.locator("#meeting-microphone").click();
        assert.equal(await page.locator("#meeting-microphone").getAttribute("aria-pressed"), "true");
        await page.locator("#meeting-camera").click();
        assert.equal(await page.locator("#meeting-camera").getAttribute("aria-pressed"), "true");
        await page.locator("#meeting-share-audio").check();
        await page.locator("#meeting-screen").click();
        assert.equal(await page.locator("#meeting-screen").getAttribute("aria-pressed"), "true");
        assert.deepEqual(await page.evaluate(() => window.__screenShareOptions), { audio: true });
        await page.evaluate(() => window.__room.handlers.localTrackUnpublished({ source: "screen_share" }));
        assert.equal(await page.locator("#meeting-screen").getAttribute("aria-pressed"), "false");
        assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector(".meeting-controls")).display), "grid");
        await page.evaluate(() => window.__room.handlers.trackSubscribed(window.__remoteScreenTrack,
            { source: "screen_share" }, window.__room.remoteParticipants.get("student-1")));
        await page.evaluate(() => window.__room.handlers.disconnected());
        assert.equal(await page.locator("#meeting-share-stage").isVisible(), false);
        await page.waitForFunction(() => window.__connectCount === 2, { timeout: 5000 });
        await page.waitForFunction(() => document.getElementById('meeting-microphone').getAttribute('aria-pressed') === 'true'
            && document.getElementById('meeting-camera').getAttribute('aria-pressed') === 'true');
        assert.equal(await page.evaluate(() => window.__credentialsRequests), 2, "reconnection must request a fresh authorized token");
        assert.equal(await page.locator("#meeting-microphone").getAttribute("aria-pressed"), "true");
        assert.equal(await page.locator("#meeting-camera").getAttribute("aria-pressed"), "true");
        assert.equal(await page.locator("#meeting-screen").getAttribute("aria-pressed"), "false");
        await page.evaluate(() => { window.__denyCredentials = true; window.__room.handlers.disconnected(); });
        await page.waitForFunction(() => window.__credentialsRequests === 3, { timeout: 5000 });
        assert.equal(await page.locator("#meeting-connect").isEnabled(), true, "rejected access must stop automatic reconnects");
        assert.match(await page.locator("#meeting-room-status").textContent(), /access is unavailable/i);
        await page.evaluate(() => { window.__denyCredentials = false; });
        await page.locator("#meeting-connect").click();
        assert.equal(await page.evaluate(() => window.__connectCount), 3);
        await page.locator("#meeting-leave").click();
        assert.equal(await page.locator("#class-meeting-room").evaluate((element) => element.classList.contains("is-connected")), false);
        assert.equal(await page.locator("#meeting-prejoin").isVisible(), true);
        assert.equal(await page.locator("#meeting-participant-count").textContent(), "0 participants");
        await page.waitForTimeout(1200);
        assert.equal(await page.evaluate(() => window.__credentialsRequests), 4, "leaving must not start another connection");
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });

const { chromium } = require(process.env.PLAYWRIGHT_MODULE || "playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");

const source = fs.readFileSync("resources/js/meeting-room.js", "utf8")
    .replace("import { Room, RoomEvent, Track } from 'livekit-client';", "const { Room, RoomEvent, Track } = window.__liveKitMock;");
const css = fs.readFileSync("public/css/learning-workspace.css", "utf8");

const fixture = `<!doctype html><html><head><meta name="csrf-token" content="test"><style>${css}</style></head>
<body class="learning-workspace"><div id="class-meeting-room" data-credentials-url="/credentials" data-end-at="${new Date(Date.now() + 60 * 60 * 1000).toISOString()}">
<p id="meeting-room-status"></p><span id="meeting-participant-count">0 participants</span>
<div class="meeting-controls">
<button id="meeting-connect">Connect</button><button id="meeting-microphone" disabled>Microphone</button>
<button id="meeting-camera" disabled>Camera</button><button id="meeting-screen" disabled>Share</button>
<button id="meeting-sound" disabled>Sound</button><button id="meeting-leave" disabled>Leave</button></div>
<select id="meeting-microphone-device" disabled></select><select id="meeting-camera-device" disabled></select>
<div id="meeting-participants"></div></div></body></html>`;

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
                        setMicrophoneEnabled: async (enabled) => { this.localParticipant.isMicrophoneEnabled = enabled; },
                        setCameraEnabled: async (enabled) => { this.localParticipant.isCameraEnabled = enabled; },
                        setScreenShareEnabled: async (enabled) => { this.localParticipant.isScreenShareEnabled = enabled; },
                    };
                }
                static async getLocalDevices(kind) { return devices[kind]; }
                on(event, callback) { this.handlers[event] = callback; }
                async connect() {}
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
                    Disconnected: "disconnected", AudioPlaybackStatusChanged: "audioPlaybackStatusChanged" },
                Track: { Kind: { Video: "video" }, Source: { ScreenShare: "screen_share" } },
            };
            window.fetch = async () => ({ ok: true, json: async () => ({ url: "wss://example.livekit.cloud", token: "test" }) });
        });
        await page.addScriptTag({ content: source });
        await page.locator("#meeting-connect").click();
        assert.equal(await page.locator("#meeting-participant-count").textContent(), "1 participant");
        assert.deepEqual(await page.locator("#meeting-microphone-device option").allTextContents(), ["Microphone A", "Microphone B"]);
        assert.deepEqual(await page.locator("#meeting-camera-device option").allTextContents(), ["Camera A", "Camera B"]);
        await page.locator("#meeting-microphone-device").selectOption("mic-b");
        await page.locator("#meeting-camera-device").selectOption("cam-b");
        assert.deepEqual(await page.evaluate(() => window.__room.activeDevices), { audioinput: "mic-b", videoinput: "cam-b" });
        await page.locator("#meeting-microphone").click();
        assert.equal(await page.locator("#meeting-microphone").getAttribute("aria-pressed"), "true");
        await page.locator("#meeting-screen").click();
        assert.equal(await page.locator("#meeting-screen").getAttribute("aria-pressed"), "true");
        await page.evaluate(() => window.__room.handlers.localTrackUnpublished({ source: "screen_share" }));
        assert.equal(await page.locator("#meeting-screen").getAttribute("aria-pressed"), "false");
        assert.equal(await page.evaluate(() => getComputedStyle(document.querySelector(".meeting-controls")).display), "grid");
        await page.locator("#meeting-leave").click();
        assert.equal(await page.locator("#meeting-participant-count").textContent(), "0 participants");
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });

const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
class Track {
    constructor(kind) { this.kind = kind; this.readyState = "live"; this.enabled = true; }
    stop() { this.readyState = "ended"; }
    getSettings() { return { deviceId: "mic-1" }; }
}
class Stream {
    constructor(tracks = []) { this.tracks = tracks; }
    getTracks() { return [...this.tracks]; }
    getAudioTracks() { return this.tracks.filter(t => t.kind === "audio"); }
    getVideoTracks() { return this.tracks.filter(t => t.kind === "video"); }
    addTrack(track) { this.tracks.push(track); }
    removeTrack(track) { this.tracks = this.tracks.filter(t => t !== track); }
}
let denyCamera = false, resolveMedia = null, deferred = false;
const acquired = [];
const context = {
    window: { isSecureContext: true, RTCPeerConnection: function () {} },
    MediaStream: Stream,
    navigator: { mediaDevices: {
        async getUserMedia(constraints) {
            if (constraints.video && denyCamera) throw Object.assign(new Error("denied"), { name: "NotAllowedError" });
            const stream = new Stream([new Track(constraints.audio ? "audio" : "video")]);
            acquired.push(stream);
            if (deferred) return new Promise(resolve => { resolveMedia = () => resolve(stream); });
            return stream;
        },
        async enumerateDevices() { return [{ kind: "audioinput", deviceId: "mic-1" }, { kind: "videoinput" }]; },
    } },
};
vm.createContext(context);
vm.runInContext(fs.readFileSync("public/js/chat/call-media.js", "utf8"), context);
const Media = context.window.CallMedia;
function connection() {
    const transceivers = [];
    return {
        getTransceivers: () => transceivers,
        addTransceiver(track, options) {
            transceivers.push({ receiver: { track: { kind: typeof track === "string" ? track : track.kind } },
                sender: { track: typeof track === "string" ? null : track, async replaceTrack(next) { this.track = next; } }, options });
        },
    };
}
(async () => {
    const media = new Media();
    await media.prepare(true);
    const pc = connection(); media.attach(pc, true);
    assert.equal(pc.getTransceivers().length, 2);
    const oldCamera = media.stream.getVideoTracks()[0], oldMic = media.stream.getAudioTracks()[0];
    media.toggleMute();
    await media.selectMicrophone("mic-2", pc);
    assert.equal(oldMic.readyState, "ended");
    assert.equal(media.stream.getAudioTracks()[0].enabled, false, "device switching preserves mute");
    await media.toggleCamera(pc);
    assert.equal(oldCamera.readyState, "ended", "camera off releases hardware");
    assert.equal(media.sender(pc, "video").track, null);
    await media.toggleCamera(pc);
    assert.equal(media.state().camera, true);
    const camera = media.stream.getVideoTracks()[0];
    await media.switchCamera(pc);
    assert.equal(camera.readyState, "ended");
    assert.equal(media.facing, "environment");
    assert.equal((await media.microphones()).length, 1);
    media.stop();
    assert.equal(media.stream.getTracks().length, 0);
    assert.ok(acquired.every(stream => stream.getTracks().every(track => track.readyState === "ended")));

    denyCamera = true;
    const fallback = new Media();
    assert.equal((await fallback.prepare(true)).name, "NotAllowedError");
    assert.equal(fallback.stream.getAudioTracks().length, 1);
    const fallbackPc = connection(); fallback.attach(fallbackPc, true);
    assert.equal(fallbackPc.getTransceivers().length, 2, "audio-only join still reserves video for later");
    fallback.stop(); denyCamera = false;

    const pending = new Media(); deferred = true;
    const preparing = pending.prepare(false);
    pending.stop(); resolveMedia();
    await assert.rejects(preparing, /cancelled/);
    assert.equal(acquired.at(-1).getTracks()[0].readyState, "ended", "late permissions must not leak tracks");
    deferred = false;
    context.window.isSecureContext = false;
    await assert.rejects(new Media().prepare(false), /HTTPS/);
    context.window.isSecureContext = true;
    const switching = new Media();
    await switching.prepare(true);
    const switchingPc = connection(); switching.attach(switchingPc, true);
    let releaseSender;
    switching.sender(switchingPc, "video").replaceTrack = () => new Promise(resolve => { releaseSender = resolve; });
    const before = acquired.length;
    const switchPending = switching.switchCamera(switchingPc);
    switching.stop(); releaseSender(); await switchPending;
    assert.equal(acquired.length, before, "ending during camera switch must not acquire another camera");
    console.log("Call media checks passed: fallback, camera, switching, mute, cleanup, cancellation, HTTPS.");
})().catch(error => { console.error(error); process.exitCode = 1; });

const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");

const tones = [];
const gains = [];
const intervals = new Map();
const listeners = {};
let nextInterval = 0;
let allowResume = true;
const audioContext = {
    state: "running",
    currentTime: 0,
    destination: {},
    resume() { if (allowResume) this.state = "running"; return Promise.resolve(); },
    createOscillator() {
        const oscillator = {
            frequency: {},
            connect() {},
            disconnect() {},
            start() { tones.push({ frequency: this.frequency.value, oscillator }); },
            stop() { this.stopped = true; },
        };
        return oscillator;
    },
    createGain() {
        return { gain: { setValueAtTime() {}, linearRampToValueAtTime(value) { if (value > 0) gains.push(value); } }, connect() {}, disconnect() {} };
    },
};
const window = { AudioContext: class { constructor() { return audioContext; } } };
const document = { addEventListener(name, listener) { listeners[name] = listener; } };
vm.runInNewContext(fs.readFileSync("public/js/chat/call-sounds.js", "utf8"), {
    window, document,
    setInterval(callback) { const id = ++nextInterval; intervals.set(id, callback); return id; },
    clearInterval(id) { intervals.delete(id); },
});

window.CallSounds.play("incoming");
assert.deepEqual(tones.map(tone => tone.frequency), [660, 880, 660, 880]);
assert.deepEqual(gains, [0.35, 0.35, 0.35, 0.35]);
assert.equal(intervals.size, 1);
window.CallSounds.play("incoming");
assert.equal(tones.length, 4, "repeated state updates must not restart the ringtone");

window.CallSounds.play("outgoing");
assert.equal(tones.slice(0, 4).every(tone => tone.oscillator.stopped), true);
assert.deepEqual(tones.slice(-2).map(tone => tone.frequency), [440, 480]);
assert.deepEqual(gains.slice(-2), [0.18, 0.18]);
assert.equal(intervals.size, 1);

for (const [stage, frequencies] of Object.entries({
    connecting: [520, 620], connected: [620, 830], reconnecting: [440, 350],
    ended: [520, 390], cancelled: [520, 390], declined: [360, 360],
    busy: [360, 360], missed: [440, 330, 260], failed: [440, 330, 260],
})) {
    const previous = tones.length;
    window.CallSounds.play(stage);
    assert.deepEqual(tones.slice(previous).map(tone => tone.frequency), frequencies, stage);
    assert.deepEqual(gains.slice(previous).slice(0, frequencies.length), frequencies.map(() => 0.22), `${stage} volume`);
    assert.equal(intervals.size, 0, `${stage} must stop ringing`);
}

window.CallSounds.stop();
assert.equal(tones.every(tone => tone.oscillator.stopped), true);

window.appPreferences = { call_tone: "gentle" };
const gentleStart = tones.length;
window.CallSounds.play("incoming");
assert.deepEqual(tones.slice(gentleStart).map(tone => tone.frequency), [523, 659, 523, 659]);
window.CallSounds.stop();
const brightStart = tones.length;
window.CallSounds.preview("bright");
assert.deepEqual(tones.slice(brightStart).map(tone => tone.frequency), [784, 1047, 784, 1047]);
window.CallSounds.stop();

for (const [choice, frequencies] of Object.entries({
    double: [880, 880, 660, 660],
    warm: [392, 523, 659],
    ascending: [523, 659, 784, 1047],
})) {
    const previewStart = tones.length;
    window.CallSounds.preview(choice);
    assert.deepEqual(tones.slice(previewStart).map(tone => tone.frequency), frequencies, `${choice} preview`);
    window.CallSounds.stop();
    window.appPreferences = { call_tone: choice };
    const incomingStart = tones.length;
    window.CallSounds.play("incoming");
    assert.deepEqual(tones.slice(incomingStart).map(tone => tone.frequency), frequencies, `${choice} incoming ring`);
    window.CallSounds.stop();
}

window.appPreferences = { call_sound: false };
const mutedToneCount = tones.length;
window.CallSounds.play("incoming");
assert.equal(tones.length, mutedToneCount);
assert.equal(intervals.size, 0);
window.CallSounds.preview("gentle");
assert.deepEqual(tones.slice(mutedToneCount).map(tone => tone.frequency), [523, 659, 523, 659]);
window.appPreferences.call_sound = true;

audioContext.state = "suspended";
allowResume = false;
window.CallSounds.play("incoming");
const beforeUnlock = tones.length;
allowResume = true;
listeners.pointerdown();
Promise.resolve().then(() => {
    assert.equal(audioContext.state, "running");
    assert.equal(tones.length > beforeUnlock, true, "a user gesture resumes the incoming ringtone");
    window.CallSounds.stop();
    console.log("Call sounds: stage tones, transitions, cleanup, and audio unlock passed.");
}).catch(error => { console.error(error); process.exitCode = 1; });

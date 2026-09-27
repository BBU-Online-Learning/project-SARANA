(() => {
    "use strict";

    let context = null;
    let stage = null;
    let ringTimer = null;
    const playing = new Set();

    function audio() {
        if (!context) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return null;
            context = new AudioContext();
        }
        if (context.state === "suspended") context.resume().catch(() => {});
        return context;
    }

    function tone(frequency, delay, duration, volume = 0.22) {
        const output = audio();
        if (!output || output.state !== "running") return;
        const oscillator = output.createOscillator();
        const gain = output.createGain();
        const start = output.currentTime + delay;
        oscillator.type = "sine";
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(volume, start + 0.025);
        gain.gain.setValueAtTime(volume, start + Math.max(0.025, duration - 0.04));
        gain.gain.linearRampToValueAtTime(0, start + duration);
        oscillator.connect(gain);
        gain.connect(output.destination);
        oscillator.onended = () => { playing.delete(oscillator); oscillator.disconnect(); gain.disconnect(); };
        playing.add(oscillator);
        oscillator.start(start);
        oscillator.stop(start + duration + 0.01);
    }

    function stop() {
        clearInterval(ringTimer);
        ringTimer = null;
        playing.forEach((oscillator) => { try { oscillator.stop(); } catch (_) {} });
        playing.clear();
        stage = null;
    }

    function incomingRing(selected = window.appPreferences?.call_tone) {
        const patterns = {
            classic: [[660, 0, 0.24], [880, 0.3, 0.24], [660, 0.7, 0.24], [880, 1, 0.24]],
            gentle: [[523, 0, 0.24], [659, 0.3, 0.24], [523, 0.7, 0.24], [659, 1, 0.24]],
            bright: [[784, 0, 0.24], [1047, 0.3, 0.24], [784, 0.7, 0.24], [1047, 1, 0.24]],
            double: [[880, 0, 0.15], [880, 0.2, 0.15], [660, 0.62, 0.15], [660, 0.82, 0.15]],
            warm: [[392, 0, 0.28], [523, 0.33, 0.28], [659, 0.74, 0.35]],
            ascending: [[523, 0, 0.18], [659, 0.2, 0.18], [784, 0.4, 0.18], [1047, 0.62, 0.3]],
        };
        (patterns[selected] || patterns.classic).forEach(([frequency, delay, duration]) => {
            tone(frequency, delay, duration, 0.35);
        });
    }

    function ring() {
        if (stage === "incoming") {
            incomingRing();
        } else if (stage === "outgoing") {
            tone(440, 0, 0.75, 0.18); tone(480, 0, 0.75, 0.18);
        }
    }

    function play(nextStage) {
        if (window.appPreferences?.call_sound === false) {
            stop();
            return;
        }
        if (stage === nextStage) return;
        stop();
        stage = nextStage;
        if (nextStage === "incoming" || nextStage === "outgoing") {
            ring();
            ringTimer = setInterval(ring, nextStage === "incoming" ? 3000 : 2800);
        } else if (nextStage === "connecting") {
            tone(520, 0, 0.12); tone(620, 0.16, 0.12);
        } else if (nextStage === "connected") {
            tone(620, 0, 0.12); tone(830, 0.15, 0.2);
        } else if (nextStage === "reconnecting") {
            tone(440, 0, 0.16); tone(350, 0.2, 0.2);
        } else if (nextStage === "ended" || nextStage === "cancelled") {
            tone(520, 0, 0.12); tone(390, 0.16, 0.23);
        } else if (nextStage === "declined" || nextStage === "busy") {
            tone(360, 0, 0.2); tone(360, 0.28, 0.2);
        } else if (nextStage === "missed" || nextStage === "failed") {
            tone(440, 0, 0.14); tone(330, 0.2, 0.14); tone(260, 0.4, 0.22);
        }
    }

    function unlock() {
        const wasSuspended = context?.state === "suspended";
        const output = audio();
        if (wasSuspended) {
            output.resume().then(() => {
                if (stage === "incoming" || stage === "outgoing") ring();
            }).catch(() => {});
        }
    }

    async function preview(selected) {
        const output = audio();
        if (!output) return;
        if (output.state === "suspended") await output.resume();
        incomingRing(selected);
    }

    document.addEventListener("pointerdown", unlock, { passive: true });
    document.addEventListener("keydown", unlock);
    window.CallSounds = { play, stop, preview };
})();

(() => {
    "use strict";

    class CallMedia {
        constructor(onChange = () => {}) {
            this.stream = new MediaStream();
            this.onChange = onChange;
            this.generation = 0;
            this.muted = false;
            this.facing = "user";
            this.busy = false;
        }

        async acquire(constraints) {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) {
                throw new Error("Calls require a supported browser and HTTPS. Open the secure application address.");
            }
            const generation = this.generation;
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            if (generation !== this.generation) {
                stream.getTracks().forEach((track) => track.stop());
                throw new Error("Call cancelled.");
            }
            return stream;
        }

        async prepare(video, audioOnly = false) {
            if (!this.stream.getAudioTracks().length) {
                const audio = await this.acquire({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false });
                audio.getTracks().forEach((track) => this.stream.addTrack(track));
            }
            let cameraError = null;
            if (video && !audioOnly && !this.stream.getVideoTracks().length) {
                try {
                    const camera = await this.acquire({ audio: false, video: this.videoConstraints() });
                    camera.getTracks().forEach((track) => this.stream.addTrack(track));
                } catch (error) {
                    if (!this.stream.getAudioTracks().length) throw error;
                    cameraError = error;
                }
            }
            this.watchTracks();
            this.onChange();
            return cameraError;
        }

        videoConstraints() {
            return { facingMode: { ideal: this.facing }, width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 24, max: 30 } };
        }

        attach(connection, video) {
            for (const kind of video ? ["audio", "video"] : ["audio"]) {
                const track = this.stream.getTracks().find((item) => item.kind === kind);
                connection.addTransceiver(track || kind, { direction: "sendrecv", streams: [this.stream] });
            }
        }

        sender(connection, kind) {
            return connection?.getTransceivers().find((item) => item.receiver.track.kind === kind)?.sender;
        }

        async answer(connection) {
            for (const transceiver of connection.getTransceivers()) {
                const track = this.stream.getTracks().find((item) => item.kind === transceiver.receiver.track.kind);
                transceiver.direction = "sendrecv";
                transceiver.sender.setStreams(this.stream);
                await transceiver.sender.replaceTrack(track || null);
            }
        }

        state() {
            return { camera: this.stream.getVideoTracks().some((track) => track.readyState === "live" && track.enabled),
                microphone: this.stream.getAudioTracks().some((track) => track.readyState === "live" && track.enabled) };
        }

        watchTracks() {
            this.stream.getTracks().forEach((track) => {
                track.onended = () => { this.stream.removeTrack(track); this.onChange(); };
            });
        }

        toggleMute() {
            this.muted = !this.muted;
            this.stream.getAudioTracks().forEach((track) => { track.enabled = !this.muted; });
            this.onChange();
        }

        async replace(kind, constraints, connection) {
            if (this.busy) return;
            this.busy = true;
            const generation = this.generation;
            let incoming = null;
            try {
                incoming = await this.acquire(constraints);
                const track = incoming.getTracks().find((item) => item.kind === kind);
                if (kind === "audio") track.enabled = !this.muted;
                const sender = this.sender(connection, kind);
                if (connection && !sender) throw new Error("This call does not support that media type.");
                if (sender) await sender.replaceTrack(track);
                if (generation !== this.generation) throw new Error("Call ended.");
                this.stream.getTracks().filter((item) => item.kind === kind).forEach((old) => {
                    old.onended = null;
                    old.stop();
                    this.stream.removeTrack(old);
                });
                this.stream.addTrack(track);
                incoming = null;
                this.watchTracks();
                this.onChange();
            } finally {
                incoming?.getTracks().forEach((track) => track.stop());
                this.busy = false;
            }
        }

        async toggleCamera(connection) {
            if (this.busy) return;
            if (!this.state().camera) return this.replace("video", { audio: false, video: this.videoConstraints() }, connection);
            this.busy = true;
            const generation = this.generation;
            try {
                const sender = this.sender(connection, "video");
                if (sender) await sender.replaceTrack(null);
                if (generation !== this.generation) return;
                this.stream.getVideoTracks().forEach((track) => { track.onended = null; track.stop(); this.stream.removeTrack(track); });
                this.onChange();
            } finally { this.busy = false; }
        }

        async switchCamera(connection) {
            if (this.busy || !this.state().camera) return;
            const generation = this.generation;
            const previous = this.facing;
            this.facing = previous === "user" ? "environment" : "user";
            // Mobile devices may require releasing the current camera before acquiring the other one.
            await this.toggleCamera(connection);
            if (generation !== this.generation) return;
            try { await this.replace("video", { audio: false, video: this.videoConstraints() }, connection); }
            catch (error) { this.facing = previous; throw error; }
        }

        selectMicrophone(deviceId, connection) {
            return this.replace("audio", { video: false, audio: { deviceId: { exact: deviceId }, echoCancellation: true, noiseSuppression: true } }, connection);
        }

        async microphones() {
            if (!navigator.mediaDevices?.enumerateDevices) return [];
            return (await navigator.mediaDevices.enumerateDevices()).filter((device) => device.kind === "audioinput");
        }

        stop() {
            this.generation++;
            this.stream.getTracks().forEach((track) => { track.onended = null; track.stop(); this.stream.removeTrack(track); });
            this.muted = false;
            this.facing = "user";
        }
    }

    window.CallMedia = CallMedia;
})();

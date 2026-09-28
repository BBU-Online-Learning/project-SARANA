import { Room, RoomEvent, Track } from 'livekit-client';

const root = document.getElementById('class-meeting-room');

if (root) {
    const status = document.getElementById('meeting-room-status');
    const connectButton = document.getElementById('meeting-connect');
    const microphoneButton = document.getElementById('meeting-microphone');
    const soundButton = document.getElementById('meeting-sound');
    const cameraButton = document.getElementById('meeting-camera');
    const screenButton = document.getElementById('meeting-screen');
    const leaveButton = document.getElementById('meeting-leave');
    const microphoneDevice = document.getElementById('meeting-microphone-device');
    const cameraDevice = document.getElementById('meeting-camera-device');
    const participantCount = document.getElementById('meeting-participant-count');
    const participants = document.getElementById('meeting-participants');
    const room = new Room({ adaptiveStream: true, dynacast: true });
    const closesAt = Date.parse(root.dataset.endAt);
    let connected = false;
    let connecting = false;
    let reconnectWanted = false;
    let reconnectTimer = null;
    let reconnectAttempts = 0;
    const mediaIntent = { microphone: false, camera: false };

    function joinWindowClosed() {
        return Number.isFinite(closesAt) && Date.now() >= closesAt;
    }

    function stopReconnect() {
        reconnectWanted = false;
        if (reconnectTimer !== null) window.clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }

    function queueReconnect() {
        if (!reconnectWanted || connected || connecting || reconnectTimer !== null) return;
        if (joinWindowClosed() || reconnectAttempts >= 5) {
            stopReconnect();
            setStatus(joinWindowClosed() ? 'The meeting join window has closed.' : 'Could not reconnect. Select Connect to meeting to try again.');
            connectButton.disabled = joinWindowClosed();
            return;
        }
        const delay = Math.min(16000, 1000 * 2 ** reconnectAttempts);
        reconnectAttempts += 1;
        connectButton.disabled = true;
        setStatus('Connection lost. Reconnecting to the meeting…');
        reconnectTimer = window.setTimeout(() => {
            reconnectTimer = null;
            connect(true);
        }, delay);
    }

    function setStatus(message) {
        status.textContent = message;
    }

    function setControls(active) {
        connected = active;
        connectButton.disabled = active;
        for (const button of [microphoneButton, soundButton, cameraButton, screenButton, leaveButton]) {
            button.disabled = !active;
        }
        if (!active) {
            participants.replaceChildren();
            updateParticipantCount();
            for (const button of [microphoneButton, cameraButton, screenButton]) {
                button.setAttribute('aria-pressed', 'false');
            }
            microphoneButton.textContent = 'Turn on microphone';
            cameraButton.textContent = 'Turn on camera';
            screenButton.textContent = 'Share screen';
            soundButton.textContent = 'Enable sound';
            for (const select of [microphoneDevice, cameraDevice]) {
                select.disabled = true;
                select.replaceChildren(new Option('Connect to choose a device', ''));
            }
        }
    }

    function syncMediaControls() {
        const microphoneEnabled = room.localParticipant.isMicrophoneEnabled;
        const cameraEnabled = room.localParticipant.isCameraEnabled;
        const screenEnabled = room.localParticipant.isScreenShareEnabled;
        microphoneButton.setAttribute('aria-pressed', String(microphoneEnabled));
        microphoneButton.textContent = microphoneEnabled ? 'Turn off microphone' : 'Turn on microphone';
        cameraButton.setAttribute('aria-pressed', String(cameraEnabled));
        cameraButton.textContent = cameraEnabled ? 'Turn off camera' : 'Turn on camera';
        screenButton.setAttribute('aria-pressed', String(screenEnabled));
        screenButton.textContent = screenEnabled ? 'Stop sharing screen' : 'Share screen';
    }

    function updateParticipantCount() {
        const count = participants.children.length;
        participantCount.textContent = `${count} ${count === 1 ? 'participant' : 'participants'}`;
    }

    async function refreshDevices() {
        if (!connected) return;
        for (const [kind, select, label] of [
            ['audioinput', microphoneDevice, 'Microphone'],
            ['videoinput', cameraDevice, 'Camera'],
        ]) {
            const devices = await Room.getLocalDevices(kind, false);
            const activeId = room.getActiveDevice(kind);
            select.replaceChildren(...devices.map((device, index) => new Option(device.label || `${label} ${index + 1}`, device.deviceId)));
            select.disabled = devices.length === 0;
            if (activeId && devices.some((device) => device.deviceId === activeId)) select.value = activeId;
        }
    }

    async function switchDevice(kind, select) {
        try {
            const switched = await room.switchActiveDevice(kind, select.value);
            if (!switched) throw new Error('Device change was not accepted.');
            setStatus(`${kind === 'audioinput' ? 'Microphone' : 'Camera'} changed.`);
        } catch {
            setStatus(`Could not change the ${kind === 'audioinput' ? 'microphone' : 'camera'}. Check browser permissions.`);
            await refreshDevices();
        }
    }

    function participantCard(identity, name) {
        let card = [...participants.children].find((element) => element.dataset.identity === identity);
        if (card) return card;
        const column = document.createElement('div');
        column.className = 'col-sm-6 col-xl-4';
        column.dataset.identity = identity;
        const panel = document.createElement('div');
        panel.className = 'card h-100';
        const body = document.createElement('div');
        body.className = 'card-body';
        const heading = document.createElement('h2');
        heading.className = 'h6';
        heading.textContent = name || identity;
        const media = document.createElement('div');
        media.className = 'meeting-participant-media';
        body.append(heading, media);
        panel.append(body);
        column.append(panel);
        participants.append(column);
        updateParticipantCount();
        return column;
    }

    function attachTrack(track, identity, name) {
        const card = participantCard(identity, name);
        const media = card.querySelector('.meeting-participant-media');
        const element = track.attach();
        element.classList.add('w-100', 'rounded');
        if (track.kind === Track.Kind.Video) element.setAttribute('playsinline', '');
        media.append(element);
    }

    function removeTrack(track) {
        track.detach().forEach((element) => element.remove());
    }

    room.on(RoomEvent.ParticipantConnected, (participant) => {
        participantCard(participant.identity, participant.name);
        setStatus(`${participant.name || 'A class member'} joined.`);
    });
    room.on(RoomEvent.ParticipantDisconnected, (participant) => {
        [...participants.children].find((element) => element.dataset.identity === participant.identity)?.remove();
        updateParticipantCount();
        setStatus(`${participant.name || 'A class member'} left.`);
    });
    room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
        attachTrack(track, participant.identity, participant.name);
    });
    room.on(RoomEvent.TrackUnsubscribed, (track) => removeTrack(track));
    room.on(RoomEvent.LocalTrackPublished, (publication) => {
        if (publication.track?.kind === Track.Kind.Video) {
            attachTrack(publication.track, room.localParticipant.identity, 'You');
        }
    });
    room.on(RoomEvent.LocalTrackUnpublished, (publication) => {
        if (publication.track) removeTrack(publication.track);
        if (publication.source === Track.Source.ScreenShare) {
            screenButton.textContent = 'Share screen';
            screenButton.setAttribute('aria-pressed', 'false');
        }
    });
    room.on(RoomEvent.Disconnected, () => {
        setControls(false);
        if (reconnectWanted && !joinWindowClosed()) queueReconnect();
        else setStatus('Disconnected from the meeting.');
    });
    room.on(RoomEvent.AudioPlaybackStatusChanged, () => {
        if (connected && !room.canPlaybackAudio) setStatus('Select Enable sound to hear other participants.');
    });

    async function connect(recovering = false) {
        if (connecting || connected || joinWindowClosed()) return;
        reconnectWanted = true;
        connecting = true;
        connectButton.disabled = true;
        setStatus(recovering ? 'Reconnecting…' : 'Connecting…');
        try {
            const response = await fetch(root.dataset.credentialsUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) {
                const error = new Error('Meeting access is unavailable. Check the schedule and your class membership.');
                error.retryable = response.status === 429 || response.status >= 500;
                throw error;
            }
            const { url, token } = await response.json();
            if (!reconnectWanted) return;
            await room.connect(url, token);
            if (!reconnectWanted) {
                room.disconnect();
                return;
            }
            participantCard(room.localParticipant.identity, 'You');
            for (const participant of room.remoteParticipants.values()) {
                participantCard(participant.identity, participant.name);
            }
            const mediaWarnings = [];
            if (recovering && mediaIntent.microphone && !room.localParticipant.isMicrophoneEnabled) {
                try { await room.localParticipant.setMicrophoneEnabled(true); }
                catch { mediaWarnings.push('microphone'); }
            }
            if (recovering && mediaIntent.camera && !room.localParticipant.isCameraEnabled) {
                try { await room.localParticipant.setCameraEnabled(true); }
                catch { mediaWarnings.push('camera'); }
            }
            if (!reconnectWanted) {
                room.disconnect();
                return;
            }
            reconnectAttempts = 0;
            setControls(true);
            syncMediaControls();
            await refreshDevices().catch(() => {});
            setStatus(mediaWarnings.length
                ? `Connected, but could not restore your ${mediaWarnings.join(' or ')}. Check browser permissions.`
                : 'Connected. Turn on your microphone or camera when ready.');
        } catch (error) {
            setControls(false);
            if (!recovering || error.retryable === false) {
                stopReconnect();
                setStatus(error.message || 'Could not connect to the meeting.');
            }
        } finally {
            connecting = false;
            if (recovering && reconnectWanted && !connected) queueReconnect();
        }
    }

    connectButton.addEventListener('click', () => connect());

    microphoneButton.addEventListener('click', async () => {
        try {
            const enabled = !room.localParticipant.isMicrophoneEnabled;
            await room.localParticipant.setMicrophoneEnabled(enabled);
            mediaIntent.microphone = enabled;
            syncMediaControls();
            await refreshDevices().catch(() => {});
        } catch {
            setStatus('Could not use your microphone. Check browser permissions.');
        }
    });
    soundButton.addEventListener('click', async () => {
        try {
            await room.startAudio();
            soundButton.textContent = 'Sound enabled';
            setStatus('Meeting sound enabled.');
        } catch {
            setStatus('Could not play meeting sound. Check browser permissions.');
        }
    });
    cameraButton.addEventListener('click', async () => {
        try {
            const enabled = !room.localParticipant.isCameraEnabled;
            await room.localParticipant.setCameraEnabled(enabled);
            mediaIntent.camera = enabled;
            syncMediaControls();
            await refreshDevices().catch(() => {});
        } catch {
            setStatus('Could not use your camera. Check browser permissions.');
        }
    });
    screenButton.addEventListener('click', async () => {
        try {
            const enabled = !room.localParticipant.isScreenShareEnabled;
            await room.localParticipant.setScreenShareEnabled(enabled);
            syncMediaControls();
        } catch {
            setStatus('Could not share your screen. Check browser permissions.');
        }
    });
    microphoneDevice.addEventListener('change', () => switchDevice('audioinput', microphoneDevice));
    cameraDevice.addEventListener('change', () => switchDevice('videoinput', cameraDevice));
    navigator.mediaDevices?.addEventListener?.('devicechange', () => refreshDevices().catch(() => {}));
    leaveButton.addEventListener('click', () => {
        stopReconnect();
        mediaIntent.microphone = false;
        mediaIntent.camera = false;
        room.disconnect();
    });
    window.addEventListener('pagehide', () => {
        stopReconnect();
        if (connected) room.disconnect();
    });
    if (Number.isFinite(closesAt)) {
        window.setTimeout(() => {
            stopReconnect();
            room.disconnect();
            connectButton.disabled = true;
            setStatus('The meeting join window has closed.');
        }, Math.max(0, closesAt - Date.now()));
    }
}

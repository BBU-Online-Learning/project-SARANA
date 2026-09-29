import { Room, RoomEvent, Track } from 'livekit-client';

const root = document.getElementById('class-meeting-room');

if (root) {
    const status = document.getElementById('meeting-room-status');
    const connectButton = document.getElementById('meeting-connect');
    const microphoneButton = document.getElementById('meeting-microphone');
    const soundButton = document.getElementById('meeting-sound');
    const cameraButton = document.getElementById('meeting-camera');
    const screenButton = document.getElementById('meeting-screen');
    const handButton = document.getElementById('meeting-hand');
    const shareAudioCheckbox = document.getElementById('meeting-share-audio');
    const leaveButton = document.getElementById('meeting-leave');
    const microphoneDevice = document.getElementById('meeting-microphone-device');
    const cameraDevice = document.getElementById('meeting-camera-device');
    const participantCount = document.getElementById('meeting-participant-count');
    const participants = document.getElementById('meeting-participants');
    const shareStage = document.getElementById('meeting-share-stage');
    const shareMedia = document.getElementById('meeting-share-media');
    const shareName = document.getElementById('meeting-share-name');
    const detailsButton = document.getElementById('meeting-details-toggle');
    const chatButton = document.getElementById('meeting-chat-toggle');
    const chatForm = document.getElementById('meeting-chat-form');
    const chatInput = document.getElementById('meeting-chat-input');
    const chatSend = document.getElementById('meeting-chat-send');
    const chatMessages = document.getElementById('meeting-chat-messages');
    const fullscreenButton = document.getElementById('meeting-fullscreen');
    const room = new Room({ adaptiveStream: true, dynacast: true });
    const closesAt = Date.parse(root.dataset.endAt);
    let connected = false;
    let connecting = false;
    let reconnectWanted = false;
    let reconnectTimer = null;
    let reconnectAttempts = 0;
    const screenShares = new Map();
    const seenChatMessages = new Set();
    const mediaIntent = { microphone: false, camera: false };
    let handRaised = false;

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

    function setControlLabel(button, label) {
        const text = button.querySelector('span');
        if (text) text.textContent = label;
        else button.textContent = label;
    }

    function renderScreenShare() {
        const activeShare = [...screenShares.values()].at(-1);
        root.classList.toggle('has-share', Boolean(activeShare));
        shareStage.hidden = !activeShare;
        shareMedia.replaceChildren(...(activeShare ? [activeShare.element] : []));
        if (activeShare) shareName.textContent = activeShare.name === 'You' ? 'You are sharing' : `${activeShare.name} is sharing`;
    }

    function clearScreenShares() {
        for (const track of screenShares.keys()) {
            track.detach().forEach((element) => element.remove());
        }
        screenShares.clear();
        renderScreenShare();
    }

    function appendChatMessage(message, sender) {
        if (!message?.message || seenChatMessages.has(message.id)) return;
        seenChatMessages.add(message.id);
        const entry = document.createElement('p');
        const author = document.createElement('strong');
        author.textContent = sender;
        entry.append(author, document.createTextNode(`  ${message.message}`));
        chatMessages.append(entry);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function setControls(active) {
        connected = active;
        root.classList.toggle('is-connected', active);
        connectButton.disabled = active;
        for (const button of [microphoneButton, soundButton, cameraButton, screenButton, handButton, leaveButton]) {
            button.disabled = !active;
        }
        chatInput.disabled = !active;
        chatSend.disabled = !active;
        if (!active) {
            clearScreenShares();
            participants.replaceChildren();
            updateParticipantCount();
            for (const button of [microphoneButton, cameraButton, screenButton]) {
                button.setAttribute('aria-pressed', 'false');
            }
            setControlLabel(microphoneButton, 'Turn on microphone');
            setControlLabel(cameraButton, 'Turn on camera');
            setControlLabel(screenButton, 'Share screen');
            handButton.setAttribute('aria-pressed', 'false');
            setControlLabel(handButton, 'Raise hand');
            setControlLabel(soundButton, 'Enable sound');
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
        setControlLabel(microphoneButton, microphoneEnabled ? 'Turn off microphone' : 'Turn on microphone');
        cameraButton.setAttribute('aria-pressed', String(cameraEnabled));
        setControlLabel(cameraButton, cameraEnabled ? 'Turn off camera' : 'Turn on camera');
        screenButton.setAttribute('aria-pressed', String(screenEnabled));
        setControlLabel(screenButton, screenEnabled ? 'Stop sharing screen' : 'Share screen');
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
        column.className = 'meeting-participant';
        column.dataset.identity = identity;
        const panel = document.createElement('div');
        panel.className = 'meeting-participant-card';
        const body = document.createElement('div');
        body.className = 'meeting-participant-body';
        const heading = document.createElement('h2');
        heading.className = 'meeting-participant-name';
        heading.textContent = name || identity;
        const hand = document.createElement('span');
        hand.className = 'meeting-participant-hand';
        hand.textContent = '✋ Hand raised';
        hand.hidden = true;
        const media = document.createElement('div');
        media.className = 'meeting-participant-media';
        const placeholder = document.createElement('div');
        placeholder.className = 'meeting-participant-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.textContent = (name || identity).trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
        media.append(placeholder);
        body.append(media, heading, hand);
        panel.append(body);
        column.append(panel);
        participants.append(column);
        updateParticipantCount();
        return column;
    }

    function syncHand(participant) {
        const card = participantCard(participant.identity, participant.isLocal ? 'You' : participant.name);
        const raised = participant.attributes?.['class.handRaised'] === 'true';
        card.querySelector('.meeting-participant-hand').hidden = !raised;
        if (participant.isLocal) {
            handButton.setAttribute('aria-pressed', String(raised));
            setControlLabel(handButton, raised ? 'Lower hand' : 'Raise hand');
        }
    }

    function attachTrack(track, identity, name, source) {
        const card = participantCard(identity, name);
        if (source === Track.Source.ScreenShare && track.kind === Track.Kind.Video) {
            const element = track.attach();
            element.setAttribute('playsinline', '');
            screenShares.set(track, { element, identity, name: name || identity });
            renderScreenShare();
            return;
        }
        const media = card.querySelector('.meeting-participant-media');
        const element = track.attach();
        if (track.kind === Track.Kind.Video) {
            element.setAttribute('playsinline', '');
            media.classList.add('has-video');
        }
        media.append(element);
    }

    function removeTrack(track) {
        if (screenShares.delete(track)) renderScreenShare();
        track.detach().forEach((element) => {
            const media = element.parentElement;
            element.remove();
            if (media && !media.querySelector('video')) media.classList.remove('has-video');
        });
    }

    room.on(RoomEvent.ParticipantConnected, (participant) => {
        syncHand(participant);
        setStatus(`${participant.name || 'A class member'} joined.`);
    });
    room.on(RoomEvent.ParticipantDisconnected, (participant) => {
        for (const [track, share] of screenShares) {
            if (share.identity === participant.identity) removeTrack(track);
        }
        [...participants.children].find((element) => element.dataset.identity === participant.identity)?.remove();
        updateParticipantCount();
        setStatus(`${participant.name || 'A class member'} left.`);
    });
    room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
        attachTrack(track, participant.identity, participant.name, publication.source);
    });
    room.on(RoomEvent.TrackUnsubscribed, (track) => removeTrack(track));
    room.on(RoomEvent.LocalTrackPublished, (publication) => {
        if (publication.track?.kind === Track.Kind.Video) {
            attachTrack(publication.track, room.localParticipant.identity, 'You', publication.source);
        }
    });
    room.on(RoomEvent.LocalTrackUnpublished, (publication) => {
        if (publication.track) removeTrack(publication.track);
        if (publication.source === Track.Source.ScreenShare) {
            setControlLabel(screenButton, 'Share screen');
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
    room.on(RoomEvent.ChatMessage, (message, participant) => {
        appendChatMessage(message, participant?.isLocal ? 'You' : participant?.name || 'Class member');
    });
    room.on(RoomEvent.ParticipantAttributesChanged, (changedAttributes, participant) => {
        if (Object.hasOwn(changedAttributes, 'class.handRaised')) syncHand(participant);
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
            syncHand(room.localParticipant);
            for (const participant of room.remoteParticipants.values()) {
                syncHand(participant);
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
            if (handRaised) {
                await room.localParticipant.setAttributes({ 'class.handRaised': 'true' }).catch(() => {
                    handRaised = false;
                });
                syncHand(room.localParticipant);
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

    detailsButton.addEventListener('click', () => {
        const expanded = !root.classList.contains('show-details');
        root.classList.toggle('show-details', expanded);
        root.classList.remove('show-chat');
        detailsButton.setAttribute('aria-expanded', String(expanded));
        chatButton.setAttribute('aria-expanded', 'false');
    });

    chatButton.addEventListener('click', () => {
        const expanded = !root.classList.contains('show-chat');
        root.classList.toggle('show-chat', expanded);
        root.classList.remove('show-details');
        chatButton.setAttribute('aria-expanded', String(expanded));
        detailsButton.setAttribute('aria-expanded', 'false');
        if (expanded) chatInput.focus();
    });

    chatForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = chatInput.value.trim();
        if (!connected || !message) return;
        chatSend.disabled = true;
        try {
            const sent = await room.localParticipant.sendChatMessage(message);
            appendChatMessage(sent, 'You');
            chatInput.value = '';
        } catch {
            setStatus('Could not send the chat message. Try again.');
        } finally {
            chatSend.disabled = !connected;
        }
    });

    fullscreenButton.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement === root) await document.exitFullscreen();
            else await root.requestFullscreen();
        } catch {
            setStatus('Full screen is unavailable in this browser.');
        }
    });
    document.addEventListener('fullscreenchange', () => {
        const active = document.fullscreenElement === root;
        fullscreenButton.setAttribute('aria-pressed', String(active));
        setControlLabel(fullscreenButton, active ? 'Exit full screen' : 'Full screen');
    });

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
            setControlLabel(soundButton, 'Sound enabled');
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
            await room.localParticipant.setScreenShareEnabled(enabled, enabled ? { audio: shareAudioCheckbox.checked } : undefined);
            syncMediaControls();
        } catch {
            setStatus('Could not share your screen. Check browser permissions.');
        }
    });
    handButton.addEventListener('click', async () => {
        if (!connected) return;
        handButton.disabled = true;
        try {
            const raised = !handRaised;
            await room.localParticipant.setAttributes({ 'class.handRaised': raised ? 'true' : '' });
            handRaised = raised;
            syncHand(room.localParticipant);
        } catch {
            setStatus('Could not update your raised hand. Try again.');
        } finally {
            handButton.disabled = !connected;
        }
    });
    microphoneDevice.addEventListener('change', () => switchDevice('audioinput', microphoneDevice));
    cameraDevice.addEventListener('change', () => switchDevice('videoinput', cameraDevice));
    navigator.mediaDevices?.addEventListener?.('devicechange', () => refreshDevices().catch(() => {}));
    leaveButton.addEventListener('click', () => {
        stopReconnect();
        mediaIntent.microphone = false;
        mediaIntent.camera = false;
        handRaised = false;
        room.disconnect();
        if (document.fullscreenElement === root) document.exitFullscreen().catch(() => {});
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

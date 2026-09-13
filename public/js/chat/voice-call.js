(() => {
    "use strict";
    const config = window.voiceCallConfig;
    if (!config || !window.CallMedia || !window.CallUI) return;
    if (typeof MediaStream === "undefined" || typeof RTCPeerConnection === "undefined") {
        document.addEventListener("click", (event) => {
            if (event.target.closest("[data-start-voice-call]")) window.AppNotifications?.error("Calling is not supported by this browser. Please use a current browser over HTTPS.");
        });
        return;
    }
    const ui = window.CallUI;
    const clientId = crypto.randomUUID ? crypto.randomUUID() : "10000000-1000-4000-8000-100000000000".replace(/[018]/g,
        (digit) => (digit ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> digit / 4).toString(16));
    let call = null, connection = null, pendingOffer = null, remoteStream = null;
    let ice = [], seenIce = new Set(), epoch = 0, starting = false, accepting = false;
    let ringTimer = null, connectTimer = null, disconnectTimer = null, negotiating = false;
    let polling = false, signaling = Promise.resolve(), subscribed = false, restarts = 0;
    let lastOffer = null, lastAnswer = null, iceServers = [], lastContact = Date.now();
    const finishedIds = new Set();
    const media = new window.CallMedia(() => {
        ui.local(media);
        if (owned() && call?.status === "active") sendMedia().catch(report);
    });
    const caller = () => Number(call?.initiated_by) === Number(config.userId);
    const owned = () => !!call?.participants?.some((person) => Number(person.id) === Number(config.userId) && person.client_id === clientId);
    const video = () => call?.call_type === "video";
    const live = (generation, id) => generation === epoch && call?.id === id;
    const endpoint = (id, action) => config.callBaseUrl + "/" + id + "/" + action;
    const post = (id, action, data = {}) => axios.post(endpoint(id, action), { ...data, client_id: clientId }, { timeout: 12000 });

    function message(error) {
        if (error?.name === "NotAllowedError") return "Microphone access is required. Allow it in your browser settings and try again.";
        if (error?.name === "NotFoundError") return "No microphone was found. Connect one and try again.";
        if (error?.name === "NotReadableError") return "The camera or microphone is in use by another application. Close it and try again.";
        return error?.response?.data?.errors?.call?.[0] || error?.response?.data?.message || error?.message || "The call could not connect. Please try again.";
    }
    function report(error) {
        window.AppNotifications?.error(message(error));
        if (config.debug) console.warn("Call operation failed", error?.name, error?.response?.status);
    }
    function reset() {
        epoch++;
        clearTimeout(ringTimer); clearTimeout(connectTimer); clearTimeout(disconnectTimer);
        ringTimer = connectTimer = disconnectTimer = null;
        if (connection) {
            connection.ontrack = connection.onicecandidate = connection.onconnectionstatechange = connection.oniceconnectionstatechange = null;
            connection.close(); connection = null;
        }
        media.stop(); ui.clear();
        call = pendingOffer = remoteStream = lastOffer = lastAnswer = null;
        ice = []; seenIce = new Set(); restarts = 0; negotiating = false;
    }
    async function finish(action = "end", label = "Call ended") {
        const ending = call;
        if (!ending) return;
        if (ending.id) finishedIds.add(ending.id);
        reset(); ui.finished(label);
        if (ending.id) {
            try { await post(ending.id, action); }
            catch (error) { report(error); }
        }
    }
    const fail = () => finish("fail", "Connection failed. Please try again.");
    async function fetchIce() {
        const response = await axios.get(config.iceUrl, { timeout: 12000 });
        return response.data.ice_servers;
    }
    async function sendSignal(type, data) {
        if (!call?.id || !owned()) return;
        return post(call.id, "signal", { type, data });
    }
    const sendMedia = () => sendSignal("media", media.state());

    function watchConnection() {
        clearTimeout(connectTimer);
        const id = call?.id, generation = epoch;
        connectTimer = setTimeout(() => {
            if (live(generation, id) && connection?.connectionState !== "connected") fail();
        }, 35000);
    }
    function createConnection() {
        if (connection) return connection;
        const generation = epoch, id = call.id;
        const pc = new RTCPeerConnection({ iceServers });
        connection = pc;
        if (caller()) media.attach(pc, video());
        remoteStream = new MediaStream();
        pc.ontrack = ({ track }) => {
            if (!live(generation, id)) return;
            if (!remoteStream.getTracks().some((item) => item.id === track.id)) remoteStream.addTrack(track);
            ui.remote(remoteStream, video());
            if (track.kind === "video") {
                track.onunmute = () => { if (live(generation, id)) ui.remoteState({ camera: true }); };
                track.onmute = () => { if (live(generation, id)) ui.remoteState({ camera: false }); };
            }
        };
        pc.onicecandidate = ({ candidate }) => {
            if (candidate && live(generation, id)) sendSignal("ice", { candidate: candidate.toJSON() }).catch(() => {
                if (live(generation, id)) ui.status("Signaling interrupted — reconnecting…");
            });
        };
        pc.onconnectionstatechange = () => {
            if (!live(generation, id)) return;
            if (pc.connectionState === "connected") {
                clearTimeout(connectTimer); clearTimeout(disconnectTimer); disconnectTimer = null;
                ui.status("Connected"); sendMedia().catch(report);
            } else if (["disconnected", "failed"].includes(pc.connectionState) || pc.iceConnectionState === "failed") {
                ui.status("Reconnecting…");
                if (!disconnectTimer) disconnectTimer = setTimeout(() => {
                    disconnectTimer = null;
                    if (live(generation, id) && pc.connectionState !== "connected") recover().catch(() => { if (live(generation, id)) fail(); });
                }, 3000);
            }
        };
        pc.oniceconnectionstatechange = () => {
            if (live(generation, id) && pc.iceConnectionState === "failed") pc.onconnectionstatechange();
        };
        return pc;
    }
    async function offer(restart = false) {
        if (!owned() || !caller() || call?.status !== "active" || negotiating) return;
        const pc = createConnection(), generation = epoch, id = call.id;
        if (pc.signalingState === "have-local-offer") {
            await sendSignal("offer", { description: pc.localDescription.toJSON() });
            return;
        }
        if (pc.signalingState !== "stable") return;
        negotiating = true;
        try {
            const description = await pc.createOffer({ iceRestart: restart });
            if (!live(generation, id)) return;
            await pc.setLocalDescription(description);
            if (!live(generation, id)) return;
            await sendSignal("offer", { description: pc.localDescription.toJSON() });
            watchConnection();
        } finally { if (live(generation, id)) negotiating = false; }
    }
    async function recover() {
        if (!owned() || call?.status !== "active") return;
        if (++restarts > 2) { await fail(); return; }
        const generation = epoch, id = call.id;
        const servers = await fetchIce();
        if (!live(generation, id)) return;
        connection?.setConfiguration({ iceServers: servers });
        watchConnection();
        if (caller()) await offer(true);
        else await sendSignal("restart", {});
    }
    async function flushIce() {
        const pc = connection;
        if (!pc?.remoteDescription) return;
        const pending = ice; ice = [];
        for (const candidate of pending) {
            if (pc !== connection) return;
            try { await pc.addIceCandidate(candidate); }
            catch (error) { if (config.debug) console.warn("Ignored stale ICE candidate", error.name); }
        }
    }
    async function answerPending() {
        if (!pendingOffer || !connection || !owned() || caller() || call?.status !== "active") return;
        const description = pendingOffer; pendingOffer = null;
        const pc = connection, generation = epoch, id = call.id;
        if (description.sdp === lastOffer && lastAnswer) {
            await sendSignal("answer", { description: lastAnswer }); return;
        }
        await pc.setRemoteDescription(description);
        if (!live(generation, id)) return;
        await media.answer(pc);
        if (!live(generation, id)) return;
        await flushIce();
        const answer = await pc.createAnswer();
        if (!live(generation, id)) return;
        await pc.setLocalDescription(answer);
        if (!live(generation, id)) return;
        lastOffer = description.sdp; lastAnswer = pc.localDescription.toJSON();
        await sendSignal("answer", { description: lastAnswer });
        watchConnection();
    }
    async function signal(event) {
        if (!call || event.call_id !== call.id || !owned() || event.from_user_id === Number(config.userId)) return;
        if (event.type === "offer" && !caller()) {
            pendingOffer = event.data.description;
            await answerPending();
        } else if (event.type === "answer" && caller() && connection?.signalingState === "have-local-offer") {
            const pc = connection;
            await pc.setRemoteDescription(event.data.description);
            if (pc === connection) await flushIce();
        } else if (event.type === "ice" && event.data.candidate) {
            const key = JSON.stringify(event.data.candidate);
            if (seenIce.has(key) || seenIce.size > 512) return;
            seenIce.add(key); ice.push(event.data.candidate); await flushIce();
        } else if (event.type === "media") ui.remoteState(event.data);
        else if (event.type === "restart" && caller()) await recover();
    }
    function queueSignal(event) {
        const generation = epoch;
        signaling = signaling.then(() => generation === epoch ? signal(event) : null).catch((error) => {
            if (generation === epoch) { report(error); fail(); }
        });
    }
    function render() {
        ui.show(call, config.userId); ui.local(media);
        clearTimeout(ringTimer);
        if (call.status === "ringing") {
            ui.status(caller() ? "Calling…" : "Incoming call");
            const id = call.id, generation = epoch;
            ringTimer = setTimeout(() => {
                if (live(generation, id)) finish("timeout", "Missed call");
            }, Math.max(0, Date.parse(call.expires_at) - Date.now() + 400));
        } else if (call.status === "active") ui.status(connection?.connectionState === "connected" ? "Connected" : "Connecting…");
    }
    function state({ call: incoming }) {
        if (!incoming || finishedIds.has(incoming.id)) return;
        if (call?.id && call.id !== incoming.id) return;
        if (call?.status === "preparing") return;
        if (!["ringing", "active"].includes(incoming.status)) {
            if (call?.id !== incoming.id) return;
            const labels = { declined: "Call declined", missed: "Missed call", cancelled: "Call cancelled", ended: "Call ended", failed: "Call failed" };
            finishedIds.add(incoming.id); reset(); ui.finished(labels[incoming.status] || "Call ended"); return;
        }
        if (call?.status === "active" && incoming.status === "ringing") return;
        const owner = incoming.participants.find((person) => Number(person.id) === Number(config.userId))?.client_id;
        if ((owner && owner !== clientId) || (incoming.status === "active" && !owner)) {
            if (call) { reset(); ui.close(); window.AppNotifications?.info("Call answered in another tab or device."); }
            return;
        }
        if (!call && Number(incoming.initiated_by) === Number(config.userId)) return;
        call = incoming; render();
        if (owned() && caller() && call.status === "active" && !connection?.remoteDescription) {
            const generation = epoch, id = call.id;
            offer().catch((error) => { if (live(generation, id)) { report(error); fail(); } });
        }
    }
    async function start(button) {
        if (starting || call) { window.AppNotifications?.warning("You already have a call in progress."); return; }
        if (!subscribed) { window.AppNotifications?.warning("Live updates are reconnecting. Try the call again shortly."); return; }
        starting = true;
        const generation = epoch;
        call = { status: "preparing", call_type: button.dataset.callType || "audio", initiated_by: Number(config.userId), participants: [
            { id: Number(config.userId), client_id: clientId }, { id: -1, name: button.dataset.peerName, avatar: button.dataset.peerAvatar },
        ] };
        ui.show(call, config.userId); ui.status(video() ? "Requesting camera and microphone…" : "Requesting microphone…");
        let created = null;
        try {
            iceServers = await fetchIce();
            if (generation !== epoch) return;
            const cameraError = await media.prepare(video());
            if (generation !== epoch) return;
            if (cameraError) window.AppNotifications?.warning("Camera unavailable. Continuing with audio; you can enable the camera during the call.");
            const response = await axios.post(button.dataset.startUrl, { call_type: call.call_type, client_id: clientId }, { timeout: 12000 });
            created = response.data.call;
            if (generation !== epoch) { await post(created.id, "cancel"); return; }
            call = created; createConnection(); render(); ui.devices(media);
            lastContact = Date.now();
        } catch (error) {
            if (generation !== epoch) return;
            if (created) await finish("fail", message(error));
            else { reset(); ui.finished(message(error)); }
            report(error);
        } finally { starting = false; }
    }
    async function accept(audioOnly = false) {
        if (!call || caller() || call.status !== "ringing" || accepting) return;
        accepting = true;
        const generation = epoch, id = call.id;
        ui.status("Requesting microphone and camera access…");
        try {
            iceServers = await fetchIce();
            if (!live(generation, id)) return;
            const cameraError = await media.prepare(video(), audioOnly);
            if (!live(generation, id)) return;
            const response = await post(id, "accept");
            if (!live(generation, id)) {
                if (response.data.call.status === "active") await post(id, "fail");
                return;
            }
            call = response.data.call; createConnection(); render(); ui.devices(media); watchConnection();
            await answerPending(); lastContact = Date.now();
            if (cameraError) window.AppNotifications?.warning("Camera unavailable. You joined with audio only.");
        } catch (error) {
            if (!live(generation, id)) return;
            if (owned()) await fail();
            else { media.stop(); ui.local(media); ui.status(message(error)); }
            report(error);
        } finally { accepting = false; }
    }
    async function reconcile() {
        if (polling || starting || accepting) return;
        polling = true;
        const generation = epoch, id = call?.id;
        try {
            if (owned() && id) {
                const response = await post(id, "heartbeat");
                if (live(generation, id)) { lastContact = Date.now(); state(response.data); }
            } else {
                const response = await axios.get(config.currentUrl, { timeout: 10000 });
                if (generation !== epoch) return;
                if (response.data.call) state(response.data);
                else if (call?.id) { reset(); ui.finished("Call ended"); }
            }
        } catch (error) {
            if (generation !== epoch) return;
            if ([401, 403, 404, 419, 422].includes(error.response?.status)) { reset(); ui.finished("Call access is no longer available."); }
            else if (owned()) {
                ui.status("Connection interrupted — reconnecting…");
                if (Date.now() - lastContact > 60000) await fail();
            }
        } finally { polling = false; }
    }
    function subscribe() {
        if (!window.Echo) return;
        window.Echo.private("user." + config.userId)
            .subscribed(() => {
                subscribed = true; reconcile();
                if (owned() && call?.status === "active") recover().catch(report);
            })
            .error(() => { subscribed = false; })
            .listen(".voice-call.state", state).listen(".voice-call.signal", queueSignal);
        window.Echo.connector?.pusher?.connection.bind("state_change", ({ current }) => {
            if (current !== "connected") {
                subscribed = false;
                if (owned()) ui.status("Live updates disconnected — reconnecting…");
            }
        });
    }
    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-start-voice-call]");
        if (button) { event.preventDefault(); start(button); return; }
        const action = event.target.closest("[data-voice-call-action]")?.dataset.voiceCallAction;
        if (action === "accept" || action === "retry") accept();
        else if (action === "accept-audio") accept(true);
        else if (action === "decline") finish("decline", "Call declined");
        else if (action === "end") finish(call?.status === "ringing" ? (caller() ? "cancel" : "decline") : "end");
        else if (action === "mute") media.toggleMute();
        else if (action === "camera" && video()) media.toggleCamera(connection).catch(report);
        else if (action === "switch-camera" && video()) media.switchCamera(connection).catch(report);
        else if (action === "play") {
            document.getElementById("voice-call-play").hidden = true;
            ui.remote(remoteStream, video());
        }
    });
    document.addEventListener("change", (event) => {
        if (event.target.id === "voice-call-microphone" && owned()) media.selectMicrophone(event.target.value, connection).then(() => ui.devices(media)).catch(report);
    });
    navigator.mediaDevices?.addEventListener?.("devicechange", () => { if (owned()) ui.devices(media); });
    window.addEventListener("pagehide", () => {
        if (owned() && call?.id) {
            const data = new FormData();
            data.append("_token", document.querySelector('meta[name="csrf-token"]')?.content || "");
            data.append("client_id", clientId);
            navigator.sendBeacon(endpoint(call.id, call.status === "ringing" ? "cancel" : "fail"), data);
        }
        reset();
    });
    function initialize() { subscribe(); reconcile(); setInterval(reconcile, 15000); }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialize, { once: true });
    else initialize();
    window.VoiceCallManager = { end: () => finish(call?.status === "ringing" ? "cancel" : "end"), cleanup: reset };
})();

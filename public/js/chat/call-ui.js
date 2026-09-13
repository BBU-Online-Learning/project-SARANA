(() => {
    "use strict";
    const element = (id) => document.getElementById(`voice-call-${id}`);
    let previousFocus = null;
    let timer = null;
    let closeTimer = null;
    let remoteMedia = { camera: false, microphone: true };

    function status(message) { element("status").textContent = message; }
    function show(call, userId) {
        clearTimeout(closeTimer);
        const layer = element("layer");
        const wasHidden = layer.hidden;
        if (wasHidden) previousFocus = document.activeElement;
        layer.hidden = false;
        const video = call.call_type === "video";
        const incoming = call.status === "ringing" && Number(call.initiated_by) !== Number(userId);
        layer.classList.toggle("is-video", video);
        layer.classList.toggle("is-active", call.status === "active");
        element("title").textContent = `${incoming ? "Incoming " : ""}${video ? "Video" : "Voice"} call`;
        const peer = call.participants?.find((person) => Number(person.id) !== Number(userId));
        element("peer-name").textContent = peer?.name || "Call";
        const image = element("avatar-image");
        image.hidden = !peer?.avatar;
        if (peer?.avatar) image.src = peer.avatar;
        else image.removeAttribute("src");
        element("avatar-fallback").hidden = !!peer?.avatar;
        element("avatar-fallback").textContent = (peer?.name || "?").trim().charAt(0).toUpperCase();
        element("incoming-actions").hidden = !incoming;
        element("active-actions").hidden = incoming;
        document.querySelectorAll('[data-voice-call-action="mute"], [data-voice-call-action="camera"], [data-voice-call-action="switch-camera"]').forEach((control) => {
            control.disabled = call.status === "preparing";
        });
        element("retry-actions").hidden = true;
        element("video-stage").hidden = !video || incoming;
        element("device-settings").hidden = incoming || call.status === "preparing";
        element("duration").hidden = call.status !== "active";
        element("open-chat").hidden = true;
        document.querySelectorAll("[data-video-control]").forEach((control) => { control.hidden = !video; });
        element("accept-audio").hidden = !video;
        clearInterval(timer);
        if (call.answered_at && call.status === "active") {
            const update = () => {
                const seconds = Math.max(0, Math.floor((Date.now() - Date.parse(call.answered_at)) / 1000));
                element("duration").textContent = `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
            };
            update(); timer = setInterval(update, 1000);
        }
        if (wasHidden) [...layer.querySelectorAll("button")].find((button) => !button.disabled && button.getClientRects().length)?.focus();
    }

    function local(media) {
        const video = element("local-video");
        if (video.srcObject !== media.stream) video.srcObject = media.stream;
        video.muted = true;
        video.style.transform = media.facing === "user" ? "scaleX(-1)" : "none";
        video.hidden = !media.state().camera;
        element("local-placeholder").hidden = media.state().camera;
        video.play()?.catch(() => {});
        for (const [action, active, off, on] of [
            ["mute", !media.state().microphone, "Unmute", "Mute"],
            ["camera", !media.state().camera, "Camera on", "Camera off"],
        ]) {
            const button = document.querySelector(`[data-voice-call-action="${action}"]`);
            button.setAttribute("aria-pressed", String(active));
            button.querySelector("span").textContent = active ? off : on;
        }
    }

    function remote(stream, video) {
        const target = element(video ? "remote-video" : "remote-audio");
        if (target.srcObject !== stream) target.srcObject = stream;
        target.play()?.then(() => { element("play").hidden = true; }).catch((error) => {
            if (error.name !== "AbortError" && target.srcObject === stream) element("play").hidden = false;
        });
    }

    function remoteState(state) {
        remoteMedia = { ...remoteMedia, ...state };
        state = remoteMedia;
        element("remote-placeholder").hidden = state.camera;
        element("remote-placeholder").querySelector("span").textContent = "Camera off";
        element("remote-video").hidden = !state.camera;
        element("peer-media").textContent = `${state.camera ? "" : "Camera off"}${!state.microphone ? " · Microphone muted" : ""}`;
    }

    async function devices(media) {
        const select = element("microphone");
        try {
            const items = await media.microphones();
            const selected = media.stream.getAudioTracks()[0]?.getSettings()?.deviceId;
            select.replaceChildren();
            items.forEach((device, index) => {
                const option = document.createElement("option");
                option.value = device.deviceId; option.textContent = device.label || `Microphone ${index + 1}`;
                option.selected = device.deviceId === selected;
                select.append(option);
            });
            select.disabled = items.length < 2;
        } catch (_) { select.disabled = true; }
    }

    function clear() {
        clearInterval(timer);
        ["remote-video", "local-video", "remote-audio"].forEach((id) => { element(id).pause(); element(id).srcObject = null; });
        element("play").hidden = true;
        remoteState({ camera: false, microphone: true });
    }

    function close() {
        clear();
        element("layer").hidden = true;
        if (previousFocus?.isConnected) previousFocus.focus();
    }

    function finished(message) {
        clear(); status(message);
        element("layer").classList.remove("is-active");
        ["incoming-actions", "active-actions", "retry-actions", "device-settings", "video-stage", "duration"].forEach((id) => { element(id).hidden = true; });
        closeTimer = setTimeout(close, 2500);
    }

    document.addEventListener("keydown", (event) => {
        if (element("layer").hidden || event.key !== "Tab") return;
        const focusable = [...element("layer").querySelectorAll("button, select, summary, a[href]")].filter((item) => !item.disabled && item.getClientRects().length);
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    window.CallUI = { show, status, local, remote, remoteState, devices, clear, close, finished };
})();

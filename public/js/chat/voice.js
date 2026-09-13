// File: D:\education\Laravel_Project\Elearning\public\js\chat\voice.js

(() => {
    "use strict";

    let recorder = null;
    let stream = null;
    let chunks = [];
    let previewUrl = null;
    let recordedFile = null;
    let pendingAutoSend = false;

    function composer() {
        return document.querySelector(".teams-composer");
    }

    function bodyInput() {
        return document.querySelector('#message-form input[name="body"]');
    }

    function overlay() {
        return document.getElementById("voice-overlay");
    }

    function audioEl() {
        return document.getElementById("voice-preview-audio");
    }

    function recordBtn() {
        return document.getElementById("voice-record-btn");
    }

    function sendBtn() {
        return document.getElementById("voice-send-btn");
    }

    function cancelBtn() {
        return document.getElementById("voice-cancel-btn");
    }

    function setComposerLocked(locked) {
        // Hide the text composer while voice mode is active.
        const composerEl = composer();
        const input = bodyInput();

        if (composerEl) {
            composerEl.classList.toggle("is-voice-active", locked);
        }

        if (input) {
            input.disabled = locked;
        }
    }

    function showOverlay(mode) {
        const el = overlay();
        const status = document.getElementById("voice-record-status");
        const audio = audioEl();

        if (!el) return;

        el.hidden = false;
        el.dataset.mode = mode;

        if (status) {
            status.hidden = mode !== "recording";
        }

        if (audio) {
            audio.hidden = mode !== "preview";
        }
    }

    function hideOverlay() {
        const el = overlay();
        const status = document.getElementById("voice-record-status");
        const audio = audioEl();

        if (!el) return;

        el.hidden = true;
        delete el.dataset.mode;

        if (status) {
            status.hidden = true;
        }

        if (audio) {
            audio.hidden = true;
            audio.removeAttribute("src");
        }
    }

    function stopTracks() {
        if (!stream) return;

        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    function clearDraft() {
        recordedFile = null;

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        chunks = [];

        const audio = audioEl();
        if (audio) {
            audio.removeAttribute("src");
            audio.hidden = true;
        }

        window.chat.voiceDraftFile = null;
    }

    function resetVoiceUi() {
        pendingAutoSend = false;
        clearDraft();
        recorder = null;
        stopTracks();
        setComposerLocked(false);
        hideOverlay();

        const btn = recordBtn();
        if (btn) {
            btn.innerHTML = '<i class="ti ti-microphone"></i>';
        }
    }

    async function sendRecordedVoice(file) {
        if (!file || !window.chat.activeRoomId) {
            return;
        }

        await window.ChatMessages?.sendVoiceDraft?.(file);
    }

    async function startRecording() {
        window.StickerPicker?.close?.();

        if (
            !navigator.mediaDevices?.getUserMedia ||
            typeof MediaRecorder === "undefined"
        ) {
            window.AppNotifications?.warning("This browser does not support voice messages.");
            return;
        }

        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks = [];
        pendingAutoSend = false;

        const mimeTypes = [
            "audio/webm;codecs=opus",
            "audio/webm",
            "audio/ogg;codecs=opus",
            "audio/ogg",
            "audio/mp4",
        ];

        const mimeType = mimeTypes.find((type) =>
            MediaRecorder.isTypeSupported(type),
        );
        recorder = mimeType
            ? new MediaRecorder(stream, { mimeType })
            : new MediaRecorder(stream);

        showOverlay("recording");
        setComposerLocked(true);

        const btn = recordBtn();
        if (btn) {
            btn.innerHTML = '<i class="ti ti-square"></i>';
        }

        recorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                chunks.push(event.data);
            }
        };

        recorder.onstop = async () => {
            const activeMimeType =
                recorder?.mimeType || chunks[0]?.type || "audio/webm";
            const blob = new Blob(chunks, { type: activeMimeType });

            const extension = activeMimeType.includes("mp4")
                ? "m4a"
                : activeMimeType.includes("ogg")
                  ? "ogg"
                  : activeMimeType.includes("mpeg")
                    ? "mp3"
                    : "webm";

            // Store the recorded voice as a file so the backend receives it like an attachment.
            recordedFile = new File(
                [blob],
                `voice-message-${Date.now()}.${extension}`,
                { type: activeMimeType },
            );

            window.chat.voiceDraftFile = recordedFile;

            stopTracks();

            const btn = recordBtn();
            if (btn) {
                btn.innerHTML = '<i class="ti ti-microphone"></i>';
            }

            recorder = null;
            chunks = [];

            if (pendingAutoSend) {
                // One click on Send while recording: stop and send immediately.
                await sendRecordedVoice(recordedFile);
                return;
            }

            // If recording was stopped manually, show preview and let Send submit it.
            const audio = audioEl();
            if (audio) {
                previewUrl = URL.createObjectURL(blob);
                audio.src = previewUrl;
                audio.hidden = false;
            }

            showOverlay("preview");
            setComposerLocked(true);
        };

        recorder.start();
    }

    function stopRecording() {
        if (!recorder) return;
        recorder.stop();
    }

    function cancelVoice() {
        pendingAutoSend = false;

        if (recorder) {
            recorder.onstop = null;
            recorder.stop();
            recorder = null;
        }

        resetVoiceUi();
    }

    async function handleSendClick() {
        if (recorder) {
            // Send button stops recording first, then the recorder callback sends the file.
            pendingAutoSend = true;
            stopRecording();
            return;
        }

        if (window.chat.voiceDraftFile) {
            await sendRecordedVoice(window.chat.voiceDraftFile);
        }
    }

    document.addEventListener("click", async (event) => {
        const btn = event.target.closest("#voice-record-btn");
        if (!btn) return;

        event.preventDefault();

        try {
            if (recorder) {
                // Clicking the mic button again stops the recording.
                stopRecording();
                return;
            }

            if (window.chat.voiceDraftFile) {
                resetVoiceUi();
            }

            await startRecording();
        } catch (error) {
            console.error("Voice recording failed:", error);
            resetVoiceUi();
            window.AppNotifications?.error("Microphone permission is required to send voice messages.");
        }
    });

    document.addEventListener("click", async (event) => {
        if (event.target.closest("#voice-cancel-btn")) {
            event.preventDefault();
            cancelVoice();
            return;
        }

        if (event.target.closest("#voice-send-btn")) {
            event.preventDefault();
            await handleSendClick();
        }
    });

    // Safety reset when leaving the page.
    window.addEventListener("pagehide", () => {
        resetVoiceUi();
    });

    window.ChatVoice = {
        reset: resetVoiceUi,
        send: handleSendClick,
    };
})();

<div id="voice-call-layer" class="voice-call-layer" role="dialog" aria-modal="true"
    aria-labelledby="voice-call-title" hidden>
    <div class="voice-call-panel">
        <div class="voice-call-kind" id="voice-call-title">Voice call</div>
        <div class="voice-call-avatar" aria-hidden="true">
            <img id="voice-call-avatar-image" alt="" hidden>
            <span id="voice-call-avatar-fallback">?</span>
        </div>
        <h2 id="voice-call-peer-name">Voice call</h2>
        <p id="voice-call-status" class="voice-call-status" aria-live="polite">Connecting…</p>
        <div id="voice-call-duration" class="voice-call-duration" hidden>00:00</div>
        <div id="voice-call-video-stage" class="call-video-stage" hidden>
            <video id="voice-call-remote-video" class="call-remote-video" autoplay playsinline aria-label="Other participant's video" hidden></video>
            <div id="voice-call-remote-placeholder" class="call-video-placeholder">
                <i class="ti ti-video-off" aria-hidden="true"></i>
                <span>Waiting for video</span>
            </div>
            <div class="call-local-preview">
                <video id="voice-call-local-video" autoplay muted playsinline aria-label="Your video preview" hidden></video>
                <span id="voice-call-local-placeholder">Your camera is off</span>
                <span class="call-preview-label">You</span>
            </div>
            <span id="voice-call-peer-media" class="call-peer-media" aria-live="polite"></span>
        </div>
        <button type="button" id="voice-call-play" class="btn btn-light" data-voice-call-action="play" hidden>Tap to play call audio and video</button>

        <div id="voice-call-incoming-actions" class="voice-call-actions" hidden>
            <button type="button" class="voice-call-action voice-call-decline" data-voice-call-action="decline">
                <i class="ti ti-phone-off" aria-hidden="true"></i>
                <span>Decline</span>
            </button>
            <button type="button" class="voice-call-action voice-call-accept" data-voice-call-action="accept">
                <i class="ti ti-phone" aria-hidden="true"></i>
                <span>Accept</span>
            </button>
            <button type="button" id="voice-call-accept-audio" class="voice-call-action voice-call-secondary"
                data-voice-call-action="accept-audio" hidden>
                <i class="ti ti-microphone" aria-hidden="true"></i>
                <span>Audio only</span>
            </button>
        </div>

        <div id="voice-call-active-actions" class="voice-call-actions" hidden>
            <button type="button" class="voice-call-action voice-call-secondary" data-voice-call-action="mute"
                aria-pressed="false">
                <i class="ti ti-microphone" aria-hidden="true"></i>
                <span>Mute</span>
            </button>
            <button type="button" class="voice-call-action voice-call-secondary" data-voice-call-action="camera"
                data-video-control aria-pressed="false" hidden>
                <i class="ti ti-video" aria-hidden="true"></i>
                <span>Camera off</span>
            </button>
            <button type="button" class="voice-call-action voice-call-secondary" data-voice-call-action="switch-camera"
                data-video-control hidden>
                <i class="ti ti-refresh" aria-hidden="true"></i>
                <span>Switch camera</span>
            </button>
            <button type="button" class="voice-call-action voice-call-decline" data-voice-call-action="end">
                <i class="ti ti-phone-off" aria-hidden="true"></i>
                <span>End</span>
            </button>
        </div>

        <div id="voice-call-retry-actions" class="voice-call-actions" hidden>
            <button type="button" class="voice-call-action voice-call-accept" data-voice-call-action="retry">
                <i class="ti ti-microphone" aria-hidden="true"></i>
                <span>Try microphone again</span>
            </button>
            <button type="button" class="voice-call-action voice-call-decline" data-voice-call-action="decline">
                <i class="ti ti-phone-off" aria-hidden="true"></i>
                <span>Decline</span>
            </button>
        </div>

        <details id="voice-call-device-settings" class="call-device-settings" hidden>
            <summary>Audio settings</summary>
            <label for="voice-call-microphone">Microphone</label>
            <select id="voice-call-microphone" class="form-select" aria-label="Choose microphone"></select>
        </details>

        <a id="voice-call-open-chat" class="voice-call-open-chat" href="{{ route('chat.index') }}" hidden>
            Open chat
        </a>
        <audio id="voice-call-remote-audio" autoplay playsinline></audio>
    </div>
</div>

import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';

function MeetingRoom({ config }) {
    useEffect(() => {
        import('./meeting-room.js');
    }, []);

    return <div className="page-container">
        <header className="meeting-page-bar">
            <a href={config.backUrl} className="meeting-page-back"><i className="ti ti-arrow-left" aria-hidden="true" /> Meeting details</a>
            <div className="meeting-page-title"><strong>{config.meetingTitle}</strong><span>{config.className}</span></div>
            <span className="meeting-page-note">Joining does not mark attendance</span>
        </header>
        <section id="class-meeting-room" className="meeting-room-shell" aria-label="Live meeting room"
            data-credentials-url={config.credentialsUrl} data-can-manage={config.canManage}
            data-waiting-room-url={config.waitingRoomUrl} data-join-requests-url={config.joinRequestsUrl}
            data-end-url={config.endUrl} data-remove-url-template={config.removeUrlTemplate}
            data-removable-user-ids={config.removableUserIds} data-can-end={config.canEnd}
            data-end-at={config.endAt}>
            <div className="meeting-room-topbar">
                <div className="meeting-room-heading">
                    <div className="meeting-live-mark"><i className="ti ti-video" aria-hidden="true" /></div>
                    <div><h2 className="h5 mb-1">Live classroom</h2><p id="meeting-room-status" className="mb-0" role="status" aria-live="polite">{config.canManage === 'true' ? 'Ready to connect. Your microphone and camera will start off.' : 'Ask your teacher to let you into the meeting.'}</p></div>
                </div>
                <div className="meeting-room-top-actions">
                    <span id="meeting-participant-count" className="meeting-participant-count" aria-live="polite">0 participants</span>
                    <button id="meeting-chat-toggle" type="button" className="meeting-top-button" aria-controls="meeting-chat-side" aria-expanded="false"><i className="ti ti-messages" aria-hidden="true" /> <span>Chat</span></button>
                    <button id="meeting-details-toggle" type="button" className="meeting-top-button" aria-controls="meeting-room-side" aria-expanded="false"><i className="ti ti-adjustments-horizontal" aria-hidden="true" /> <span>Details</span>{config.canManage === 'true' && <span id="meeting-waiting-top-count" className="meeting-waiting-top-count" hidden>0</span>}</button>
                    <button id="meeting-fullscreen" type="button" className="meeting-top-button" aria-pressed="false"><i className="ti ti-maximize" aria-hidden="true" /> <span>Full screen</span></button>
                </div>
            </div>
            <div className="meeting-room-content">
                <div className="meeting-stage">
                    <div id="meeting-prejoin" className="meeting-prejoin">
                        <div className="meeting-prejoin-icon"><i className="ti ti-video" aria-hidden="true" /></div>
                        <h3>Ready for class?</h3>
                        <p>{config.canManage === 'true' ? 'Connect to the room first. Your microphone and camera start off, and you can choose when to turn them on.' : 'Request entry and wait for a teacher to admit you. Your microphone and camera will start off.'}</p>
                        <button id="meeting-connect" type="button" className="btn btn-primary meeting-control meeting-control-connect" disabled={config.canManage !== 'true'}><i className="ti ti-door-enter" aria-hidden="true" /> Connect to meeting</button>
                        {config.canManage !== 'true' && <div id="meeting-waiting-student" className="meeting-waiting-student">
                            <button id="meeting-request-entry" type="button" className="btn btn-primary">Request to join</button>
                            <button id="meeting-cancel-entry" type="button" className="btn btn-outline-secondary" hidden>Cancel request</button>
                            <p id="meeting-waiting-status" role="status" aria-live="polite">Checking your entry status…</p>
                        </div>}
                    </div>
                    <div id="meeting-share-stage" className="meeting-share-stage" aria-label="Shared screen" hidden>
                        <div className="meeting-share-heading"><i className="ti ti-screen-share" aria-hidden="true" /> <strong id="meeting-share-name">Screen share</strong><span>Presentation</span></div>
                        <div id="meeting-share-media" className="meeting-share-media" />
                    </div>
                    <div id="meeting-participants" className="meeting-participants" aria-label="Meeting participants" />
                </div>
                <aside id="meeting-room-side" className="meeting-room-side" aria-label="Room information and settings">
                    <div className="meeting-side-section"><span className="meeting-side-eyebrow">CLASS SESSION</span><h3>{config.meetingTitle}</h3><p><i className="ti ti-calendar-event" aria-hidden="true" /> {config.startLabel}</p><p><i className="ti ti-school" aria-hidden="true" /> {config.className}</p></div>
                    <div className="meeting-side-section"><h3 className="h6">Your devices</h3><p>Choose your microphone and camera after connecting.</p>
                        <div id="meeting-device-settings" className="meeting-device-grid">
                            <div><label className="form-label" htmlFor="meeting-microphone-device">Microphone</label><select id="meeting-microphone-device" className="form-select" disabled><option>Connect to choose a device</option></select></div>
                            <div><label className="form-label" htmlFor="meeting-camera-device">Camera</label><select id="meeting-camera-device" className="form-select" disabled><option>Connect to choose a device</option></select></div>
                        </div>
                    </div>
                    {config.canManage === 'true' && <div className="meeting-side-section meeting-waiting-host" id="meeting-waiting-host">
                        <h3 className="h6">Waiting room <span id="meeting-waiting-count" className="badge bg-primary">0</span></h3>
                        <p>Admit class members when you are ready.</p>
                        <div id="meeting-waiting-requests" aria-live="polite">No one is waiting.</div>
                    </div>}
                    <div className="meeting-side-tip"><i className="ti ti-info-circle" aria-hidden="true" /><span>Allow your browser to use the microphone or camera when you turn them on.</span></div>
                </aside>
                <aside id="meeting-chat-side" className="meeting-chat-side" aria-label="Meeting chat">
                    <h3>Meeting chat</h3>
                    <p className="meeting-chat-note">Messages are visible to people in this room and disappear after you leave.</p>
                    <div id="meeting-chat-messages" className="meeting-chat-messages" role="log" aria-live="polite" />
                    <form id="meeting-chat-form" className="meeting-chat-form">
                        <label className="visually-hidden" htmlFor="meeting-chat-input">Message</label>
                        <input id="meeting-chat-input" type="text" maxLength="500" placeholder="Message everyone" autoComplete="off" disabled />
                        <button id="meeting-chat-send" type="submit" aria-label="Send message" disabled><i className="ti ti-send" aria-hidden="true" /></button>
                    </form>
                </aside>
            </div>
            <div className="meeting-controls" aria-label="Meeting controls">
                <button id="meeting-microphone" type="button" className="btn meeting-control" aria-pressed="false" disabled><i className="ti ti-microphone" aria-hidden="true" /> <span>Turn on microphone</span></button>
                <button id="meeting-camera" type="button" className="btn meeting-control" aria-pressed="false" disabled><i className="ti ti-video" aria-hidden="true" /> <span>Turn on camera</span></button>
                <button id="meeting-screen" type="button" className="btn meeting-control" aria-pressed="false" disabled><i className="ti ti-screen-share" aria-hidden="true" /> <span>Share screen</span></button>
                <button id="meeting-hand" type="button" className="btn meeting-control" aria-pressed="false" disabled><i className="ti ti-hand-stop" aria-hidden="true" /> <span>Raise hand</span></button>
                <label className="meeting-share-audio-option" title="Share audio when the browser supports it"><input id="meeting-share-audio" type="checkbox" /> Include tab audio</label>
                <button id="meeting-sound" type="button" className="btn meeting-control" disabled><i className="ti ti-volume" aria-hidden="true" /> <span>Enable sound</span></button>
                <button id="meeting-leave" type="button" className="btn meeting-control meeting-control-leave" disabled><i className="ti ti-door-exit" aria-hidden="true" /> <span>Leave meeting</span></button>
                {config.canEnd === 'true' && <button id="meeting-end" type="button" className="btn meeting-control meeting-control-leave"><i className="ti ti-player-stop" aria-hidden="true" /> <span>End for everyone</span></button>}
            </div>
        </section>
    </div>;
}

const mount = document.getElementById('meeting-react-root');

if (mount) {
    createRoot(mount).render(<MeetingRoom config={{ ...mount.dataset }} />);
}

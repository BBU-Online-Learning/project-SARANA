<section class="group-invite-panel" data-group-invite-panel aria-labelledby="group-invite-title">
    <div class="group-invite-heading">
        <span class="group-invite-heading-icon" aria-hidden="true"><i class="ti ti-qrcode"></i></span>
        <div>
            <h3 id="group-invite-title">Invite with link or QR</h3>
            <p>Anyone with an active account can use this invitation to join this group.</p>
        </div>
    </div>

    <p class="group-invite-status" data-group-invite-status role="status" aria-live="polite"></p>

    @if($groupInviteData)
        <div class="group-invite-active">
            <div class="group-invite-qr">
                <img src="{{ $groupInviteData['qr_code'] }}" alt="QR code for joining {{ $room->name }}">
                <span>Scan with another phone</span>
                <button type="button" class="group-invite-download" data-download-group-invite-qr data-download-name="{{ Str::slug($room->name) }}-group-invite.png"><i class="ti ti-download" aria-hidden="true"></i> Download PNG</button>
            </div>
            <div class="group-invite-details">
                <p class="group-invite-instructions">Open the Camera app on another phone, point it at the QR code, then tap the link that appears.</p>
                <label for="group-invite-url-{{ $room->id }}">Invitation link</label>
                <div class="group-invite-link-row">
                    <input id="group-invite-url-{{ $room->id }}" type="text" readonly value="{{ $groupInviteData['url'] }}" data-group-invite-url>
                    <button type="button" class="btn btn-primary" data-copy-group-invite aria-label="Copy invitation link"><i class="ti ti-copy" aria-hidden="true"></i><span>Copy</span></button>
                </div>
                <p><i class="ti ti-clock" aria-hidden="true"></i> Expires {{ $groupInviteData['invite']->expires_at->diffForHumans() }}</p>
                <div class="group-invite-actions">
                    <button type="button" class="btn btn-outline-primary" data-share-group-invite data-share-title="Join {{ $room->name }}"><i class="ti ti-share-3" aria-hidden="true"></i> Share</button>
                    <form method="POST" action="{{ route('chat.groups.invites.store', $room) }}" data-group-invite-create>
                        @csrf
                        <button type="submit" class="btn btn-outline-primary"><i class="ti ti-refresh" aria-hidden="true"></i> New link</button>
                    </form>
                    <form method="POST" action="{{ route('chat.groups.invites.destroy', [$room, $groupInviteData['invite']]) }}" data-group-invite-revoke>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="ti ti-link-off" aria-hidden="true"></i> Disable</button>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="group-invite-empty">
            <i class="ti ti-link-plus" aria-hidden="true"></i>
            <p>Create a secure invitation that expires automatically after seven days.</p>
            <form method="POST" action="{{ route('chat.groups.invites.store', $room) }}" data-group-invite-create>
                @csrf
                <button type="submit" class="btn btn-primary"><i class="ti ti-link" aria-hidden="true"></i> Create invitation</button>
            </form>
        </div>
    @endif
</section>

<div class="modal fade forward-message-modal" id="forwardMessageModal" tabindex="-1"
    aria-labelledby="forward-message-title" aria-hidden="true" data-forward-url="{{ route('chat.messages.forward') }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="forward-message-eyebrow mb-1">Share in chat</p>
                    <h2 class="modal-title h5 mb-0" id="forward-message-title">Forward message</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="forward-source-summary" aria-live="polite">
                    <span class="forward-source-icon"><i class="ti ti-arrow-forward-up" aria-hidden="true"></i></span>
                    <div><strong data-forward-source-count>1 message</strong><span data-forward-source-preview></span></div>
                </div>

                <label class="forward-search" for="forward-room-search">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input type="search" id="forward-room-search" placeholder="Search conversations" autocomplete="off">
                </label>

                <div class="forward-room-filters" role="group" aria-label="Conversation type">
                    <button type="button" class="active" data-forward-filter="all" aria-pressed="true">All</button>
                    <button type="button" data-forward-filter="direct" aria-pressed="false">Direct</button>
                    <button type="button" data-forward-filter="group" aria-pressed="false">Groups</button>
                </div>

                <div class="forward-room-list" data-forward-room-list role="group" aria-label="Choose conversations">
                    @foreach ($rooms as $forwardRoom)
                        @php
                            $forwardIsDirect = $forwardRoom->type === 'direct';
                            $forwardPerson = $forwardIsDirect ? $forwardRoom->members->firstWhere('id', '!=', auth()->id()) : null;
                            $forwardName = $forwardIsDirect ? ($forwardPerson?->name ?? 'Direct chat') : ($forwardRoom->name ?? 'Group chat');
                            $forwardAvatar = $forwardIsDirect ? $forwardPerson?->profileUrl() : $forwardRoom->avatarUrl();
                        @endphp
                        <button type="button" class="forward-room-option" data-forward-room="{{ $forwardRoom->id }}"
                            data-forward-room-type="{{ $forwardRoom->type }}" data-forward-room-name="{{ Str::lower($forwardName) }}"
                            aria-pressed="false">
                            <span class="forward-room-avatar">
                                @if ($forwardAvatar)
                                    <img src="{{ $forwardAvatar }}" alt="" loading="lazy">
                                @else
                                    {{ Str::upper(Str::substr($forwardName, 0, 1)) }}
                                @endif
                            </span>
                            <span class="forward-room-copy">
                                <strong>{{ $forwardName }}</strong>
                                <small>{{ $forwardIsDirect ? 'Direct chat' : 'Group chat' }}</small>
                            </span>
                            <span class="forward-room-check"><i class="ti ti-check" aria-hidden="true"></i></span>
                        </button>
                    @endforeach
                </div>
                <p class="forward-room-empty" data-forward-empty hidden>No conversations match your search.</p>

                <label class="forward-note-label" for="forward-note">Add a message <span>(optional)</span></label>
                <textarea id="forward-note" rows="2" maxlength="5000" placeholder="Write a message before the forwarded item…"></textarea>
                <div class="alert alert-danger mt-3 mb-0" data-forward-error role="alert" hidden></div>
            </div>
            <div class="modal-footer forward-message-footer">
                <span data-forward-destination-count>Choose a conversation</span>
                <div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-forward-submit disabled>
                        <i class="ti ti-send" aria-hidden="true"></i> Forward
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $recentContactIds = $rooms
        ->where('type', 'direct')
        ->map(fn ($room) => $room->members->firstWhere('id', '!=', auth()->id())?->id)
        ->filter()
        ->unique()
        ->take(6)
        ->values();
    $chatPeople = $users->map(fn ($user) => [
        'id' => $user->id,
        'name' => $user->name,
        'initials' => $user->initials(),
        'avatar' => $user->profileUrl(),
        'role' => $user->role?->name,
        'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        'recent' => $recentContactIds->contains($user->id),
    ])->values();
@endphp

<div class="modal fade create-chat-modal" id="createChatModal" tabindex="-1" aria-labelledby="create-chat-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="create-chat-eyebrow mb-1">Start a conversation</p>
                    <h2 class="modal-title h5 mb-0" id="create-chat-title">New chat</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close create chat"></button>
            </div>

            <div class="modal-body">
                <div id="create-chat-error" class="alert alert-danger" role="alert" tabindex="-1" hidden></div>
                @if ($users->isEmpty())
                    <div class="create-chat-empty" role="status">
                        <i class="ti ti-users-off" aria-hidden="true"></i>
                        <p>No other active users are available.</p>
                        <span>You can still create an empty group and add members later.</span>
                    </div>
                @endif

                <div class="create-chat-tabs" role="tablist" aria-label="Conversation type">
                    <button type="button" class="create-chat-tab active" id="direct-chat-tab" data-bs-toggle="tab"
                        data-bs-target="#direct-tab" role="tab" aria-controls="direct-tab" aria-selected="true">
                        <i class="ti ti-user" aria-hidden="true"></i> Direct chat
                    </button>
                    <button type="button" class="create-chat-tab" id="group-chat-tab" data-bs-toggle="tab"
                        data-bs-target="#group-tab" role="tab" aria-controls="group-tab" aria-selected="false">
                        <i class="ti ti-users" aria-hidden="true"></i> Group chat
                    </button>
                </div>

                <div class="tab-content">
                    <section class="tab-pane fade show active" id="direct-tab" role="tabpanel" aria-labelledby="direct-chat-tab" tabindex="0">
                        <h3 class="h6 mt-3 mb-1">New chat</h3>
                        <p class="text-muted small mb-3">Find one person to start or reopen a conversation.</p>
                        @include('chat.partials.create-chat-people-picker', ['picker' => 'direct'])

                        <select id="direct-user-id" class="visually-hidden" tabindex="-1" aria-hidden="true">
                            <option value="">Select user</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>

                        <div class="create-chat-actions">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="create-direct-btn" class="btn btn-primary" disabled>Start chat</button>
                        </div>
                    </section>

                    <section class="tab-pane fade" id="group-tab" role="tabpanel" aria-labelledby="group-chat-tab" tabindex="0">
                        <div data-group-step="details">
                            <h3 class="h6 mt-3 mb-1">New group</h3>
                            <p class="text-muted small mb-3">Name your group, then add people now or later.</p>
                            <div class="mb-3">
                                <label for="group-name" class="form-label">Group name</label>
                                <input type="text" id="group-name" class="form-control" minlength="3" maxlength="100"
                                    placeholder="Enter group name..." autocomplete="off" aria-describedby="group-name-help">
                                <div id="group-name-help" class="form-text">Use between 3 and 100 characters.</div>
                            </div>

                            <div class="create-chat-label-row">
                                <span class="form-label mb-0">Add people <span class="text-muted fw-normal">(optional)</span></span>
                                <span class="create-chat-selection-count" id="group-selection-count">0 selected</span>
                            </div>
                            <div id="group-selected-chips" class="create-chat-chips" aria-label="Selected group members"></div>
                            @include('chat.partials.create-chat-people-picker', ['picker' => 'group'])

                            <select multiple id="group-members" class="visually-hidden" tabindex="-1" aria-hidden="true">
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>

                            <div class="create-chat-actions">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" id="group-next-btn" class="btn btn-primary" disabled>Next</button>
                            </div>
                        </div>

                        <div data-group-step="review" hidden>
                            <button type="button" class="create-chat-back" id="group-back-btn">
                                <i class="ti ti-arrow-left" aria-hidden="true"></i> Back to editing
                            </button>
                            <div class="create-chat-review-heading">
                                <span class="create-chat-group-icon"><i class="ti ti-users" aria-hidden="true"></i></span>
                                <div><p class="text-muted small mb-1">New group</p><h3 class="h5 mb-0" id="group-review-name"></h3></div>
                            </div>
                            <h4 class="h6 mt-4">Members <span class="text-muted fw-normal" id="group-review-count"></span></h4>
                            <div id="group-review-members" class="create-chat-review-list"></div>
                            <div id="group-review-empty" class="create-chat-review-empty" hidden>
                                No members selected. You can add people after creating the group.
                            </div>
                            <div class="create-chat-actions">
                                <button type="button" class="btn btn-outline-secondary" id="group-review-back-btn">Back</button>
                                <button type="button" id="create-group-btn" class="btn btn-primary">Create group</button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
    <script type="application/json" id="create-chat-users">@json($chatPeople)</script>
</div>

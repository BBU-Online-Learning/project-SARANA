@extends('layouts.app')

@section('title', $room->name.' settings')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/create-chat.css') }}">
    <link rel="stylesheet" href="{{ asset('css/group-settings.css') }}">
@endsection

@section('content')
@php
    $initial = mb_strtoupper(mb_substr($room->name ?: 'G', 0, 1));
    $avatarUrl = $room->avatarUrl();
    $remainingCapacity = max(0, 31 - $room->members->count());
    $oldMemberIds = collect(old('members', []))->map(fn ($id) => (string) $id)->all();
    $availablePeople = $users->map(fn ($user) => [
        'id' => $user->id,
        'name' => $user->name,
        'initials' => $user->initials(),
        'avatar' => $user->profileUrl(),
        'role' => $user->role?->name,
        'last_seen_at' => $user->last_seen_at?->toIso8601String(),
    ])->values();
@endphp

<div class="page-container workspace-page group-settings-page" data-group-settings data-max-selectable="{{ $remainingCapacity }}">
    <a href="{{ route('chat.index') }}" class="workspace-class-back">
        <i class="ti ti-arrow-left" aria-hidden="true"></i> Back to chats
    </a>

    <section class="card group-settings-hero mb-3">
        <div class="card-body">
            <div class="group-settings-identity">
                <span class="group-settings-avatar" aria-hidden="true">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="">
                    @else
                        {{ $initial }}
                    @endif
                </span>
                <div class="group-settings-heading">
                    <p class="workspace-eyebrow mb-1">Group chat</p>
                    <h1 class="h3 mb-1">{{ $room->name }}</h1>
                    <p class="text-muted mb-0">{{ $room->members->count() }} {{ Str::plural('member', $room->members->count()) }}</p>
                </div>
                <a href="{{ route('chat.index') }}" class="btn btn-primary group-settings-open-chat">
                    <i class="ti ti-message-circle" aria-hidden="true"></i> Open chat
                </a>
            </div>
            @if ($room->description)
                <p class="group-settings-description mb-0">{{ $room->description }}</p>
            @endif
        </div>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert" tabindex="-1" data-validation-summary>
            <strong>Some changes were not saved.</strong>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @unless ($owner)
        <div class="alert alert-warning" role="alert">Group ownership needs review. No member will be automatically promoted.</div>
    @endunless

    <div class="row g-3">
        <div class="col-xl-5">
            <section class="card group-settings-card mb-3">
                <div class="card-body">
                    <div class="group-settings-section-heading">
                        <span class="group-settings-section-icon"><i class="ti ti-info-circle" aria-hidden="true"></i></span>
                        <div><h2 class="h5 mb-1">Group details</h2><p class="text-muted small mb-0">Information members see about this conversation.</p></div>
                    </div>

                    @can('manageGroup', $room)
                        <form method="POST" action="{{ route('chat.groups.update', $room) }}" enctype="multipart/form-data" data-pending-form>
                            @csrf
                            @method('PATCH')
                            <div class="group-settings-photo-field mb-3">
                                <span class="group-settings-avatar group-settings-avatar-preview" data-group-avatar-preview aria-hidden="true">
                                    @if ($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="">
                                    @else
                                        {{ $initial }}
                                    @endif
                                </span>
                                <div class="group-settings-photo-copy">
                                    <label for="group-avatar" class="form-label">Group photo <span class="text-muted fw-normal">(optional)</span></label>
                                    <input id="group-avatar" type="file" name="avatar"
                                        class="form-control @error('avatar') is-invalid @enderror"
                                        accept="image/jpeg,image/png,image/webp" data-group-avatar-input>
                                    <div class="form-text">JPG, PNG or WebP, up to 2 MB and 4096 × 4096 pixels.</div>
                                    @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="group-name" class="form-label">Group name</label>
                                <input id="group-name" name="name" required minlength="3" maxlength="100"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ is_string(old('name')) ? old('name') : $room->name }}">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="group-description" class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea id="group-description" name="description" rows="4" maxlength="1000"
                                    class="form-control @error('description') is-invalid @enderror"
                                    placeholder="What is this group for?">{{ is_string(old('description')) ? old('description') : $room->description }}</textarea>
                                <div class="form-text">Up to 1000 characters.</div>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-primary" data-pending-label="Saving...">Save details</button>
                            <span role="status" aria-live="polite" data-form-status></span>
                        </form>
                    @else
                        <dl class="mb-0">
                            <dt>Name</dt><dd>{{ $room->name }}</dd>
                            <dt>Description</dt><dd class="group-settings-description">{{ $room->description ?: 'No description provided.' }}</dd>
                        </dl>
                    @endcan
                </div>
            </section>

            @can('manageGroup', $room)
                <section class="card group-settings-card mb-3">
                    <div class="card-body">
                        <div class="group-settings-section-heading">
                            <span class="group-settings-section-icon"><i class="ti ti-user-plus" aria-hidden="true"></i></span>
                            <div><h2 class="h5 mb-1">Add people</h2><p class="text-muted small mb-0">Search and select active users to join the group.</p></div>
                        </div>

                        @if ($remainingCapacity === 0)
                            <div class="group-settings-empty">This group has reached its 31-member limit.</div>
                        @elseif ($users->isEmpty())
                            <div class="group-settings-empty">No other active users are currently available.</div>
                        @else
                            <form method="POST" action="{{ route('chat.groups.members.store', $room) }}" data-add-group-members>
                                @csrf
                                <div class="create-chat-label-row">
                                    <span class="form-label mb-0">Selected people</span>
                                    <span class="create-chat-selection-count" data-add-people-count>0 selected</span>
                                </div>
                                <div class="create-chat-chips" data-add-people-chips aria-label="Selected people"></div>
                                <label class="visually-hidden" for="add-people-search">Search people</label>
                                <div class="create-chat-search">
                                    <i class="ti ti-search" aria-hidden="true"></i>
                                    <input type="search" id="add-people-search" placeholder="Search people..." autocomplete="off" data-add-people-search>
                                    <button type="button" data-clear-add-people aria-label="Clear people search" hidden>×</button>
                                </div>
                                <div class="create-chat-role-filters" role="group" aria-label="Filter people by role">
                                    @foreach (['all' => 'All', 'student' => 'Students', 'teacher' => 'Teachers', 'admin' => 'Admins'] as $value => $label)
                                        <button type="button" class="{{ $value === 'all' ? 'active' : '' }}" data-add-role-filter="{{ $value }}"
                                            aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}">{{ $label }}</button>
                                    @endforeach
                                </div>
                                <div class="create-chat-results group-settings-people-results" data-add-people-results aria-live="polite"></div>
                                <select name="members[]" multiple required class="visually-hidden" tabindex="-1" aria-hidden="true" data-add-people-select>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(in_array((string) $user->id, $oldMemberIds, true))>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary w-100 mt-3" data-add-people-submit disabled>
                                    <i class="ti ti-user-plus" aria-hidden="true"></i> Add selected members
                                </button>
                            </form>
                        @endif
                    </div>
                </section>
            @endcan
        </div>

        <div class="col-xl-7">
            <section class="card group-settings-card">
                <div class="card-body">
                    <div class="group-settings-members-heading">
                        <div class="group-settings-section-heading mb-0">
                            <span class="group-settings-section-icon"><i class="ti ti-users" aria-hidden="true"></i></span>
                            <div><h2 class="h5 mb-1">Members</h2><p class="text-muted small mb-0">{{ $room->members->count() }} people in this group</p></div>
                        </div>
                        @if ($room->members->count() > 5)
                            <label class="group-settings-member-search">
                                <span class="visually-hidden">Search current members</span>
                                <i class="ti ti-search" aria-hidden="true"></i>
                                <input type="search" placeholder="Search members" autocomplete="off" data-member-search>
                            </label>
                        @endif
                    </div>

                    <div class="group-settings-members" data-member-list>
                        @foreach ($room->members->sortBy(fn ($member) => $member->pivot->role === 'owner' ? 0 : 1) as $member)
                            <article class="group-settings-member" data-member-card
                                data-search-text="{{ Str::lower($member->name.' '.($member->role?->name ?? '')) }}">
                                <a href="{{ auth()->user()->is($member) ? route('profile.edit') : route('users.profile', $member) }}"
                                    class="group-settings-member-avatar" aria-label="View {{ $member->name }}'s profile">
                                    <x-user-avatar :user="$member" :size="44" />
                                </a>
                                <div class="group-settings-member-copy">
                                    <div class="group-settings-member-name">
                                        <a href="{{ auth()->user()->is($member) ? route('profile.edit') : route('users.profile', $member) }}">{{ $member->name }}</a>
                                        @if ($member->pivot->role === 'owner')<span class="badge bg-primary">Owner</span>@endif
                                        @if (auth()->user()->is($member))<span class="badge bg-light text-dark">You</span>@endif
                                    </div>
                                    <span>{{ ucwords(str_replace('_', ' ', $member->role?->name ?? 'Member')) }}</span>
                                </div>
                                @can('manageGroup', $room)
                                    @if ($member->pivot->role !== 'owner')
                                        <form method="POST" action="{{ route('chat.groups.members.destroy', [$room, $member]) }}"
                                            data-confirm-title="Remove member?"
                                            data-confirm-message="Remove {{ $member->name }} from this group?"
                                            data-confirm-label="Remove member" data-confirm-tone="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" aria-label="Remove {{ $member->name }} from group">Remove</button>
                                        </form>
                                    @endif
                                @endcan
                            </article>
                        @endforeach
                    </div>
                    <div class="group-settings-empty" data-member-empty hidden>No members match your search.</div>

                    <div class="group-settings-membership-note">
                        @if ($room->roomMembers()->where('user_id', auth()->id())->value('role') !== 'owner')
                            <form method="POST" action="{{ route('chat.groups.leave', $room) }}"
                                data-confirm-title="Leave group?"
                                data-confirm-message="You will lose access to this conversation after leaving."
                                data-confirm-label="Leave group" data-confirm-tone="danger">
                                @csrf
                                <button class="btn btn-outline-danger">Leave group</button>
                            </form>
                        @else
                            <i class="ti ti-lock" aria-hidden="true"></i>
                            <span>The group owner must remain in the group.</span>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script type="application/json" data-available-people>@json($availablePeople)</script>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('js/chat/group-settings.js') }}"></script>
@endsection

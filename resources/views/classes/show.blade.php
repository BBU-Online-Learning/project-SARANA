@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('content')
    <div class="page-container">
        @php
            $currentRole = strtolower($schoolClass->members->firstWhere('id', auth()->id())?->pivot->role ?? '');
        @endphp

        <div class="card class-hero mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge class-hero-badge">Class Space</span>

                            @can('manageMembers', $schoolClass)
                            <span class="badge bg-dark">
                                {{ $schoolClass->join_code }}
                            </span>

                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-copy-class-code="{{ $schoolClass->join_code }}">
                                Copy Code
                            </button>
                            @endcan
                        </div>

                        <h4 class="class-title mb-2">{{ $schoolClass->name }}</h4>
                        @if ($schoolClass->avatar && str_starts_with($schoolClass->avatar, 'class-avatars/'))
                            <img src="{{ route('classes.avatar', $schoolClass) }}" alt="Class image"
                                width="80" height="80" class="rounded mb-2">
                        @endif

                        <p class="class-description mb-0">
                            {{ $schoolClass->description ?: 'No description yet.' }}
                        </p>
                    </div>

                    <div class="d-flex flex-column align-items-stretch align-items-lg-end gap-2">
                        <div class="class-meta-pill">
                            <span>Created by</span>
                            <strong>{{ $schoolClass->creator?->name ?? 'Unknown' }}</strong>
                        </div>

                        @if ($currentRole && $currentRole !== 'owner')
                            <form method="POST" action="{{ route('classes.leave', $schoolClass) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">
                                    Leave Class
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="row g-3 mt-4">
                    <div class="col-md-4">
                        <div class="class-stat-card">
                            <span class="class-stat-label">Members</span>
                            <strong>{{ $schoolClass->members->count() }}</strong>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="class-stat-card">
                            <span class="class-stat-label">Channels</span>
                            <strong>{{ $schoolClass->channels->count() }}</strong>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="class-stat-card">
                            <span class="class-stat-label">Your Role</span>
                            <strong>{{ $currentRole ?: 'Metadata access only' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Class Info</h5>

                        @can('manageMembers', $schoolClass)
                        <div class="mb-2">
                            <strong>Join Code:</strong>
                            <span class="badge bg-dark">{{ $schoolClass->join_code }}</span>
                        </div>
                        @endcan

                        <div class="mb-2">
                            <strong>Created By:</strong>
                            <span>{{ $schoolClass->creator?->name ?? 'Unknown' }}</span>
                        </div>

                        <div class="mb-2">
                            <strong>Members:</strong>
                            <span>{{ $schoolClass->members->count() }}</span>
                        </div>
                    </div>
                </div>

                @can('enroll', $schoolClass)
                    <div class="card mt-3"><div class="card-body">
                        <p>You are not enrolled. Messages remain private until you explicitly enroll. Enrollment is audited.</p>
                        <form method="POST" action="{{ route('classes.enroll', $schoolClass) }}">
                            @csrf
                            <button class="btn btn-outline-primary" type="submit">Enroll for message access</button>
                        </form>
                    </div></div>
                @endcan

                @can('update', $schoolClass)
                    <div class="card mt-3"><div class="card-body">
                        <h5>Edit Class Information</h5>
                        <form method="POST" action="{{ route('classes.update', $schoolClass) }}">
                            @csrf
                            @method('PATCH')
                            <label class="form-label">Name</label>
                            <input name="name" class="form-control mb-2" value="{{ old('name', $schoolClass->name) }}" required maxlength="100">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control mb-2" maxlength="1000">{{ old('description', $schoolClass->description) }}</textarea>
                            @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                            @error('description') <div class="text-danger">{{ $message }}</div> @enderror
                            <button type="submit" class="btn btn-primary">Save Information</button>
                        </form>
                    </div></div>
                @endcan

                @can('transferOwnership', $schoolClass)
                    <div class="card mt-3"><div class="card-body">
                        <h5>Transfer Ownership</h5>
                        <form method="POST" action="{{ route('classes.owner', $schoolClass) }}">
                            @csrf
                            <label class="form-label">New Teacher Owner</label>
                            <select name="owner_id" class="form-select mb-2" required>
                                <option value="">Select a different Teacher</option>
                                @foreach ($eligibleTeachers as $teacher)
                                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                            <label class="d-block mb-2"><input type="checkbox" name="confirm_transfer" value="1" required>
                                I confirm the previous owner will remain enrolled as a student member.
                            </label>
                            @error('owner_id') <div class="text-danger">{{ $message }}</div> @enderror
                            @error('confirm_transfer') <div class="text-danger">{{ $message }}</div> @enderror
                            <button type="submit" class="btn btn-outline-danger">Transfer Ownership</button>
                        </form>
                    </div></div>
                @endcan

                @can('manageMembers', $schoolClass)
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="mb-3">Add Member</h5>

                            <form method="POST" action="{{ route('classes.members.store', $schoolClass) }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">User</label>
                                    <select name="user_id" class="form-select" required>
                                        <option value="">Select user</option>
                                        @foreach ($availableUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role?->name }})</option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select">
                                        <option value="student">Student</option>
                                        @can('manageTeachers', $schoolClass)
                                            <option value="teacher">Co-teacher (application Teachers only)</option>
                                        @endcan
                                    </select>
                                    @error('role')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Add Member</button>
                            </form>
                        </div>
                    </div>
                @endcan
                @can('manageChannels', $schoolClass)
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="mb-3">Add Channel</h5>

                            <form method="POST" action="{{ route('classes.channels.store', $schoolClass) }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Channel Name</label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                        placeholder="Example: Project">
                                    @error('name')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" rows="3" class="form-control" placeholder="Optional channel description">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    Create Channel
                                </button>
                            </form>
                        </div>
                    </div>
                @endcan

                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0">Channels</h5>
                            <span class="badge bg-light text-dark">
                                {{ $schoolClass->channels->count() }}
                            </span>
                        </div>

                        <input type="search" class="form-control form-control-sm mb-3" id="class-channel-search"
                            placeholder="Search channels..." autocomplete="off">

                        {{-- List channel --}}
                        <div class="list-group" id="class-channel-list">
                            @forelse ($schoolClass->channels as $channel)
                                <div class="list-group-item d-flex justify-content-between align-items-center class-channel-item"
                                    data-search-text="{{ strtolower($channel->name . ' ' . ($channel->description ?? '')) }}">
                                    @can('viewChannel', [$schoolClass, $channel])
                                    <a href="{{ route('classes.channels.show', [$schoolClass, $channel]) }}"
                                        class="text-decoration-none flex-grow-1 d-flex justify-content-between align-items-center me-3">
                                        <span>{{ $channel->name }}</span>

                                        @if ($channel->is_default)
                                            <span class="badge bg-info-subtle text-info">Default</span>
                                        @endif
                                    </a>
                                    @else
                                        <span>{{ $channel->name }} (enrollment required)</span>
                                    @endcan

                                    @can('manageChannels', $schoolClass)
                                        @if (!$channel->is_default)
                                            <form method="POST"
                                                action="{{ route('classes.channels.destroy', [$schoolClass, $channel]) }}"
                                                class="ms-2" onsubmit="return confirm('Delete this channel?');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            @empty
                                <div class="alert alert-light mb-0">
                                    No channels have been created yet.
                                </div>
                            @endforelse
                        </div>

                        <div id="class-channel-no-results" class="alert alert-warning mt-3 mb-0 d-none">
                            No channels match your search.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div
                            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Class Members</h5>
                                <small class="text-muted">
                                    {{ $schoolClass->members->count() }} member(s)
                                </small>
                            </div>

                            <input type="search" class="form-control form-control-sm class-member-search"
                                id="class-member-search" placeholder="Search members..." autocomplete="off">
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        @can('manageMembers', $schoolClass)
                                            <th class="text-end">Action</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($schoolClass->members as $member)
                                        <tr class="class-member-row"
                                            data-search-text="{{ strtolower($member->name . ' ' . $member->pivot->role) }}">
                                            <td>{{ $member->name }}</td>
                                            <td>{{ $member->pivot->role }}</td>

                                            @can('manageMembers', $schoolClass)
                                                <td class="text-end">
                                                    @can('removeMember', [$schoolClass, $member])
                                                        <form method="POST"
                                                            action="{{ route('classes.members.destroy', [$schoolClass, $member]) }}"
                                                            class="d-inline">
                                                            @csrf
                                                            @method('DELETE')

                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                Remove
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted small">Protected</span>
                                                    @endcan
                                                </td>
                                            @endcan
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div id="class-member-no-results" class="alert alert-warning mb-3 d-none">
                            No members match your search.
                        </div>

                        @if ($schoolClass->members->isEmpty())
                            <div class="alert alert-light mb-3">
                                No members have joined this class yet.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const copyButton = document.querySelector('[data-copy-class-code]');
            const channelSearch = document.querySelector('#class-channel-search');
            const memberSearch = document.querySelector('#class-member-search');
            const channelNoResults = document.querySelector('#class-channel-no-results');
            const memberNoResults = document.querySelector('#class-member-no-results');

            if (copyButton) {
                copyButton.addEventListener('click', async () => {
                    const joinCode = copyButton.dataset.copyClassCode;
                    const originalText = copyButton.textContent;

                    try {
                        await navigator.clipboard.writeText(joinCode);
                        copyButton.textContent = 'Copied';
                        copyButton.classList.remove('btn-outline-secondary');
                        copyButton.classList.add('btn-success');
                    } catch (error) {
                        copyButton.textContent = 'Copy Failed';
                        copyButton.classList.remove('btn-outline-secondary');
                        copyButton.classList.add('btn-danger');
                    }

                    setTimeout(() => {
                        copyButton.textContent = originalText;
                        copyButton.classList.remove('btn-success', 'btn-danger');
                        copyButton.classList.add('btn-outline-secondary');
                    }, 1500);
                });
            }

            if (channelSearch) {
                channelSearch.addEventListener('input', () => {
                    const searchValue = channelSearch.value.trim().toLowerCase();
                    const channelItems = document.querySelectorAll('.class-channel-item');
                    let visibleChannelCount = 0;

                    channelItems.forEach((channelItem) => {
                        const channelText = channelItem.dataset.searchText || '';
                        const isVisible = channelText.includes(searchValue);

                        channelItem.classList.toggle('d-none', !isVisible);

                        if (isVisible) {
                            visibleChannelCount++;
                        }
                    });

                    if (channelNoResults) {
                        channelNoResults.classList.toggle(
                            'd-none',
                            visibleChannelCount > 0 || channelItems.length === 0
                        );
                    }
                });
            }

            if (memberSearch) {
                memberSearch.addEventListener('input', () => {
                    const searchValue = memberSearch.value.trim().toLowerCase();
                    const memberRows = document.querySelectorAll('.class-member-row');
                    let visibleMemberCount = 0;

                    memberRows.forEach((memberRow) => {
                        const memberText = memberRow.dataset.searchText || '';
                        const isVisible = memberText.includes(searchValue);

                        memberRow.classList.toggle('d-none', !isVisible);

                        if (isVisible) {
                            visibleMemberCount++;
                        }
                    });

                    if (memberNoResults) {
                        memberNoResults.classList.toggle(
                            'd-none',
                            visibleMemberCount > 0 || memberRows.length === 0
                        );
                    }
                });
            }
        });
    </script>
@endsection

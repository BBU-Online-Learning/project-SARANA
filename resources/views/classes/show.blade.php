@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', $schoolClass->name)
@section('content')
    <div class="page-container">
        @php
            $currentRole = strtolower($schoolClass->members->firstWhere('id', auth()->id())?->pivot->role ?? '');
        @endphp
        @if ($schoolClass->isArchived())
            <div class="alert alert-warning">Archived class: history remains readable. Restore the class before editing, posting, joining, or changing membership.</div>
        @endif
        @error('class') <div class="alert alert-danger">{{ $message }}</div> @enderror

        <a class="workspace-class-back" href="{{ route('classes.index') }}"><i class="ti ti-arrow-left" aria-hidden="true"></i> Back to classes</a>
        <div class="card class-hero mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge class-hero-badge">{{ auth()->user()->can('manageMembers', $schoolClass) ? 'Class management' : 'Classroom' }}</span>

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

                        <h1 class="class-title h3 mb-2">{{ $schoolClass->name }}</h1>
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

                        @if ($currentRole && $currentRole !== 'owner' && ! $schoolClass->isArchived())
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

        @if(in_array(auth()->user()->role->name, ['teacher', 'student'], true))
            <nav class="learning-class-nav" aria-label="Class sections">
                <a href="#class-channels">Channels</a>
                <a href="#class-members">Members</a>
                @canany(['manageLifecycle', 'manageMembers', 'manageChannels'], $schoolClass)
                    <a href="#class-actions">Class actions</a>
                @endcanany
                <a href="{{ route('chat.index') }}">Chats</a>
            </nav>
            <section id="class-channels" class="learning-class-section" aria-label="Class channels">
                <p class="learning-muted">Read announcements from your teaching team or open a channel to take part in the conversation.</p>
                @include('classes.partials.class-channels')
            </section>
            <details id="class-members" class="learning-disclosure learning-class-section" @if($errors->hasAny(['user_id', 'role'])) open @endif>
                <summary>Class members <span>{{ $schoolClass->members->count() }} members</span></summary>
                @include('classes.partials.class-members')
            </details>
            @canany(['manageLifecycle', 'manageMembers', 'manageChannels'], $schoolClass)
                <details id="class-actions" class="learning-disclosure learning-class-section" @if($errors->any()) open @endif>
                    <summary>Class actions <span>Manage this class</span></summary>
                    <div class="learning-action-grid">@include('classes.partials.class-actions')</div>
                </details>
            @endcanany
        @else
            <div class="row g-3">
                <div class="col-lg-4">
                    @include('classes.partials.class-info')
                    <div id="class-actions">@include('classes.partials.class-actions')</div>
                    @include('classes.partials.class-channels')
                </div>
                <div class="col-lg-8">@include('classes.partials.class-members')</div>
            </div>
        @endif
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

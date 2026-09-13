@extends('layouts.app')
@section('bodyClass', 'class-page')
@section('title', $schoolClass->name.' · '.$channel->name)

@section('content')
<div id="school-class-channel-page"
     data-membership-id="{{ $membership->id }}"
     data-history-page="{{ $historyPage ? '1' : '0' }}"
     data-page-url="{{ route('classes.channels.show', [$schoolClass, $channel]) }}"
     data-messages-url="{{ route('classes.channels.messages.index', [$schoolClass, $channel]) }}"
     class="page-container"
     data-class-id="{{ $schoolClass->id }}"
     data-channel-id="{{ $channel->id }}">
    <div class="card class-hero mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <a href="{{ route('classes.show', $schoolClass) }}" class="btn btn-sm btn-outline-secondary">
                            Back to Class
                        </a>
                        <span class="badge class-hero-badge">Channel</span>
                        <span class="badge bg-dark">{{ $schoolClass->name }}</span>
                    </div>

                    <h1 class="class-title h3 mb-2">{{ $channel->name }}</h1>
                    @if ($schoolClass->isArchived())
                        <div class="alert alert-warning">Archived class: messages are read-only until restored.</div>
                    @elseif ($channel->isAnnouncement())
                        <p class="text-muted">Announcements: only the class owner and co-teachers can post.</p>
                    @endif

                    <p class="class-description mb-0">
                        {{ $channel->description ?: 'Class discussion channel' }}
                    </p>
                </div>

                <div class="d-flex flex-column align-items-stretch align-items-lg-end gap-2">
                    <div class="class-meta-pill">
                        <span>Channel Type</span>
                        <strong>{{ $channel->is_default ? 'Default' : 'Custom' }}</strong>
                    </div>

                    <div class="class-meta-pill">
                        <span>Messages on this page</span>
                        <strong>{{ $messages->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <a id="class-channel-older" class="btn btn-sm btn-outline-secondary" @unless($hasOlder) hidden @endunless
                           href="{{ route('classes.channels.show', [$schoolClass, $channel, 'before_id' => $messages->first()?->id]) }}">Older messages</a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('classes.channels.show', [$schoolClass, $channel]) }}">Latest messages</a>
                    </div>
                    <p id="class-channel-sync-status" class="text-muted small" role="status" aria-live="polite"></p>
                    <h6 class="mb-3">Channels</h6>

                    <div class="list-group">
                        @foreach ($schoolClass->channels as $classChannel)
                            <a href="{{ route('classes.channels.show', [$schoolClass, $classChannel]) }}"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $classChannel->id === $channel->id ? 'active' : '' }}">
                                <span>{{ $classChannel->name }}</span>

                                @if ($classChannel->is_default)
                                    <span class="badge bg-info-subtle text-info">Default</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <div
                        id="class-channel-message-list"
                        class="border rounded-3 p-3 mb-4 bg-white"
                        style="min-height: 60vh; max-height: 60vh; overflow-y: auto;"
                    >
                        @forelse ($messages as $message)
                            <div class="border-bottom py-3" data-class-message-id="{{ $message->id }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>{{ ! $message->sender || $message->sender->trashed() ? 'Deleted user' : $message->sender->name }}</strong>
                                    <span class="text-muted small">
                                        {{ $message->created_at->format('d M Y, h:i A') }}
                                        @if($message->is_edited) (edited) @endif
                                    </span>
                                </div>

                                <div class="mt-2">
                                    {{ $message->body }}
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-light mb-0">
                                @can('sendMessage', [$schoolClass, $channel])
                                    No messages yet. Be the first to start the discussion.
                                @else
                                    No messages yet. Updates will appear here when your teaching team posts.
                                @endcan
                            </div>
                        @endforelse
                    </div>

                    <p id="class-channel-read-only" class="alert alert-light" @can('sendMessage', [$schoolClass, $channel]) hidden @endcan>
                        This channel is read-only for you. Refresh the page after the class is restored or your permissions change.
                    </p>
                    @can('sendMessage', [$schoolClass, $channel])
                    <form id="class-channel-message-form" method="POST" action="{{ route('classes.channels.messages.store', [$schoolClass, $channel]) }}">
                        @csrf
                        <input type="hidden" name="client_uuid" value="{{ is_string(old('client_uuid')) ? old('client_uuid') : (string) \Illuminate\Support\Str::uuid() }}">

                        <div class="mb-3">
                            <label class="form-label">Send Message</label>
                            <textarea name="body" rows="4" maxlength="5000" required class="form-control" placeholder="Write a message...">{{ is_string(old('body')) ? old('body') : '' }}</textarea>
                            <div id="class-channel-send-error" class="text-danger small mt-1" role="alert"></div>

                            @error('body')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            @error('client_uuid')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Send
                        </button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script id="class-channel-initial" type="application/json">@json($initialMessages)</script>
    <script src="{{ asset('js/classes/channel-realtime.js') }}"></script>
@endsection

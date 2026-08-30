@extends('layouts.app')
@section('bodyClass', 'class-page')

@section('content')
<div id="school-class-channel-page"
     data-membership-id="{{ $membership->id }}"
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

                    <h4 class="class-title mb-2">{{ $channel->name }}</h4>

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
                        <span>Messages</span>
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
                                    <strong>{{ $message->sender->name }}</strong>
                                    <span class="text-muted small">
                                        {{ $message->created_at->format('d M Y, h:i A') }}
                                    </span>
                                </div>

                                <div class="mt-2">
                                    {{ $message->body }}
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-light mb-0">
                                No messages yet. Be the first to start the discussion.
                            </div>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('classes.channels.messages.store', [$schoolClass, $channel]) }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Send Message</label>
                            <textarea name="body" rows="4" class="form-control" placeholder="Write a message...">{{ old('body') }}</textarea>

                            @error('body')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Send
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('js/classes/channel-realtime.js') }}"></script>
@endsection

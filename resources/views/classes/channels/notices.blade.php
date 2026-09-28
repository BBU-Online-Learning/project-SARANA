<section class="card mb-3" aria-labelledby="class-notices-heading">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 id="class-notices-heading" class="h5 mb-1">Class notices</h2>
                <p class="small text-muted mb-0">Published notices from the teaching team.</p>
            </div>
        </div>
        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

        @if ($canManageNotices)
            <details class="border rounded p-3 mb-3">
                <summary class="fw-semibold">Create a notice draft</summary>
                <form method="POST" action="{{ route('classes.channels.notices.store', [$schoolClass, $channel]) }}" class="mt-3">
                    @csrf
                    <label class="form-label" for="new-notice-title">Title</label>
                    <input id="new-notice-title" class="form-control mb-2" name="title" maxlength="180" value="{{ old('title') }}" required>
                    <label class="form-label" for="new-notice-body">Notice</label>
                    <textarea id="new-notice-body" class="form-control mb-2" name="body" rows="4" maxlength="10000" required>{{ old('body') }}</textarea>
                    <label class="form-label" for="new-notice-expires">Expires at (optional, {{ config('app.timezone') }})</label>
                    <input id="new-notice-expires" class="form-control mb-2" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}">
                    <button class="btn btn-primary" type="submit">Save draft</button>
                </form>
            </details>
        @endif

        @forelse ($notices as $notice)
            <article class="class-list-card p-3 mb-3" id="notice-{{ $notice->id }}">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                            @if ($notice->pinned_at && $notice->isVisible()) <x-class-status value="pinned" /> @endif
                            @if ($canManageNotices || $notice->status !== 'published')
                                <x-class-status :value="$notice->publicationPauseReason() ? 'paused' : ($notice->status === 'published' && $notice->expires_at?->isPast() ? 'expired' : $notice->status)" />
                            @endif
                        </div>
                        <h3 class="h6 mb-1">{{ $notice->title }}</h3>
                        <small class="text-muted">{{ $notice->author?->name ?? 'Former teacher' }}
                            @if ($notice->scheduled_by && $notice->scheduled_by !== $notice->author_id) · Scheduled by {{ $notice->scheduledBy?->name ?? 'Former teacher' }} @endif
                            @if ($notice->published_at) · Published {{ $notice->published_at->format('d M Y, g:i A') }} @endif
                            @if ($notice->publish_at && $notice->status === 'scheduled') · Scheduled {{ $notice->publish_at->format('d M Y, g:i A') }} @endif
                            @if ($notice->publish_at && $notice->publicationPauseReason()) · Originally due {{ $notice->publish_at->format('d M Y, g:i A') }} @endif
                            @if ($notice->expires_at) · Expires {{ $notice->expires_at->format('d M Y, g:i A') }} @endif
                        </small>
                    </div>
                </div>
                @if ($notice->publicationPauseReason())
                    <p class="alert alert-warning mt-3 mb-0">Publication paused. {{ $notice->publicationPauseReason() }}</p>
                @endif
                <div class="mt-2 text-break" style="white-space: pre-wrap">{{ $notice->body }}</div>

                @canany(['update', 'pin', 'archive', 'restore'], $notice)
                <details class="class-notice-manage">
                    <summary>Manage notice</summary>
                @can('update', $notice)
                    <details class="mt-3">
                        <summary>Edit draft and timing</summary>
                        <form method="POST" action="{{ route('classes.channels.notices.update', [$schoolClass, $channel, $notice]) }}" class="mt-2">
                            @csrf @method('PATCH')
                            <label class="form-label" for="notice-title-{{ $notice->id }}">Title</label><input id="notice-title-{{ $notice->id }}" class="form-control mb-2" name="title" maxlength="180" value="{{ $notice->title }}" required>
                            <label class="form-label" for="notice-body-{{ $notice->id }}">Notice</label><textarea id="notice-body-{{ $notice->id }}" class="form-control mb-2" name="body" rows="4" maxlength="10000" required>{{ $notice->body }}</textarea>
                            <label class="form-label" for="notice-expires-{{ $notice->id }}">Expires at ({{ config('app.timezone') }})</label><input id="notice-expires-{{ $notice->id }}" class="form-control mb-2" type="datetime-local" name="expires_at" value="{{ $notice->expires_at?->format('Y-m-d\TH:i') }}">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Save changes</button>
                        </form>
                    </details>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <form method="POST" action="{{ route('classes.channels.notices.publish', [$schoolClass, $channel, $notice]) }}">
                            @csrf <button class="btn btn-sm btn-primary" type="submit">Publish now</button>
                        </form>
                        <form method="POST" action="{{ route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]) }}" class="d-flex gap-2 align-items-end">
                            @csrf
                            <label class="small">Publish at ({{ config('app.timezone') }})
                                <input class="form-control form-control-sm" type="datetime-local" name="publish_at" value="{{ $notice->publish_at?->format('Y-m-d\TH:i') }}" required>
                            </label>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Schedule</button>
                        </form>
                        @can('returnToDraft', $notice)
                            <form method="POST" action="{{ route('classes.channels.notices.draft', [$schoolClass, $channel, $notice]) }}">
                                @csrf <button class="btn btn-sm btn-outline-secondary" type="submit">Return to draft</button>
                            </form>
                        @endcan
                    </div>
                @endcan

                <div class="d-flex flex-wrap gap-2 mt-3">
                    @can('pin', $notice)
                        <form method="POST" action="{{ route($notice->pinned_at ? 'classes.channels.notices.unpin' : 'classes.channels.notices.pin', [$schoolClass, $channel, $notice]) }}">
                            @csrf <button class="btn btn-sm btn-outline-secondary" type="submit">{{ $notice->pinned_at ? 'Unpin' : 'Pin' }}</button>
                        </form>
                    @endcan
                    @can('archive', $notice)
                        <form method="POST" action="{{ route('classes.channels.notices.archive', [$schoolClass, $channel, $notice]) }}">
                            @csrf <button class="btn btn-sm btn-outline-danger" type="submit">Archive</button>
                        </form>
                    @endcan
                    @can('restore', $notice)
                        <form method="POST" action="{{ route('classes.channels.notices.restore', [$schoolClass, $channel, $notice]) }}">
                            @csrf <button class="btn btn-sm btn-outline-primary" type="submit">Restore as draft</button>
                        </form>
                    @endcan
                </div>
                </details>
                @endcanany
            </article>
        @empty
            <x-class-empty title="No notices to show." description="Published class notices will appear here." icon="ti-speakerphone" />
        @endforelse
        {{ $notices->links() }}
    </div>
</section>

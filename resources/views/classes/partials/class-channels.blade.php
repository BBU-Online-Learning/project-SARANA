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
                                        @if(in_array(auth()->user()->role->name, ['teacher', 'student'], true))
                                            <span class="learning-channel-label"><i class="ti {{ $channel->isAnnouncement() ? 'ti-speakerphone' : 'ti-hash' }}" aria-hidden="true"></i><span>{{ $channel->name }}<small>{{ $channel->description ?: ($channel->isAnnouncement() ? 'Updates from the teaching team' : 'Class conversations and discussions') }}</small></span></span>
                                        @else
                                            <span>{{ $channel->name }}</span>
                                        @endif

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

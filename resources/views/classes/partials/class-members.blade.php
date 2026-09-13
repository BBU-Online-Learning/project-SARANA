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
                                            <td><span class="d-flex align-items-center gap-2"><x-user-avatar :user="$member" :size="32" />
                                                <a href="{{ auth()->user()->is($member) ? route('profile.edit') : route('users.profile', $member) }}">{{ $member->name }}</a></span></td>
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

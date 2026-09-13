                @can('manageLifecycle', $schoolClass)
                    <div class="card mt-3"><div class="card-body">
                        <h5>Class Lifecycle</h5>
                        <p class="text-muted">Archive instead of deleting. Messages and members are retained.</p>
                        <form method="POST" action="{{ route($schoolClass->isArchived() ? 'classes.unarchive' : 'classes.archive', $schoolClass) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning">
                                {{ $schoolClass->isArchived() ? 'Restore Class' : 'Archive Class' }}
                            </button>
                        </form>
                    </div></div>
                @endcan

                @can('regenerateCode', $schoolClass)
                    <div class="card mt-3"><div class="card-body">
                        <h5>Join Code</h5>
                        <p>Regenerating the code invalidates the previous code. Existing members stay enrolled.</p>
                        <form method="POST" action="{{ route('classes.regenerate-code', $schoolClass) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Regenerate Join Code</button>
                        </form>
                    </div></div>
                @endcan

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


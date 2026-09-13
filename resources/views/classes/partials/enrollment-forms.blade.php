            @can('manage-classes')
                <div class="card mb-3 workspace-anchor" id="create-class">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Create Class</h2>
                        <p class="text-muted small">{{ $classAdministration ? 'Choose a teacher owner for the new class.' : 'Start a class space for your students.' }}</p>

                        <form method="POST" action="{{ route('classes.store') }}" enctype="multipart/form-data">
                            @csrf

                            @if (app(\App\Services\ClassAccessService::class)->administrator(auth()->user()))
                                <div class="mb-3">
                                    <label class="form-label" for="enrollment-owner">Teacher Owner</label>
                                    <select id="enrollment-owner" name="owner_id" class="form-select" required>
                                        <option value="">Select an eligible Teacher</option>
                                        @foreach ($eligibleTeachers as $teacher)
                                            <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Creating a class does not enroll you for message access.</small>
                                    @error('owner_id') <div class="text-danger">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label" for="enrollment-name">Class Name</label>
                                <input type="text" id="enrollment-name" name="name" class="form-control" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="enrollment-description">Description</label>
                                <textarea id="enrollment-description" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="enrollment-avatar">Class Image</label>
                                <input type="file" id="enrollment-avatar" name="avatar" class="form-control">
                                @error('avatar')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Create Class</button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3 workspace-anchor" id="join-class">Join Class</h2>
                    <p class="text-muted small">Enter a class code to join as a student member.</p>

                    <form method="POST" action="{{ route('classes.join') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="enrollment-code">Join Code</label>
                            <input type="text" id="enrollment-code" name="join_code" class="form-control" value="{{ old('join_code') }}" required>
                            @error('join_code')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-success w-100">Join Class</button>
                    </form>
                </div>
            </div>

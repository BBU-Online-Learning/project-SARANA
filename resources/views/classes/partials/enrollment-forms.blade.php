            @can('manage-classes')
                <details class="card mb-3 workspace-anchor enrollment-disclosure" id="create-class" @if($errors->hasAny(['owner_id', 'name', 'description', 'avatar'])) open @endif>
                    <summary><span class="enrollment-summary-icon"><i class="ti ti-school" aria-hidden="true"></i></span><span><strong>Create Class</strong><small>Set up a new teaching space</small></span><i class="ti ti-chevron-down enrollment-chevron" aria-hidden="true"></i></summary>
                    <div class="card-body">
                        <div class="enrollment-form-heading"><span><i class="ti ti-sparkles" aria-hidden="true"></i></span><div><h2>Create your class</h2><p>{{ $classAdministration ? 'Choose a teacher owner for the new class.' : 'Add the details students will see in their learning space.' }}</p></div></div>

                        <form method="POST" action="{{ route('classes.store') }}" enctype="multipart/form-data" data-pending-form>
                            @csrf

                            @if (app(\App\Services\ClassAccessService::class)->administrator(auth()->user()))
                                <div class="mb-3">
                                    <label class="form-label" for="enrollment-owner">Teacher Owner</label>
                                    <select id="enrollment-owner" name="owner_id" class="form-select" required>
                                        <option value="">Select an eligible Teacher</option>
                                        @foreach ($eligibleTeachers as $teacher)
                                            <option value="{{ $teacher->id }}" @selected((string) old('owner_id') === (string) $teacher->id)>{{ $teacher->name }}</option>
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
                                <input type="file" id="enrollment-avatar" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp" aria-describedby="class-image-help">
                                <small id="class-image-help" class="text-muted">Optional. Choose a JPG, PNG, or WebP image up to 2 MB.</small>
                                @error('avatar')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100" data-pending-label="Creating…">Create Class</button>
                            <p class="small mb-0 mt-2" role="status" data-form-status></p>
                        </form>
                    </div>
                </details>
            @endcan

            <details class="card workspace-anchor enrollment-disclosure" id="join-class" @if($errors->has('join_code')) open @endif>
                <summary><span class="enrollment-summary-icon"><i class="ti ti-login" aria-hidden="true"></i></span><span><strong>Join Class</strong><small>{{ $classAdministration ? 'Use a code to access class content' : (auth()->user()->role->name === 'teacher' ? 'Join as a student member' : 'Use a code from your teacher') }}</small></span><i class="ti ti-chevron-down enrollment-chevron" aria-hidden="true"></i></summary>
                <div class="card-body">
                    <div class="enrollment-form-heading"><span><i class="ti ti-key" aria-hidden="true"></i></span><div><h2>Enter a class code</h2><p>{{ $classAdministration ? 'Enroll your account to access class messages and learning content.' : 'Join an existing space as a student member.' }}</p></div></div>

                    <form method="POST" action="{{ route('classes.join') }}" data-pending-form>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="enrollment-code">Join Code</label>
                            <input type="text" id="enrollment-code" name="join_code" class="form-control" value="{{ old('join_code') }}" required>
                            @error('join_code')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100" data-pending-label="Joining…">Join Class</button>
                        <p class="small mb-0 mt-2" role="status" data-form-status></p>
                    </form>
                </div>
            </details>

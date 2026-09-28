@extends('layouts.app')
@section('title', 'Edit user')
@section('content')
    <div class="page-container workspace-page account-form-page">
        <a href="{{ route('users.index') }}" class="btn btn-link px-0 mb-3"><i class="ti ti-arrow-left" aria-hidden="true"></i> Back to accounts</a>
        <section class="workspace-page-heading account-page-heading mb-4" aria-labelledby="account-form-title">
            <div class="workspace-heading-icon" aria-hidden="true"><i class="ti ti-user-edit"></i></div>
            <div class="workspace-heading-copy">
                <p class="workspace-eyebrow mb-1">School management</p>
                <h1 class="h3 mb-1" id="account-form-title">Edit user</h1>
                <p class="mb-0">Update {{ $user->name }}'s account details and institution role.</p>
            </div>
        </section>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">

                    <div class="card-body">
                        @if ($user->schoolClasses()->wherePivot('role', 'owner')->whereNull('school_classes.archived_at')->exists())
                            <div class="alert alert-warning">
                                This account owns active classes. Reassign ownership before suspending it or changing it to a non-Teacher role.
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif
                        <form action="{{ route('users.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">

                                {{-- Name (if you still want name, keep it; otherwise remove this block) --}}
                                <div class="col-md-6">
                                    <label for="name" class="form-label">User name</label>
                                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                                        name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Email --}}
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                        name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Role --}}

                                <div class="col-md-6">
                                    <label for="role_id" class="form-label">Role</label>
                                    <select id="role_id" name="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
                                        @foreach ($roles as $id => $name)
                                            <option value="{{ $id }}"
                                                {{ old('role_id', $user->role_id) == $id ? 'selected' : '' }}>
                                                {{ ucwords(str_replace('_', ' ', $name)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                {{-- phone --}}
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone number <span class="text-muted">(optional)</span></label>
                                    <input id="phone" type="tel" class="form-control @error('phone') is-invalid @enderror"
                                        name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>



                                {{-- Profile Image --}}
                                <div class="col-md-6">
                                    <label for="profile" class="form-label">Profile image <span class="text-muted">(optional)</span></label>
                                    <input id="profile" type="file" class="form-control" name="profile" accept="image/jpeg,image/png,image/gif"
                                        onchange="previewProfileImage(event)">

                                    {{-- Preview --}}
                                    <div class="mt-2">
                                        <img id="profilePreview"
                                            src="{{ $user->profileUrl() ?? asset('images/error.png') }}"
                                            alt="Profile image preview" class="img-thumbnail"
                                            style="width:120px; height:120px; object-fit:cover;">
                                    </div>
                                </div>




                                {{-- Status --}}
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status1" class="form-label">Status</label>
                                        <select name="status" id="status1" class="form-select" required>
                                            <option value="">Choose a status</option>
                                            @foreach (['active', 'inactive', 'suspended'] as $status)
                                                <option value="{{ $status }}" @selected(old('status', $user->status) === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                        @error('status')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12 text-end pt-3">
                                    <button type="submit" class="btn btn-primary">
                                        Save changes
                                    </button>
                                    <a href="{{ route('users.index') }}" class="btn btn-light">
                                        Cancel
                                    </a>
                                </div>

                            </div>
                        </form>
                        <div class="border rounded p-3 mt-3 bg-light">
                            <h5>Account Recovery</h5>
                            <p>Verify the account owner's identity through the institution first. The recovery link goes only to their registered email. Their authenticator remains unchanged until they redeem the link and set a new password.</p>
                            <p>2FA: {{ $user->google2fa_enabled ? 'Enabled' : 'Disabled' }}</p>
                            <form method="POST" action="{{ route('users.recovery', $user) }}">
                                @csrf
                                <label for="recovery-password" class="form-label">Your current password</label>
                                <input id="recovery-password" type="password" name="current_password" class="form-control mb-2" autocomplete="current-password" required>
                                <label for="recovery-code" class="form-label">Your fresh authenticator code</label>
                                <input id="recovery-code" name="code" class="form-control mb-2" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required>
                                <label class="form-check-label mb-2">
                                    <input type="checkbox" name="identity_confirmed" value="1" required>
                                    I verified this account owner's identity through the institution.
                                </label>
                                <p class="small text-muted">A code already used for login cannot be reused. Wait for a fresh code.</p>
                                <button class="btn btn-outline-primary" type="submit">Send verified recovery link</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
        function previewProfileImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('profilePreview');

            if (!file) return;

            if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                window.AppNotifications?.warning('Please select a JPG, PNG, or GIF image.');
                event.target.value = '';
                return;
            }

            preview.src = URL.createObjectURL(file);
        }
    </script>
@endsection

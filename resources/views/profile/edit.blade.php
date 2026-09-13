@extends('layouts.app')
@section('title', 'My profile')
@section('content')
<div class="page-container workspace-page">
    <section class="profile-hero card mb-3"><div class="card-body d-flex flex-column flex-sm-row align-items-sm-center gap-3">
        <x-user-avatar :user="$user" :size="96" />
        <div><p class="workspace-eyebrow mb-1">My profile</p><h1 class="h3 mb-1">{{ $user->name }}</h1>
            <p class="text-muted mb-0">{{ ucwords(str_replace('_', ' ', $user->role->name)) }}</p></div>
    </div></section>
    @if($errors->any())
        <div class="alert alert-danger" role="alert" tabindex="-1" data-validation-summary>
            <strong>Your profile was not saved.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="row g-3">
        <section class="col-lg-7"><div class="card"><div class="card-body">
            <h2 class="h5">Personal details</h2>
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-pending-form>
                @csrf @method('PATCH')
                <div class="mb-3">
                    <label for="profile-name" class="form-label">Name</label>
                    <input id="profile-name" name="name" class="form-control @error('name') is-invalid @enderror" required maxlength="255" autocomplete="name"
                        value="{{ is_string(old('name', $user->name)) ? old('name', $user->name) : '' }}" aria-describedby="name-error">
                    @error('name')<div id="name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="profile-phone" class="form-label">Phone (optional)</label>
                    <input id="profile-phone" type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" maxlength="30" autocomplete="tel"
                        value="{{ is_string(old('phone', $user->phone)) ? old('phone', $user->phone) : '' }}" aria-describedby="phone-error">
                    @error('phone')<div id="phone-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="profile-bio" class="form-label">About (optional)</label>
                    <textarea id="profile-bio" name="bio" class="form-control @error('bio') is-invalid @enderror" maxlength="1000" rows="5"
                        aria-describedby="bio-help bio-error">{{ is_string(old('bio', $user->bio)) ? old('bio', $user->bio) : '' }}</textarea>
                    <div id="bio-help" class="form-text">Visible to people who share a chat or class with you.</div>
                    @error('bio')<div id="bio-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <x-user-avatar :user="$user" :size="72" class="mb-2" />
                    <span class="visually-hidden">Your current profile photo</span>
                    <label for="profile-photo" class="form-label d-block">Profile photo (optional)</label>
                    <input id="profile-photo" type="file" name="photo" class="form-control @error('photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help photo-error">
                    <div id="photo-help" class="form-text">JPG, PNG or WebP, up to 2 MB and 4096 x 4096 pixels. Photos are visible to other users. Leave blank to keep your photo.</div>
                    @error('photo')<div id="photo-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary" data-pending-label="Saving...">Save profile</button>
                <span role="status" aria-live="polite" data-form-status></span>
            </form>
        </div></div></section>
        <section class="col-lg-5"><div class="card"><div class="card-body">
            <h2 class="h5">Account and security</h2>
            <dl><dt>Email</dt><dd class="text-break">{{ $user->email }}</dd><dt>Role</dt><dd>{{ ucwords(str_replace('_', ' ', $user->role->name)) }}</dd><dt>Status</dt><dd>{{ ucfirst($user->status) }}</dd><dt>Member since</dt><dd>{{ $user->created_at->format('F Y') }}</dd></dl>
            <p class="text-muted">Email, role and account status cannot be changed here. Contact institution administration for account corrections.</p>
            <p>Two-factor authentication is enabled.</p>
            <a href="{{ route('password.change') }}" class="btn btn-outline-primary">Change password</a>
            <p class="form-text">Password changes require your current password and an authenticator code.</p>
        </div></div></section>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'My profile')
@section('content')
<div class="page-container workspace-page">
    <section class="profile-hero profile-page-hero">
        <div class="profile-page-identity">
            <x-user-avatar :user="$user" :size="104" />
            <div><p class="workspace-eyebrow">My profile</p><h1>{{ $user->name }}</h1><p>Manage your personal information, profile photo, and account security.</p>
                <div class="profile-hero-meta"><span><i class="ti ti-id-badge" aria-hidden="true"></i>{{ ucwords(str_replace('_', ' ', $user->role->name)) }}</span><span><i class="ti ti-circle-check" aria-hidden="true"></i>{{ ucfirst($user->status) }}</span><span><i class="ti ti-calendar" aria-hidden="true"></i>Member since {{ $user->created_at->format('M Y') }}</span></div>
            </div>
        </div>
        <div class="profile-hero-art" aria-hidden="true"><i class="ti ti-user-circle"></i></div>
    </section>
    @if($errors->any())
        <div class="alert alert-danger" role="alert" tabindex="-1" data-validation-summary>
            <strong>Your profile was not saved.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="profile-layout">
        <section class="card profile-details-card"><div class="card-body">
            <div class="profile-section-heading"><span><i class="ti ti-user-edit" aria-hidden="true"></i></span><div><h2>Personal details</h2><p>Keep your name, phone number, biography, and photo up to date.</p></div></div>
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
                <div class="mb-3 profile-photo-field">
                    <x-user-avatar :user="$user" :size="72" class="mb-2" />
                    <span class="visually-hidden">Your current profile photo</span><div><label for="profile-photo" class="form-label d-block">Profile photo (optional)</label>
                    <input id="profile-photo" type="file" name="photo" class="form-control @error('photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help photo-error">
                    <div id="photo-help" class="form-text">JPG, PNG or WebP, up to 2 MB. Leave blank to keep your current photo.</div>
                    @error('photo')<div id="photo-error" class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div>
                <div class="profile-save-actions"><button type="submit" class="btn btn-primary" data-pending-label="Saving..."><i class="ti ti-device-floppy" aria-hidden="true"></i> Save profile</button><span role="status" aria-live="polite" data-form-status></span></div>
            </form>
        </div></section>
        <section class="card profile-security-card"><div class="card-body">
            <div class="profile-section-heading is-security"><span><i class="ti ti-shield-lock" aria-hidden="true"></i></span><div><h2>Account and security</h2><p>Your institutional account details and protection status.</p></div></div>
            <dl><dt>Email</dt><dd class="text-break">{{ $user->email }}</dd><dt>Role</dt><dd><span class="workspace-badge role-badge role-{{ str_replace('_', '-', $user->role->name) }}">{{ ucwords(str_replace('_', ' ', $user->role->name)) }}</span></dd><dt>Status</dt><dd><span class="workspace-badge status-badge status-{{ $user->status }}">{{ ucfirst($user->status) }}</span></dd><dt>Member since</dt><dd>{{ $user->created_at->format('F Y') }}</dd></dl>
            <p class="text-muted">Email, role and account status cannot be changed here. Contact institution administration for account corrections.</p>
            <p class="profile-security-status"><i class="ti ti-circle-check" aria-hidden="true"></i><span>Two-factor authentication is enabled.</span></p>
            <a href="{{ route('password.change') }}" class="btn btn-outline-primary">Change password</a>
            <p class="form-text">Password changes require your current password and an authenticator code.</p>
        </div></section>
    </div>
</div>
@endsection

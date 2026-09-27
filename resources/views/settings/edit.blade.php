@extends('layouts.app')
@section('title', 'Settings')
@section('content')
@php
    $role = $user->role->name;
    $notificationOptions = [
        ['message_sound', 'Message sound', 'Play a sound when a new chat message arrives.'],
        ['call_sound', 'Call sounds', 'Play ringing and call status sounds for voice and video calls.'],
        ['message_popups', 'Message popups', 'Show an in-app popup for new chat messages.'],
        ['desktop_messages', 'Desktop message alerts', 'Show a browser notification when permission is granted.'],
    ];
    $soundOptions = [
        ['message_tone', 'Message sound', 'Choose the sound for a new message.', \App\Models\AppSetting::MESSAGE_TONES, 'message'],
        ['call_tone', 'Incoming call ringtone', 'Choose the ring for voice and video calls.', \App\Models\AppSetting::CALL_TONES, 'call'],
    ];
    $displayOptions = [
        ['larger_text', 'Larger text', 'Increase text size across the workspace.'],
        ['reduce_motion', 'Reduce motion', 'Limit interface animations and transitions.'],
    ];
@endphp
<div class="page-container workspace-page settings-page">
    <header class="settings-intro">
        <p class="workspace-eyebrow">Your workspace</p>
        <h1>Settings</h1>
        <p>Choose how the app alerts you and how your workspace appears. Your personal choices apply only to your account.</p>
    </header>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Settings were not saved.</strong>
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" class="settings-form" data-pending-form>
        @csrf @method('PATCH')
        <section class="card settings-card" aria-labelledby="settings-notifications-title">
            <div class="card-body">
                <h2 id="settings-notifications-title"><i class="ti ti-bell" aria-hidden="true"></i> Notifications and calls</h2>
                <p>Choose sounds and popups. Messages, missed calls, class updates and quiz alerts remain in your notification history.</p>
                @foreach($notificationOptions as [$name, $label, $description])
                    <label class="settings-option" for="setting-{{ $name }}">
                        <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                        <span class="form-check form-switch">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="setting-{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $preferences[$name]))>
                        </span>
                    </label>
                @endforeach
                <div class="settings-sounds" aria-label="Choose sounds">
                    @foreach($soundOptions as [$name, $label, $description, $tones, $type])
                        <div class="settings-sound-option">
                            <label for="setting-{{ $name }}"><strong>{{ $label }}</strong><small>{{ $description }}</small></label>
                            <div class="settings-sound-controls">
                                <select id="setting-{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror">
                                    @foreach($tones as $value => $toneLabel)
                                        <option value="{{ $value }}" @selected(old($name, $preferences[$name]) === $value)>{{ $toneLabel }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-primary" data-preview-sound="{{ $type }}" data-tone-select="setting-{{ $name }}" aria-label="Preview {{ strtolower($label) }}"><i class="ti ti-player-play" aria-hidden="true"></i> Preview</button>
                            </div>
                            @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="settings-browser-permission">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="settings-browser-permission">Enable browser notifications</button>
                    <span id="settings-browser-permission-status" role="status" aria-live="polite"></span>
                </div>
                <p class="settings-help">Desktop alerts also require permission in your browser.</p>
            </div>
        </section>
        <section class="card settings-card" aria-labelledby="settings-display-title">
            <div class="card-body">
                <h2 id="settings-display-title"><i class="ti ti-adjustments" aria-hidden="true"></i> Display and accessibility</h2>
                <p>Make the workspace more comfortable to use.</p>
                @foreach($displayOptions as [$name, $label, $description])
                    <label class="settings-option" for="setting-{{ $name }}">
                        <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                        <span class="form-check form-switch">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="setting-{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $preferences[$name]))>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>
        <div class="settings-actions"><button type="submit" class="btn btn-primary" data-pending-label="Saving..."><i class="ti ti-device-floppy" aria-hidden="true"></i> Save my settings</button></div>
    </form>

    @if($role === 'super_admin')
        <section class="card settings-card settings-application" id="application" aria-labelledby="settings-application-title">
            <div class="card-body">
                <h2 id="settings-application-title"><i class="ti ti-settings" aria-hidden="true"></i> Application defaults</h2>
                <p>These defaults apply to accounts that have not saved their own settings. Users can still choose their own preferences.</p>
                <form method="POST" action="{{ route('settings.application.update') }}" data-pending-form>
                    @csrf @method('PATCH')
                    @foreach(array_merge($notificationOptions, $displayOptions) as [$name, $label, $description])
                        <label class="settings-option" for="default-{{ $name }}">
                            <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                            <span class="form-check form-switch">
                                <input type="hidden" name="defaults[{{ $name }}]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="default-{{ $name }}" name="defaults[{{ $name }}]" value="1" @checked(old('defaults.'.$name, $defaults[$name]))>
                            </span>
                        </label>
                    @endforeach
                    <div class="settings-sounds" aria-label="Default sounds">
                        @foreach($soundOptions as [$name, $label, $description, $tones, $type])
                            <div class="settings-sound-option">
                                <label for="default-{{ $name }}"><strong>{{ $label }}</strong><small>{{ $description }}</small></label>
                                <div class="settings-sound-controls">
                                    <select id="default-{{ $name }}" name="defaults[{{ $name }}]" class="form-select @error('defaults.'.$name) is-invalid @enderror">
                                        @foreach($tones as $value => $toneLabel)
                                            <option value="{{ $value }}" @selected(old('defaults.'.$name, $defaults[$name]) === $value)>{{ $toneLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" data-preview-sound="{{ $type }}" data-tone-select="default-{{ $name }}" aria-label="Preview default {{ strtolower($label) }}"><i class="ti ti-player-play" aria-hidden="true"></i> Preview</button>
                                </div>
                                @error('defaults.'.$name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="settings-actions"><button type="submit" class="btn btn-outline-primary" data-pending-label="Saving...">Save application defaults</button></div>
                </form>
            </div>
        </section>
    @endif
</div>
@endsection
@section('scripts')
    <script src="{{ asset('js/settings.js') }}" data-workspace-page-script="settings"></script>
@endsection

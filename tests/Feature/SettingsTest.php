<?php

use App\Models\AppSetting;
use App\Models\Role;
use App\Notifications\ActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('each role has settings without duplicate account or workspace cards', function (string $role): void {
    $user = securityTestUser($role);

    $this->actingAs($user)->get(route('settings.edit'))->assertOk()
        ->assertSee('Notifications and calls')
        ->assertSee('Incoming call ringtone')
        ->assertSee('Preview message sound')
        ->assertSee('Display and accessibility')
        ->assertSee('Settings')
        ->assertDontSee('Account and security')
        ->assertDontSee('Quick access to the tools available for your role.')
        ->assertDontSee('Profile and photo');

    $this->get(route('home'))->assertOk()->assertSee(route('settings.edit'));
})->with([
    Role::SUPER_ADMIN,
    Role::ADMIN,
    Role::TEACHER,
    Role::STUDENT,
]);

test('chime is the default message sound and extra incoming ringtones are available', function (): void {
    $user = securityTestUser('student');

    expect(AppSetting::forUser($user)['message_tone'])->toBe('chime');

    $this->actingAs($user)->get(route('settings.edit'))->assertOk()
        ->assertSee('Double ring')
        ->assertSee('Warm bell')
        ->assertSee('Ascending ring')
        ->assertSee('"message_tone":"chime"', false);
});

test('users can save their own sound and display preferences without changing another account', function (): void {
    $user = securityTestUser('student');
    $other = securityTestUser('teacher');
    $choices = [
        'message_sound' => 0,
        'call_sound' => 0,
        'message_popups' => 0,
        'desktop_messages' => 0,
        'larger_text' => 1,
        'reduce_motion' => 1,
        'message_tone' => 'pulse',
        'call_tone' => 'ascending',
    ];

    $this->actingAs($user)->patch(route('settings.update'), $choices)
        ->assertRedirect(route('settings.edit'));
    expect($user->fresh()->preferences)->toMatchArray(AppSetting::normalize($choices))
        ->and($other->fresh()->preferences)->toBeNull();

    $this->get(route('settings.edit'))->assertOk()
        ->assertSee('settings-larger-text')
        ->assertSee('settings-reduce-motion')
        ->assertSee('"message_sound":false', false)
        ->assertSee('"message_tone":"pulse"', false)
        ->assertSee('"call_tone":"ascending"', false);
    $this->actingAs($other)->get(route('settings.edit'))->assertOk()
        ->assertDontSee('"message_sound":false', false);

    $user->notify(new ActivityNotification('call', 'Missed call', 'From teacher', '/chat'));
    $this->actingAs($user)->getJson(route('notifications.index'))
        ->assertOk()->assertJsonPath('unread_count', 1);
});

test('only super admins can change application defaults and personal choices override them', function (): void {
    $superAdmin = securityTestUser('super_admin');
    $admin = securityTestUser('admin');
    $teacher = securityTestUser('teacher');
    $choices = [
        'message_sound' => 0,
        'call_sound' => 0,
        'message_popups' => 0,
        'desktop_messages' => 0,
        'larger_text' => 1,
        'reduce_motion' => 1,
        'message_tone' => 'chime',
        'call_tone' => 'bright',
    ];

    $this->actingAs($admin)->patch(route('settings.application.update'), ['defaults' => $choices])->assertForbidden();
    expect(AppSetting::query()->count())->toBe(0);

    $this->actingAs($superAdmin)->patch(route('settings.application.update'), ['defaults' => $choices])
        ->assertRedirect(route('settings.edit'));
    expect(AppSetting::defaults())->toMatchArray(AppSetting::normalize($choices))
        ->and(AppSetting::forUser($teacher)['message_sound'])->toBeFalse()
        ->and(AppSetting::forUser($teacher)['larger_text'])->toBeTrue()
        ->and(AppSetting::forUser($teacher)['message_tone'])->toBe('chime');

    $teacher->forceFill(['preferences' => array_replace($choices, ['message_sound' => true])])->save();
    expect(AppSetting::forUser($teacher)['message_sound'])->toBeTrue();
    $invalidDefaults = array_replace($choices, ['message_tone' => 'external-url']);
    $this->actingAs($superAdmin)->patchJson(route('settings.application.update'), ['defaults' => $invalidDefaults])
        ->assertUnprocessable()->assertJsonValidationErrors('defaults.message_tone');
    expect(AppSetting::defaults()['message_tone'])->toBe('chime');
    $this->actingAs($superAdmin)->get(route('settings.edit'))->assertSee('Application defaults');
    $this->actingAs($admin)->get(route('settings.edit'))->assertDontSee('Application defaults');
});

test('invalid preferences are rejected and guests cannot open settings', function (): void {
    $this->get(route('settings.edit'))->assertRedirect(route('login'));
    $user = securityTestUser('student');
    $choices = array_fill_keys(array_keys(AppSetting::DEFAULT_PREFERENCES), 1);
    $choices['message_tone'] = 'classic';
    $choices['call_tone'] = 'classic';
    $choices['message_sound'] = 'invalid';

    $this->actingAs($user)->patchJson(route('settings.update'), $choices)
        ->assertUnprocessable()->assertJsonValidationErrors('message_sound');
    expect($user->fresh()->preferences)->toBeNull();

    $choices['message_sound'] = 1;
    $choices['call_tone'] = 'uploaded-file.mp3';
    $this->patchJson(route('settings.update'), $choices)
        ->assertUnprocessable()->assertJsonValidationErrors('call_tone');
});

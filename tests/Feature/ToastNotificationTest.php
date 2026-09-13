<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notification client handles types accessibility deduplication and its queue', function (): void {
    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/notifications-client.cjs')], base_path());

    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
});

test('authenticated layouts render each flash notification through the shared toast host', function (): void {
    $user = securityTestUser('student');

    $response = $this->actingAs($user)->withSession([
        'success' => 'Settings saved.',
        'warning' => 'Please review your settings.',
    ])->get(route('profile.edit'));

    $response->assertOk()
        ->assertSee('id="app-notifications"', false)
        ->assertSee('data-notification-seed data-type="success"', false)
        ->assertSee('data-notification-seed data-type="warning"', false)
        ->assertSee('Settings saved.')
        ->assertDontSee('alert alert-success', false);

    expect(substr_count($response->getContent(), 'Settings saved.'))->toBe(1);
});

test('validation keeps field feedback and also seeds one general error toast', function (): void {
    $user = securityTestUser('student');

    $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
        'name' => '',
    ])->assertRedirect(route('profile.edit'))->assertSessionHasErrors('name');

    $response = $this->get(route('profile.edit'))->assertOk();

    $response->assertSee('Your profile was not saved.')
        ->assertSee('Please check the highlighted fields.')
        ->assertSee('data-notification-seed data-type="error"', false);

    expect(substr_count($response->getContent(), 'Please check the highlighted fields.'))->toBe(1);
});

test('flash notification content is escaped', function (): void {
    $user = securityTestUser('student');
    $payload = '<img src=x onerror=alert(1)>';

    $response = $this->actingAs($user)->withSession(['error' => $payload])->get(route('profile.edit'));

    $response->assertOk()
        ->assertSee($payload)
        ->assertDontSee($payload, false);
});

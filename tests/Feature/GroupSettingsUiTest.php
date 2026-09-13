<?php

use App\Models\ChatRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->owner = securityTestUser('student', ['name' => 'Group Owner']);
    $this->member = securityTestUser('teacher', ['name' => 'Current Teacher']);
    $this->candidate = securityTestUser('admin', [
        'name' => 'Available Admin',
        'profile' => 'images/users/available-admin.webp',
    ]);
    Storage::disk('public')->put('images/users/available-admin.webp', 'avatar');
    $this->group = ChatRoom::create([
        'type' => 'group',
        'name' => 'English group chat',
        'description' => 'A place to practise English together.',
        'created_by' => $this->owner->id,
    ]);
    $this->group->members()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
    $this->group->members()->attach($this->member, ['role' => 'member', 'joined_at' => now()]);
});

test('owner sees modern group details people picker and profile-aware member cards', function (): void {
    $response = $this->actingAs($this->owner)->get(route('chat.groups.show', $this->group));

    $response->assertOk()
        ->assertSee('English group chat')
        ->assertSee('A place to practise English together.')
        ->assertSee('Group details')
        ->assertSee('Group photo')
        ->assertSee('name="avatar"', false)
        ->assertSee('Add people')
        ->assertSee('Search people...')
        ->assertSee('data-add-role-filter="teacher"', false)
        ->assertSee('Add selected members')
        ->assertSee('Members')
        ->assertSee('Owner')
        ->assertSee('The group owner must remain in the group.')
        ->assertSee('available-admin.webp')
        ->assertSee(route('users.profile', $this->member))
        ->assertSee('css/group-settings.css')
        ->assertSee('js/chat/group-settings.js');
});

test('ordinary member sees group identity and roster without management controls', function (): void {
    $response = $this->actingAs($this->member)->get(route('chat.groups.show', $this->group));

    $response->assertOk()
        ->assertSee('English group chat')
        ->assertSee('A place to practise English together.')
        ->assertSee('Current Teacher')
        ->assertSee('Leave group')
        ->assertDontSee('Add selected members')
        ->assertDontSee('Save details')
        ->assertDontSee('Available Admin');
});

test('owner can update group name and optional description without changing membership', function (): void {
    $memberIds = $this->group->members()->pluck('users.id')->all();

    $this->actingAs($this->owner)->patch(route('chat.groups.update', $this->group), [
        'name' => '  English Practice  ',
        'description' => '  Weekly speaking and writing practice.  ',
    ])->assertRedirect(route('chat.groups.show', $this->group))
        ->assertSessionHas('success', 'Group details updated.');

    expect($this->group->fresh())
        ->name->toBe('English Practice')
        ->description->toBe('Weekly speaking and writing practice.')
        ->and($this->group->members()->pluck('users.id')->all())->toBe($memberIds);

    $this->patch(route('chat.groups.update', $this->group), ['name' => 'Speaking Club'])
        ->assertRedirect(route('chat.groups.show', $this->group));
    expect($this->group->fresh()->description)->toBe('Weekly speaking and writing practice.');
});

test('group detail validation rejects unsafe shapes and excessive descriptions atomically', function (array $payload, string $field): void {
    $before = $this->group->fresh();

    $this->actingAs($this->owner)->patchJson(route('chat.groups.update', $this->group), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->group->fresh())
        ->name->toBe($before->name)
        ->description->toBe($before->description)
        ->avatar->toBe($before->avatar)
        ->updated_at->toEqual($before->updated_at);
})->with([
    [['name' => 'Valid name', 'description' => str_repeat('a', 1001)], 'description'],
    [['name' => 'Valid name', 'description' => ['unexpected']], 'description'],
    [['name' => 'Valid name', 'avatar' => 'forged.png'], 'avatar'],
]);

test('group settings client supports people and current-member searches', function (): void {
    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/group-settings-client.cjs')], base_path());

    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
});

test('owner can upload and safely replace a managed group photo', function (): void {
    $this->group->update(['avatar' => '/storage/images/groups/old-group.png']);
    Storage::disk('public')->put('images/groups/old-group.png', 'old');

    $this->actingAs($this->owner)->patch(route('chat.groups.update', $this->group), [
        'name' => $this->group->name,
        'description' => $this->group->description,
        'avatar' => UploadedFile::fake()->image('new-group.webp'),
    ])->assertRedirect(route('chat.groups.show', $this->group))
        ->assertSessionHas('success', 'Group photo and details updated successfully.');

    $path = $this->group->fresh()->avatar;
    expect($path)->toMatch('~^images/groups/[A-Za-z0-9_-]+\.webp$~')->not->toContain('new-group');
    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertMissing('images/groups/old-group.png');
});

test('invalid group photos are rejected and ordinary members cannot upload them', function (): void {
    $payload = [
        'name' => $this->group->name,
        'avatar' => UploadedFile::fake()->create('payload.svg', 2, 'image/svg+xml'),
    ];

    $this->actingAs($this->owner)->patchJson(route('chat.groups.update', $this->group), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('avatar');
    expect($this->group->fresh()->avatar)->toBeNull();

    $this->patchJson(route('chat.groups.update', $this->group), [
        'name' => $this->group->name,
        'avatar' => UploadedFile::fake()->image('too-large.jpg')->size(3000),
    ])->assertUnprocessable()->assertJsonValidationErrors('avatar');

    $this->actingAs($this->member)->patch(route('chat.groups.update', $this->group), [
        'name' => $this->group->name,
        'avatar' => UploadedFile::fake()->image('unauthorized.png'),
    ])->assertForbidden();
    expect($this->group->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing('images/groups/unauthorized.png');
});

test('stored group photo is rendered in settings conversation list and active chat header', function (): void {
    $this->group->update(['avatar' => 'images/groups/visible-group.png', 'last_message_at' => now()]);
    Storage::disk('public')->put('images/groups/visible-group.png', 'image');
    $url = $this->group->fresh()->avatarUrl();

    $this->actingAs($this->owner)->get(route('chat.groups.show', $this->group))->assertOk()->assertSee($url);
    $this->get(route('chat.index'))->assertOk()->assertSee($url);
    expect($this->getJson(route('chat.rooms.show', $this->group))->assertOk()->json('html'))->toContain($url);
});

test('group settings styles include mobile member actions and accessible focus treatment', function (): void {
    $css = file_get_contents(public_path('css/group-settings.css'));

    expect($css)
        ->toContain('@media (max-width: 767px)')
        ->toContain('@media (max-width: 480px)')
        ->toContain(':focus-within')
        ->toContain('min-height: 68px');
});

<?php

use App\Models\ChatRoom;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Services\Chat\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->viewer = securityTestUser('student');
    $this->target = securityTestUser('teacher', [
        'name' => 'Teacher Profile',
        'email' => 'private-teacher@example.test',
        'phone' => '+855 12 345 678',
        'bio' => 'I help students understand Laravel.',
        'profile' => 'images/users/teacher.png',
        'last_seen_at' => now()->subMinutes(5),
    ]);
    Storage::disk('public')->put('images/users/teacher.png', 'avatar');
});

test('user can view and update their own complete profile', function (): void {
    $this->actingAs($this->viewer)->get(route('profile.edit'))->assertOk()
        ->assertSee('My profile')->assertSee($this->viewer->email)->assertSee('Member since')
        ->assertSee('name="bio"', false)->assertSee('name="phone"', false);

    $roleId = $this->viewer->role_id;
    $this->patch(route('profile.update'), [
        'name' => '  Updated Student  ', 'phone' => '+855 (10) 222-333', 'bio' => '  Learning every day.  ',
    ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Profile updated successfully.');

    expect($this->viewer->fresh())
        ->name->toBe('Updated Student')
        ->phone->toBe('+855 (10) 222-333')
        ->bio->toBe('Learning every day.')
        ->role_id->toBe($roleId);
});

test('normal profile update cannot change role email password or another user', function (string $field): void {
    $this->actingAs($this->viewer)->patchJson(route('profile.update'), [
        'name' => 'Should not save', $field => $field === 'role_id' ? Role::query()->where('name', Role::TEACHER)->value('id') : 'tampered',
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($this->viewer->fresh()->name)->toBe($this->viewer->name);
    $this->patchJson('/profile/'.$this->target->id, ['name' => 'Wrong user'])->assertNotFound();
})->with(['role_id', 'email', 'password', 'status']);

test('profile photo replacement stores a path and deletes only an application managed predecessor', function (): void {
    $this->viewer->update(['profile' => '/storage/images/users/old.png']);
    Storage::disk('public')->put('images/users/old.png', 'old');
    $this->actingAs($this->viewer)->patch(route('profile.update'), [
        'name' => $this->viewer->name, 'photo' => UploadedFile::fake()->image('new-photo.webp'),
    ])->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Profile photo updated successfully.');

    $path = $this->viewer->fresh()->profile;
    expect($path)->toMatch('~^images/users/[A-Za-z0-9_-]+\.webp$~')->not->toContain('new-photo');
    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertMissing('images/users/old.png');

    $this->viewer->update(['profile' => 'https://cdn.example.test/legacy.jpg']);
    $this->patch(route('profile.update'), [
        'name' => $this->viewer->name, 'photo' => UploadedFile::fake()->image('replacement.png'),
    ])->assertRedirect(route('profile.edit'));
    expect($this->viewer->fresh()->profileUrl())->toStartWith('/storage/images/users/');
});

test('invalid profile photos and oversized bios are rejected without partial changes', function (): void {
    $this->actingAs($this->viewer)->patchJson(route('profile.update'), [
        'name' => 'Should not save', 'bio' => str_repeat('a', 1001),
        'photo' => UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['bio', 'photo']);
    expect($this->viewer->fresh()->name)->toBe($this->viewer->name);
    expect(Storage::disk('public')->allFiles())->toBe(['images/users/teacher.png']);
});

test('current chat participants can view safe public profiles', function (): void {
    $room = ChatRoom::create(['type' => 'direct', 'created_by' => $this->viewer->id]);
    $room->members()->attach([$this->viewer->id, $this->target->id], ['joined_at' => now()]);

    $response = $this->actingAs($this->viewer)->get(route('users.profile', $this->target))->assertOk()
        ->assertSee('Teacher Profile')->assertSee('Teacher')->assertSee($this->target->bio)
        ->assertSee('Last seen')->assertSee('Member since')->assertSee($this->target->profileUrl());
    $response->assertDontSee($this->target->email)->assertDontSee($this->target->phone)
        ->assertDontSee('password')->assertDontSee('google2fa');

    $room->members()->detach($this->viewer);
    $this->get(route('users.profile', $this->target))->assertForbidden();
});

test('current classmates can view profiles and only authorized shared classes are listed', function (): void {
    $shared = SchoolClass::create([
        'name' => 'Shared Mathematics', 'description' => 'Shared', 'created_by' => $this->target->id, 'join_code' => Str::random(8),
    ]);
    $private = SchoolClass::create([
        'name' => 'Teacher Private Class', 'description' => 'Private', 'created_by' => $this->target->id, 'join_code' => Str::random(8),
    ]);
    $shared->members()->attach($this->target, ['role' => 'owner', 'joined_at' => now()]);
    $shared->members()->attach($this->viewer, ['role' => 'student', 'joined_at' => now()]);
    $private->members()->attach($this->target, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($this->viewer)->get(route('users.profile', $this->target))->assertOk()
        ->assertSee('Shared Mathematics')->assertDontSee('Teacher Private Class');
    $shared->members()->detach($this->viewer);
    $this->get(route('users.profile', $this->target))->assertForbidden();
});

test('unrelated suspended and deleted users do not expose public profiles', function (): void {
    $this->actingAs($this->viewer)->get(route('users.profile', $this->target))->assertForbidden();
    $room = app(GroupMembershipService::class)->create($this->target, 'Profile room', [$this->viewer->id]);
    $this->target->update(['status' => 'suspended']);
    $this->get(route('users.profile', $this->target))->assertForbidden();
    $this->target->update(['status' => 'active']);
    $this->target->delete();
    $this->get('/users/'.$this->target->id.'/profile')->assertNotFound();
    expect($room->exists)->toBeTrue();
});

test('chat surfaces render updated profile links photos and initials', function (): void {
    $room = ChatRoom::create(['type' => 'direct', 'created_by' => $this->viewer->id, 'last_message_at' => now()]);
    $room->members()->attach([$this->viewer->id, $this->target->id], ['joined_at' => now()]);
    $message = $room->messages()->create(['sender_id' => $this->target->id, 'body' => 'Hello from profile']);

    $index = $this->actingAs($this->viewer)->get(route('chat.index'))->assertOk();
    $index->assertSee(route('users.profile', $this->target))->assertSee($this->target->profileUrl());
    $roomResponse = $this->getJson(route('chat.rooms.show', $room))->assertOk();
    expect($roomResponse->json('html'))->toContain(route('users.profile', $this->target), $this->target->profileUrl(), 'Teacher Profile');
    $rendered = $this->getJson("/chat/messages/{$message->id}/html")->assertOk()->json('html');
    expect($rendered)->toContain(route('users.profile', $this->target), $this->target->profileUrl());

    $this->target->update(['profile' => null, 'name' => 'Teacher Example']);
    $this->getJson("/chat/messages/{$message->id}/html")->assertOk()->assertSee('TE')->assertDontSee('teacher.png');

    $this->actingAs($this->target)->patch(route('profile.update'), ['name' => 'Updated Chat Teacher', 'bio' => 'Updated'])
        ->assertRedirect(route('profile.edit'));
    $this->actingAs($this->viewer)->get(route('chat.index'))->assertOk()->assertSee('Updated Chat Teacher');
});

test('read by data uses the same current profile image URL', function (): void {
    $this->viewer->update(['profile' => 'images/users/viewer.png']);
    Storage::disk('public')->put('images/users/viewer.png', 'avatar');
    $room = app(GroupMembershipService::class)->create($this->target, 'Avatar receipts', [$this->viewer->id]);
    $message = $room->messages()->create(['sender_id' => $this->target->id, 'body' => 'Read this']);
    $this->actingAs($this->viewer)->postJson(route('chat.rooms.read', $room), ['up_to_message_id' => $message->id])->assertOk();
    $this->actingAs($this->target)->getJson(route('chat.messages.reads', $message))->assertOk()
        ->assertJsonPath('readers.0.avatar', $this->viewer->profileUrl());
});

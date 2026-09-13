<?php

use App\Models\ChatRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

test('create chat renders search first people pickers with existing profile data and recent contacts', function (): void {
    $viewer = securityTestUser('student');
    $teacher = securityTestUser('teacher', [
        'name' => 'Teacher Dara',
        'profile' => 'images/users/teacher-dara.webp',
        'last_seen_at' => now(),
    ]);
    $admin = securityTestUser('admin', ['name' => 'School Admin']);
    Storage::disk('public')->put('images/users/teacher-dara.webp', 'avatar');
    $room = ChatRoom::create(['type' => 'direct', 'created_by' => $viewer->id, 'last_message_at' => now()]);
    $room->members()->attach([$viewer->id, $teacher->id], ['joined_at' => now()]);

    $response = $this->actingAs($viewer)->get(route('chat.index'));

    $response->assertOk()
        ->assertSee('Search people...')
        ->assertSee('"recent":true', false)
        ->assertSee('data-role-filter="teacher"', false)
        ->assertSee('data-group-step="review"', false)
        ->assertSee('Group name')
        ->assertSee('Add people')
        ->assertSee('Create group')
        ->assertSee('Teacher Dara')
        ->assertSee('School Admin')
        ->assertSee($teacher->profileUrl())
        ->assertSee('"role":"teacher"', false)
        ->assertSee('css/create-chat.css');
});

test('create chat client filters roles handles empty results and builds unchanged request payloads', function (): void {
    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/create-chat-client.cjs')], base_path());

    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
});

test('create chat styles include mobile layout visible focus and scrollable results', function (): void {
    $css = file_get_contents(public_path('css/create-chat.css'));

    expect($css)
        ->toContain('@media (max-width: 576px)')
        ->toContain(':focus-visible')
        ->toContain('overflow-y: auto')
        ->toContain('min-height: 58px');
});

test('existing direct and group creation endpoints still accept the redesigned client payloads', function (): void {
    $viewer = securityTestUser('student');
    $teacher = securityTestUser('teacher');
    $student = securityTestUser('student');
    $this->actingAs($viewer);

    $directId = $this->postJson(route('chat.direct.create'), ['user_id' => (string) $teacher->id])
        ->assertOk()->json('room_id');
    $this->postJson(route('chat.direct.create'), ['user_id' => (string) $teacher->id])
        ->assertOk()->assertJsonPath('room_id', $directId);

    $groupId = $this->postJson(route('chat.group.create'), [
        'name' => 'Project team',
        'members' => [(string) $teacher->id, (string) $student->id],
    ])->assertOk()->json('room_id');

    expect(ChatRoom::findOrFail($directId)->members()->count())->toBe(2)
        ->and(ChatRoom::findOrFail($groupId)->members()->count())->toBe(3);
});

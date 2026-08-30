<?php

use App\Events\Classes\SchoolClassChannelMessageSent;
use App\Models\ClassMembershipAudit;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassAccessService;
use App\Services\ClassManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = securityTestUser('teacher');
    $this->schoolClass = SchoolClass::query()->create([
        'name' => 'Authorization class', 'created_by' => $this->owner->id, 'join_code' => Str::upper(Str::random(8)),
    ]);
    $this->schoolClass->members()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
    $this->channel = $this->schoolClass->channels()->create([
        'name' => 'Private discussion', 'slug' => 'private-discussion', 'created_by' => $this->owner->id,
        'is_default' => false,
    ]);
    Event::fake([SchoolClassChannelMessageSent::class]);
});

test('outsider teachers cannot access class metadata content membership or channels', function (): void {
    $outsider = securityTestUser('teacher');
    $student = securityTestUser();
    $this->actingAs($outsider);
    $this->get(route('classes.show', $this->schoolClass))->assertForbidden();
    $this->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => 'No entry'])->assertForbidden();
    $this->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $student->id, 'role' => 'student'])->assertForbidden();
    $this->delete(route('classes.members.destroy', [$this->schoolClass, $this->owner]))->assertForbidden();
    $this->postJson(route('classes.channels.store', $this->schoolClass), ['name' => 'No entry'])->assertForbidden();
    $this->delete(route('classes.channels.destroy', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->patchJson(route('classes.update', $this->schoolClass), ['name' => 'No entry'])->assertForbidden();
    $this->get(route('classes.index'))->assertDontSee('Authorization class');
    $this->assertDatabaseCount('class_membership_audits', 0);
});

test('administrators manage metadata but must explicitly enroll for messages', function (string $role): void {
    $admin = securityTestUser($role);
    $message = $this->channel->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Class-only body']);
    $this->actingAs($admin)->get(route('classes.index'))->assertOk()->assertSee('Authorization class');
    $this->get(route('classes.show', $this->schoolClass))->assertOk()->assertSee('Enroll for message access')
        ->assertDontSee('Class-only body')->assertDontSee('Add Channel');
    $this->patch(route('classes.update', $this->schoolClass), ['name' => 'Updated metadata'])->assertRedirect();
    $this->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => 'Forbidden'])->assertForbidden();
    $this->from(route('classes.show', $this->schoolClass))->post(route('classes.enroll', $this->schoolClass))
        ->assertRedirect(route('classes.show', $this->schoolClass));
    $this->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertOk()->assertSee('Class-only body');
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => 'Enrolled'])->assertRedirect();
    $this->postJson(route('classes.channels.store', $this->schoolClass), ['name' => 'Forbidden'])->assertForbidden();
    $this->assertDatabaseHas('class_membership_audits', [
        'actor_id' => $admin->id, 'target_id' => $admin->id, 'action' => 'administrative_enrollment', 'new_role' => 'student',
    ]);
    expect(json_encode(ClassMembershipAudit::all()))->not->toContain('Class-only body', $admin->google2fa_secret, $this->schoolClass->join_code);
})->with(['admin', 'super_admin']);

test('administrative class creation selects a teacher owner and does not enroll the creator', function (string $role): void {
    $admin = securityTestUser($role);
    $this->actingAs($admin)->postJson(route('classes.store'), ['name' => 'Created class'])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    $this->postJson(route('classes.store'), ['name' => 'Created class', 'owner_id' => $admin->id])->assertUnprocessable();
    $this->post(route('classes.store'), ['name' => 'Created class', 'owner_id' => $this->owner->id])->assertRedirect();
    $created = SchoolClass::where('name', 'Created class')->sole();
    expect($created->memberRecords()->where('role', 'owner')->sole()->user_id)->toBe($this->owner->id);
    expect($created->memberRecords()->where('user_id', $admin->id)->exists())->toBeFalse();
    $this->assertDatabaseHas('class_membership_audits', ['school_class_id' => $created->id, 'action' => 'owner_assigned']);
})->with(['admin', 'super_admin']);

test('only eligible application teachers can be owners and co-teachers', function (string $role, string $status): void {
    $target = securityTestUser($role, ['status' => $status]);
    $admin = securityTestUser('admin');
    $this->actingAs($admin)->postJson(route('classes.store'), ['name' => 'Invalid owner', 'owner_id' => $target->id])->assertUnprocessable();
    $this->actingAs($this->owner)->postJson(route('classes.members.store', $this->schoolClass), [
        'user_id' => $target->id, 'role' => 'teacher',
    ])->assertForbidden();
    $this->postJson(route('classes.owner', $this->schoolClass), [
        'owner_id' => $target->id, 'confirm_transfer' => true,
    ])->assertUnprocessable();
    $this->assertDatabaseMissing('school_class_members', ['school_class_id' => $this->schoolClass->id, 'user_id' => $target->id]);
})->with([['student', 'active'], ['admin', 'active'], ['super_admin', 'active'], ['teacher', 'suspended']]);

test('owner adds and removes co-teachers with an audit trail', function (): void {
    $teacher = securityTestUser('teacher');
    $this->actingAs($this->owner)->from(route('classes.show', $this->schoolClass))
        ->post(route('classes.members.store', $this->schoolClass), ['user_id' => $teacher->id, 'role' => 'teacher'])
        ->assertRedirect(route('classes.show', $this->schoolClass))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('class_membership_audits', ['actor_id' => $this->owner->id, 'target_id' => $teacher->id, 'action' => 'member_added', 'new_role' => 'teacher']);
    $this->delete(route('classes.members.destroy', [$this->schoolClass, $teacher]))->assertRedirect(route('classes.show', $this->schoolClass));
    $this->assertDatabaseHas('class_membership_audits', ['target_id' => $teacher->id, 'action' => 'member_removed', 'old_role' => 'teacher', 'new_role' => null]);
});

test('co-teachers manage students and channels but not other teachers or ownership', function (): void {
    $teacher = securityTestUser('teacher');
    $peer = securityTestUser('teacher');
    $student = securityTestUser();
    $this->schoolClass->members()->attach($teacher, ['role' => 'teacher']);
    $this->schoolClass->members()->attach($peer, ['role' => 'teacher']);
    $this->actingAs($teacher)->get(route('classes.show', $this->schoolClass))->assertOk()
        ->assertSee('Add Channel')->assertDontSee('value="teacher"', false)->assertDontSee('Transfer Ownership');
    $this->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $student->id, 'role' => 'teacher'])->assertForbidden();
    $this->post(route('classes.members.store', $this->schoolClass), ['user_id' => $student->id, 'role' => 'student'])->assertRedirect();
    $this->delete(route('classes.members.destroy', [$this->schoolClass, $peer]))->assertForbidden();
    $this->postJson(route('classes.owner', $this->schoolClass), ['owner_id' => $teacher->id, 'confirm_transfer' => true])->assertForbidden();
    $this->post(route('classes.channels.store', $this->schoolClass), ['name' => 'Co-teacher channel'])->assertRedirect();
    $this->delete(route('classes.channels.destroy', [$this->schoolClass, $this->channel]))->assertRedirect();
    $this->delete(route('classes.members.destroy', [$this->schoolClass, $student]))->assertRedirect();
});

test('student members cannot assign roles or use administrative enrollment', function (): void {
    $student = securityTestUser();
    $this->schoolClass->members()->attach($student, ['role' => 'student']);
    $this->actingAs($student)->get(route('classes.show', $this->schoolClass))->assertOk()
        ->assertDontSee('Add Member')->assertDontSee('Add Channel')->assertDontSee($this->schoolClass->join_code);
    $this->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $student->id, 'role' => 'owner'])->assertForbidden();
    $this->post(route('classes.enroll', $this->schoolClass))->assertForbidden();
    $this->postJson(route('classes.channels.store', $this->schoolClass), ['name' => 'Unauthorized'])->assertForbidden();
    $this->delete(route('classes.channels.destroy', [$this->schoolClass, $this->channel]))->assertForbidden();
});

test('route and submitted identifiers cannot cross class boundaries', function (): void {
    $otherOwner = securityTestUser('teacher');
    $otherClass = SchoolClass::create(['name' => 'Other class', 'created_by' => $otherOwner->id, 'join_code' => 'OTHER123']);
    $otherClass->members()->attach($otherOwner, ['role' => 'owner']);
    $otherChannel = $otherClass->channels()->create(['name' => 'Other', 'slug' => 'other', 'created_by' => $otherOwner->id]);
    $this->actingAs($this->owner);
    $this->get(route('classes.channels.show', [$this->schoolClass, $otherChannel]))->assertNotFound();
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $otherChannel]))->assertNotFound();
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $otherChannel]), ['body' => 'Cross class'])->assertNotFound();
    $this->delete(route('classes.channels.destroy', [$this->schoolClass, $otherChannel]))->assertNotFound();
    $this->delete(route('classes.members.destroy', [$this->schoolClass, $otherOwner]))->assertNotFound();
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), [
        'body' => 'Forged sender', 'sender_id' => $otherOwner->id, 'school_class_channel_id' => $otherChannel->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['sender_id', 'school_class_channel_id']);
    $this->postJson(route('classes.channels.store', $this->schoolClass), [
        'name' => 'Forged channel', 'is_default' => true, 'school_class_id' => $otherClass->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['is_default', 'school_class_id']);
    $this->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $otherOwner->id, 'role' => 'owner'])
        ->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->patchJson(route('classes.update', $this->schoolClass), ['name' => 'Forged owner', 'created_by' => $otherOwner->id])
        ->assertUnprocessable()->assertJsonValidationErrors('created_by');
    expect($otherChannel->fresh()->deleted_at)->toBeNull();
    $this->assertDatabaseCount('school_class_channel_messages', 0);
});

test('removed members lose read write and broadcast access including existing topic deliveries', function (): void {
    $student = securityTestUser();
    $this->schoolClass->members()->attach($student, ['role' => 'student']);
    $access = app(ClassAccessService::class);
    $membershipId = $access->membership($student, $this->schoolClass)->id;
    $callback = Broadcast::getChannels()['school-class.membership.{membershipId}'];
    expect($callback($student, (string) $membershipId))->toBeTrue();
    $message = $this->channel->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Never broadcast this']);
    $event = new SchoolClassChannelMessageSent($message);
    $oldTopic = 'private-school-class.membership.'.$membershipId;
    expect(collect($event->broadcastOn())->pluck('name'))->toContain($oldTopic);
    expect($event->broadcastWith())->toBe([]);
    $oldCode = $this->schoolClass->join_code;
    $this->actingAs($this->owner)->delete(route('classes.members.destroy', [$this->schoolClass, $student]))->assertRedirect();
    expect($callback($student, (string) $membershipId))->toBeFalse();
    expect(collect($event->broadcastOn())->pluck('name'))->not->toContain($oldTopic);
    $this->actingAs($student)->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => 'Stale tab'])->assertForbidden();
    $this->post(route('classes.join'), ['join_code' => $oldCode])->assertSessionHas('error', 'Invalid join code.');
    expect($this->schoolClass->fresh()->join_code)->not->toBe($oldCode);
    app(ClassManagementService::class)->add($this->owner, $this->schoolClass, $student->id, 'student');
    $newId = $access->membership($student, $this->schoolClass)->id;
    expect($newId)->not->toBe($membershipId);
    expect($callback($student, (string) $membershipId))->toBeFalse();
    expect($callback($student, (string) $newId))->toBeTrue();
    expect(collect($event->broadcastOn())->pluck('name'))->not->toContain($oldTopic);
});

test('broadcast authentication rejects guessed identities and the retired shared topic', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    require base_path('routes/channels.php');
    $student = securityTestUser();
    $membershipId = app(ClassAccessService::class)->membership($this->owner, $this->schoolClass)->id;
    $payload = ['socket_id' => '123.456', 'channel_name' => 'private-school-class.membership.'.$membershipId];
    $this->actingAs($this->owner)->postJson('/broadcasting/auth', $payload)->assertOk()->assertJsonStructure(['auth']);
    $this->actingAs($student)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    $this->actingAs($this->owner)->postJson('/broadcasting/auth', [
        'socket_id' => '123.456', 'channel_name' => 'private-school-class.channel.'.$this->channel->id,
    ])->assertForbidden();
});

test('suspension and incomplete onboarding prevent broadcast subscription and future content', function (array $changes): void {
    $student = securityTestUser();
    $this->schoolClass->members()->attach($student, ['role' => 'student']);
    $id = app(ClassAccessService::class)->membership($student, $this->schoolClass)->id;
    $message = $this->channel->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Sensitive']);
    $student->update($changes);
    expect(app(ClassAccessService::class)->subscription($student, $id))->toBeFalse();
    expect(collect((new SchoolClassChannelMessageSent($message))->broadcastOn())->pluck('name'))
        ->not->toContain('private-school-class.membership.'.$id);
})->with([[['status' => 'suspended']], [['google2fa_enabled' => false]], [['must_change_password' => true]]]);

test('soft deleted classes and channels cannot be read sent to or broadcast', function (string $deleted): void {
    $message = $this->channel->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Retained history']);
    $this->{$deleted}->delete();
    $this->actingAs($this->owner)->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertNotFound();
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel]))->assertNotFound();
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => 'No'])->assertNotFound();
    expect((new SchoolClassChannelMessageSent($message))->broadcastOn())->toBe([]);
    expect($message->fresh()->body)->toBe('Retained history');
})->with(['schoolClass', 'channel']);

test('legacy invalid teaching memberships are preserved but grant no teaching privileges', function (): void {
    $legacy = securityTestUser();
    $this->schoolClass->members()->attach($legacy, ['role' => 'teacher']);
    $this->actingAs($legacy)->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertOk();
    $this->postJson(route('classes.channels.store', $this->schoolClass), ['name' => 'Invalid privilege'])->assertForbidden();
    $this->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $this->owner->id, 'role' => 'teacher'])->assertForbidden();
    $this->assertDatabaseHas('school_class_members', ['user_id' => $legacy->id, 'role' => 'teacher']);
});

test('ownership transfer explicitly repairs a legacy admin owner without deleting history', function (): void {
    $admin = securityTestUser('admin');
    $this->schoolClass->memberRecords()->where('user_id', $this->owner->id)->update(['role' => 'teacher']);
    $this->schoolClass->members()->attach($admin, ['role' => 'owner']);
    $this->actingAs($admin)->postJson(route('classes.owner', $this->schoolClass), ['owner_id' => $this->owner->id])
        ->assertUnprocessable()->assertJsonValidationErrors('confirm_transfer');
    $this->post(route('classes.owner', $this->schoolClass), ['owner_id' => $this->owner->id, 'confirm_transfer' => true])->assertRedirect();
    $this->assertDatabaseHas('school_class_members', ['user_id' => $admin->id, 'role' => 'student']);
    $this->assertDatabaseHas('school_class_members', ['user_id' => $this->owner->id, 'role' => 'owner']);
    expect($this->schoolClass->memberRecords()->where('role', 'owner')->count())->toBe(1);
    $this->assertDatabaseHas('class_membership_audits', ['actor_id' => $admin->id, 'target_id' => $admin->id, 'action' => 'ownership_transferred_out']);
    $this->assertDatabaseHas('class_membership_audits', ['actor_id' => $admin->id, 'target_id' => $this->owner->id, 'action' => 'ownership_transferred_in']);
    expect($this->schoolClass->fresh()->created_by)->toBe($this->owner->id);
});

test('a demoted teacher is reauthorized inside the mutation lock', function (): void {
    $student = securityTestUser();
    $this->owner->load('role');
    User::whereKey($this->owner->id)->update(['role_id' => Role::where('name', 'student')->firstOrFail()->id]);
    expect(fn () => app(ClassManagementService::class)->add($this->owner, $this->schoolClass, $student->id, 'student'))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    $this->assertDatabaseMissing('school_class_members', ['user_id' => $student->id]);
});

test('administrators cannot enroll peers or super admins and teachers cannot enroll administrators', function (): void {
    $admin = securityTestUser('admin');
    $peer = securityTestUser('admin');
    $super = securityTestUser('super_admin');
    $this->actingAs($admin);
    foreach ([$peer, $super] as $target) {
        $this->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $target->id, 'role' => 'student'])->assertForbidden();
    }
    $this->actingAs($this->owner)->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $admin->id, 'role' => 'student'])->assertForbidden();
});

test('authorized message fetches are bounded non-cacheable and return only their channel', function (): void {
    $other = $this->schoolClass->channels()->create(['name' => 'Other', 'slug' => 'other', 'created_by' => $this->owner->id]);
    $other->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Other channel body']);
    $message = $this->channel->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Visible body']);
    $response = $this->actingAs($this->owner)->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel]))
        ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Visible body')->assertDontSee('Other channel body');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel, 'after_id' => $message->id]))
        ->assertOk()->assertJsonCount(0, 'messages');
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel, 'after_id' => -1]))->assertUnprocessable();
});

test('malformed message and join inputs do not trigger server errors or role assignment', function (): void {
    $this->actingAs($this->owner)->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => ['invalid']])->assertUnprocessable();
    $this->postJson(route('classes.channels.store', $this->schoolClass), ['name' => ['invalid']])->assertUnprocessable();
    $this->postJson(route('classes.join'), ['join_code' => ['invalid']])->assertUnprocessable();
    $this->postJson(route('classes.join'), ['join_code' => $this->schoolClass->join_code, 'role' => 'owner'])->assertUnprocessable();
});

test('class images are stored privately and authorized independently of supplied paths', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::fake('public');
    $this->actingAs($this->owner)->post(route('classes.store'), [
        'name' => 'Private image class',
        'avatar' => \Illuminate\Http\UploadedFile::fake()->image('class.png'),
    ])->assertRedirect();
    $created = SchoolClass::where('name', 'Private image class')->sole();
    expect($created->avatar)->toStartWith('class-avatars/');
    \Illuminate\Support\Facades\Storage::disk('local')->assertExists($created->avatar);
    \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($created->avatar);
    $response = $this->get(route('classes.avatar', $created))->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->actingAs(securityTestUser('teacher'))->get(route('classes.avatar', $created))->assertForbidden();
    $this->actingAs(securityTestUser('admin'))->get(route('classes.avatar', $created))->assertOk();
    $student = securityTestUser();
    $created->members()->attach($student, ['role' => 'student']);
    $this->actingAs($student)->get(route('classes.avatar', $created))->assertOk();
    app(ClassManagementService::class)->remove($this->owner, $created, $student);
    $this->get(route('classes.avatar', $created))->assertForbidden();
    $created->update(['avatar' => '../private.txt']);
    $this->actingAs($this->owner)->get(route('classes.avatar', [$created, 'path' => '../private.txt']))->assertNotFound();
});

test('class image migration previews then verifies copies before retiring public URLs', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::fake('public');
    $public = \Illuminate\Support\Facades\Storage::disk('public');
    $private = \Illuminate\Support\Facades\Storage::disk('local');
    $public->put('classes/example.png', 'test-image-bytes');
    $this->schoolClass->update(['avatar' => 'classes/example.png']);
    $this->artisan('classes:privatize-avatars')->expectsOutput('Pending images: 1. Failed: 0.')->assertSuccessful();
    expect($this->schoolClass->fresh()->avatar)->toBe('classes/example.png');
    $public->assertExists('classes/example.png');
    $private->assertMissing('class-avatars/example.png');
    $this->artisan('classes:privatize-avatars', ['--commit' => true])->assertSuccessful();
    $public->assertMissing('classes/example.png');
    expect($private->get('class-avatars/example.png'))->toBe('test-image-bytes');
    expect($this->schoolClass->fresh()->avatar)->toBe('class-avatars/example.png');
    $this->artisan('classes:privatize-avatars', ['--commit' => true])->expectsOutput('Processed images: 0. Failed: 0.')->assertSuccessful();
});

test('class image migration refuses mismatched copies without deleting the public original', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::fake('public');
    $this->schoolClass->update(['avatar' => 'classes/example.png']);
    \Illuminate\Support\Facades\Storage::disk('public')->put('classes/example.png', 'original');
    \Illuminate\Support\Facades\Storage::disk('local')->put('class-avatars/example.png', 'different');
    $this->artisan('classes:privatize-avatars', ['--commit' => true])->assertFailed();
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists('classes/example.png');
    expect($this->schoolClass->fresh()->avatar)->toBe('classes/example.png');
});

test('membership writes roll back if the audit cannot be recorded', function (): void {
    $student = securityTestUser();
    ClassMembershipAudit::creating(function (): void {
        throw new RuntimeException('Audit unavailable');
    });
    try {
        expect(fn () => app(ClassManagementService::class)->add($this->owner, $this->schoolClass, $student->id, 'student'))
            ->toThrow(RuntimeException::class, 'Audit unavailable');
    } finally {
        ClassMembershipAudit::flushEventListeners();
    }
    $this->assertDatabaseMissing('school_class_members', ['user_id' => $student->id]);
});

test('a realtime transport failure does not make a committed class message fail', function (): void {
    Event::fake()->except([SchoolClassChannelMessageSent::class]);
    Broadcast::shouldReceive('queue')->once()->andThrow(new RuntimeException('Transport unavailable'));
    $this->actingAs($this->owner)->post(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), [
        'body' => 'Stored despite the unavailable transport',
    ])->assertRedirect(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('school_class_channel_messages', [
        'school_class_channel_id' => $this->channel->id, 'body' => 'Stored despite the unavailable transport',
    ]);
});

test('a disabled application role cannot list classes or retain subscriptions', function (): void {
    $this->owner->role->update(['status' => false]);
    $id = app(ClassAccessService::class)->membership($this->owner, $this->schoolClass)->id;
    $this->actingAs($this->owner)->get(route('classes.index'))->assertForbidden();
    expect(app(ClassAccessService::class)->subscription($this->owner, $id))->toBeFalse();
});

test('the realtime client fetches authorized content and clears a revoked class page', function (): void {
    $script = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
(async () => {
    let start, listener, interval, cleared, left, topic, calls = 0, revoked = false;
    const list = {
        querySelectorAll: () => [{dataset: {classMessageId: '10'}}],
        querySelector: () => null,
        appendChild: () => { throw new Error('No message body may come from an empty signal'); },
    };
    const page = {
        dataset: {membershipId: '7', messagesUrl: '/classes/1/channels/1/messages'},
        replaceChildren: (node) => { revoked = node.textContent.includes('no longer available'); },
    };
    vm.runInNewContext(fs.readFileSync(process.argv[1], 'utf8'), {
        URL,
        document: {
            addEventListener: (_event, callback) => { start = callback; },
            getElementById: (id) => id === 'school-class-channel-page' ? page : list,
            createElement: () => ({}),
        },
        window: {
            location: {origin: 'http://localhost'},
            addEventListener: () => {},
            Echo: {
                private: (name) => { topic = name; return {listen: (_event, callback) => { listener = callback; }}; },
                leave: (name) => { left = name; },
            },
        },
        setInterval: (callback) => { interval = callback; return 42; },
        clearInterval: (id) => { cleared = id; },
        fetch: async (url, options) => {
            calls++;
            assert.equal(options.cache, 'no-store');
            assert.equal(url.searchParams.get('after_id'), '10');
            if (calls === 1) {
                return {ok: true, status: 200, json: async () => ({messages: [], next_id: 10, has_more: false})};
            }
            return {ok: false, status: 403};
        },
    });
    start();
    await new Promise(setImmediate);
    assert.equal(topic, 'school-class.membership.7');
    await listener({body: 'Untrusted socket data', message_id: 11});
    assert.equal(calls, 2);
    assert.equal(revoked, true);
    assert.equal(left, topic);
    assert.equal(cleared, 42);
    await interval();
    assert.equal(calls, 2);
})().catch((error) => { console.error(error); process.exit(1); });
JS;
    $process = new \Symfony\Component\Process\Process(['node', '-e', $script, public_path('js/classes/channel-realtime.js')]);
    $process->mustRun();
    expect($process->isSuccessful())->toBeTrue();
});

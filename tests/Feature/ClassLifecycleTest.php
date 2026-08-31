<?php

use App\Events\Classes\SchoolClassChannelMessageSent;
use App\Models\ClassMembershipAudit;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Services\AccountManagementService;
use App\Services\ClassAccessService;
use App\Services\ClassManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = securityTestUser('teacher');
    $this->schoolClass = SchoolClass::create([
        'name' => 'Lifecycle class', 'created_by' => $this->owner->id, 'join_code' => Str::upper(Str::random(8)),
    ]);
    $this->schoolClass->members()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
    $this->channel = $this->schoolClass->channels()->create([
        'name' => 'General', 'slug' => 'general', 'is_default' => true, 'created_by' => $this->owner->id,
    ]);
    $this->announcement = $this->schoolClass->channels()->create([
        'name' => 'Announcement', 'slug' => 'announcement', 'is_default' => true, 'created_by' => $this->owner->id,
    ]);
    $this->from(route('classes.show', $this->schoolClass));
    Event::fake([SchoolClassChannelMessageSent::class]);
});

test('teachers create their own classes without forging an owner or archive state', function (): void {
    $otherTeacher = securityTestUser('teacher');
    $this->actingAs($this->owner)->postJson(route('classes.store'), [
        'name' => 'Forged owner', 'owner_id' => $otherTeacher->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    $this->postJson(route('classes.store'), ['name' => 'Forged archive', 'archived_at' => now()->toISOString()])
        ->assertUnprocessable()->assertJsonValidationErrors('archived_at');
    $response = $this->post(route('classes.store'), ['name' => 'Teacher-created class']);
    $created = SchoolClass::where('name', 'Teacher-created class')->sole();
    $response->assertRedirect(route('classes.show', $created));
    expect($created->memberRecords()->where('role', 'owner')->sole()->user_id)->toBe($this->owner->id);
    expect($created->isArchived())->toBeFalse();
    expect($created->channels()->where('slug', 'announcement')->sole()->isAnnouncement())->toBeTrue();
    $this->actingAs(securityTestUser())->postJson(route('classes.store'), ['name' => 'Student class'])->assertForbidden();
});

test('archive and restore are limited to the owner and institutional administrators', function (string $kind, bool $allowed): void {
    $actor = $kind === 'owner' ? $this->owner : securityTestUser($kind === 'co_teacher' ? 'teacher' : $kind);
    if ($kind === 'co_teacher') {
        $this->schoolClass->members()->attach($actor, ['role' => 'teacher']);
    }
    if ($kind === 'student') {
        $this->schoolClass->members()->attach($actor, ['role' => 'student']);
    }
    $response = $this->actingAs($actor)->post(route('classes.archive', $this->schoolClass));
    if (! $allowed) {
        $response->assertForbidden();
        expect($this->schoolClass->fresh()->isArchived())->toBeFalse();
        app(ClassManagementService::class)->setArchived($this->owner, $this->schoolClass, true);
        $this->post(route('classes.unarchive', $this->schoolClass))->assertForbidden();

        return;
    }
    $response->assertRedirect(route('classes.show', $this->schoolClass));
    $archivedAt = $this->schoolClass->fresh()->archived_at;
    $this->post(route('classes.archive', $this->schoolClass))->assertRedirect(route('classes.show', $this->schoolClass));
    expect($this->schoolClass->fresh()->archived_at->equalTo($archivedAt))->toBeTrue();
    $this->post(route('classes.unarchive', $this->schoolClass))->assertRedirect(route('classes.show', $this->schoolClass));
    expect($this->schoolClass->fresh()->isArchived())->toBeFalse();
})->with([['owner', true], ['admin', true], ['super_admin', true], ['co_teacher', false], ['teacher', false], ['student', false]]);

test('archived members retain history but every class mutation is denied', function (string $role): void {
    $actor = $role === 'owner' ? $this->owner : securityTestUser($role === 'co_teacher' ? 'teacher' : $role);
    if ($actor->id !== $this->owner->id) {
        $this->schoolClass->members()->attach($actor, ['role' => $role === 'co_teacher' ? 'teacher' : 'student']);
    }
    $student = securityTestUser();
    $candidate = securityTestUser('teacher');
    $this->schoolClass->members()->attach($student, ['role' => 'student']);
    $custom = $this->schoolClass->channels()->create(['name' => 'Project', 'slug' => 'project', 'created_by' => $this->owner->id]);
    $message = $this->channel->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Retained history']);
    app(ClassManagementService::class)->setArchived($this->owner, $this->schoolClass, true);
    $this->actingAs($actor)->get(route('classes.show', $this->schoolClass))->assertOk()
        ->assertSee('Archived class')->assertDontSee('Add Member')->assertDontSee('Add Channel')->assertDontSee('Leave Class');
    $this->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertOk()
        ->assertSee('Retained history')->assertDontSee('id="class-channel-message-form"', false);
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->channel]))
        ->assertOk()->assertJsonPath('can_send', false)->assertJsonPath('messages.0.body', 'Retained history');
    $id = app(ClassAccessService::class)->membership($actor, $this->schoolClass)->id;
    expect(app(ClassAccessService::class)->subscription($actor, $id))->toBeTrue();
    $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => 'Rejected'])->assertForbidden();
    $this->postJson(route('classes.members.store', $this->schoolClass), ['user_id' => $candidate->id, 'role' => 'teacher'])->assertForbidden();
    $this->delete(route('classes.members.destroy', [$this->schoolClass, $student]))->assertForbidden();
    $this->post(route('classes.leave', $this->schoolClass))->assertForbidden();
    $this->postJson(route('classes.join'), ['join_code' => $this->schoolClass->join_code])->assertForbidden();
    $this->post(route('classes.enroll', $this->schoolClass))->assertForbidden();
    $this->postJson(route('classes.owner', $this->schoolClass), ['owner_id' => $candidate->id, 'confirm_transfer' => true])->assertForbidden();
    $this->post(route('classes.regenerate-code', $this->schoolClass))->assertForbidden();
    $this->postJson(route('classes.channels.store', $this->schoolClass), ['name' => 'Rejected'])->assertForbidden();
    $this->delete(route('classes.channels.destroy', [$this->schoolClass, $custom]))->assertForbidden();
    $this->patchJson(route('classes.update', $this->schoolClass), ['name' => 'Rejected'])->assertForbidden();
    expect($message->fresh()->body)->toBe('Retained history');
    expect($custom->fresh()->deleted_at)->toBeNull();
    $this->assertDatabaseCount('school_class_channel_messages', 1);
    $this->assertDatabaseCount('class_membership_audits', 0);
})->with(['owner', 'co_teacher', 'student', 'admin', 'super_admin']);

test('archived classes do not enroll outsiders or bypass administrator content boundaries', function (string $role): void {
    app(ClassManagementService::class)->setArchived($this->owner, $this->schoolClass, true);
    $actor = securityTestUser($role);
    $this->actingAs($actor)->get(route('classes.channels.show', [$this->schoolClass, $this->channel]))->assertForbidden();
    $this->postJson(route('classes.join'), ['join_code' => $this->schoolClass->join_code])->assertForbidden();
    $this->assertDatabaseMissing('school_class_members', ['user_id' => $actor->id]);
})->with(Role::NAMES);

test('restoring a class enables messages and membership operations again', function (): void {
    $this->actingAs($this->owner)->post(route('classes.archive', $this->schoolClass))->assertRedirect(route('classes.show', $this->schoolClass));
    $this->post(route('classes.unarchive', $this->schoolClass))->assertRedirect(route('classes.show', $this->schoolClass));
    $student = securityTestUser();
    $this->post(route('classes.members.store', $this->schoolClass), ['user_id' => $student->id, 'role' => 'student'])
        ->assertRedirect(route('classes.show', $this->schoolClass))->assertSessionHasNoErrors();
    $this->post(route('classes.channels.messages.store', [$this->schoolClass, $this->channel]), ['body' => 'After restore'])
        ->assertRedirect(route('classes.channels.show', [$this->schoolClass, $this->channel]));
    $this->assertDatabaseHas('school_class_channel_messages', ['body' => 'After restore', 'sender_id' => $this->owner->id]);
});

test('only class teachers and owner post announcements while enrolled users can read', function (string $kind, bool $allowed): void {
    $actor = $kind === 'owner' ? $this->owner : securityTestUser(in_array($kind, ['co_teacher', 'teacher_member'], true) ? 'teacher' : $kind);
    if ($actor->id !== $this->owner->id) {
        $this->schoolClass->members()->attach($actor, ['role' => $kind === 'co_teacher' ? 'teacher' : 'student']);
    }
    $this->announcement->messages()->create(['body' => 'Existing announcement', 'sender_id' => $this->owner->id]);
    $this->actingAs($actor)->get(route('classes.channels.show', [$this->schoolClass, $this->announcement]))->assertOk()->assertSee('Existing announcement');
    $this->getJson(route('classes.channels.messages.index', [$this->schoolClass, $this->announcement]))->assertJsonPath('can_send', $allowed);
    $response = $this->postJson(route('classes.channels.messages.store', [$this->schoolClass, $this->announcement]), ['body' => 'New announcement']);
    if ($allowed) {
        $response->assertCreated()->assertJsonPath('message.body', 'New announcement');
        $this->assertDatabaseHas('school_class_channel_messages', ['body' => 'New announcement', 'sender_id' => $actor->id]);
    } else {
        $response->assertForbidden();
        $this->assertDatabaseMissing('school_class_channel_messages', ['body' => 'New announcement']);
    }
})->with([['owner', true], ['co_teacher', true], ['student', false], ['teacher_member', false], ['admin', false], ['super_admin', false]]);

test('join code regeneration is owner controlled and duplicate joins preserve roles', function (): void {
    $student = securityTestUser();
    $oldCode = $this->schoolClass->join_code;
    $this->actingAs($student)->post(route('classes.join'), ['join_code' => strtolower($oldCode)])->assertRedirect(route('classes.show', $this->schoolClass));
    $this->post(route('classes.join'), ['join_code' => $oldCode])->assertRedirect(route('classes.show', $this->schoolClass));
    expect($this->schoolClass->memberRecords()->where('user_id', $student->id)->count())->toBe(1);
    expect(ClassMembershipAudit::where('target_id', $student->id)->where('action', 'joined')->count())->toBe(1);
    $this->actingAs($this->owner)->post(route('classes.join'), ['join_code' => $oldCode])->assertRedirect(route('classes.show', $this->schoolClass));
    expect($this->schoolClass->memberRecords()->where('user_id', $this->owner->id)->sole()->role)->toBe('owner');
    $this->post(route('classes.regenerate-code', $this->schoolClass))->assertRedirect(route('classes.show', $this->schoolClass));
    $newCode = $this->schoolClass->fresh()->join_code;
    expect($newCode)->not->toBe($oldCode);
    $outsider = securityTestUser();
    $this->actingAs($outsider)->post(route('classes.join'), ['join_code' => $oldCode])->assertRedirect(route('classes.show', $this->schoolClass))
        ->assertSessionHas('error', 'Invalid join code.');
    $this->assertDatabaseMissing('school_class_members', ['user_id' => $outsider->id]);
    $this->post(route('classes.join'), ['join_code' => $newCode])->assertRedirect(route('classes.show', $this->schoolClass));
    $this->assertDatabaseHas('school_class_members', ['user_id' => $student->id, 'role' => 'student']);
});

test('non-owners cannot regenerate codes even when they can manage class metadata', function (string $kind): void {
    $actor = securityTestUser($kind === 'co_teacher' ? 'teacher' : $kind);
    $this->schoolClass->members()->attach($actor, ['role' => $kind === 'co_teacher' ? 'teacher' : 'student']);
    $this->actingAs($actor)->post(route('classes.regenerate-code', $this->schoolClass))->assertForbidden();
    expect($this->schoolClass->fresh()->join_code)->toBe($this->schoolClass->join_code);
})->with(['co_teacher', 'student', 'admin', 'super_admin']);

test('duplicate member requests never overwrite an existing privileged membership', function (): void {
    $teacher = securityTestUser('teacher');
    $this->actingAs($this->owner)->post(route('classes.members.store', $this->schoolClass), ['user_id' => $teacher->id, 'role' => 'teacher'])
        ->assertRedirect(route('classes.show', $this->schoolClass))->assertSessionHasNoErrors();
    $this->post(route('classes.members.store', $this->schoolClass), ['user_id' => $teacher->id, 'role' => 'student'])
        ->assertRedirect(route('classes.show', $this->schoolClass))->assertSessionHas('error', 'This user is already a class member.');
    expect($this->schoolClass->memberRecords()->where('user_id', $teacher->id)->sole()->role)->toBe('teacher');
    expect(ClassMembershipAudit::where('target_id', $teacher->id)->count())->toBe(1);
});

test('deleted channel slugs remain reserved and never merge old history into new channels', function (): void {
    $this->actingAs($this->owner)->post(route('classes.channels.store', $this->schoolClass), ['name' => 'Project'])
        ->assertRedirect(route('classes.show', $this->schoolClass));
    $old = $this->schoolClass->channels()->where('slug', 'project')->sole();
    $message = $old->messages()->create(['sender_id' => $this->owner->id, 'body' => 'Old channel history']);
    $this->delete(route('classes.channels.destroy', [$this->schoolClass, $old]))->assertRedirect(route('classes.show', $this->schoolClass));
    $this->post(route('classes.channels.store', $this->schoolClass), ['name' => 'Project'])->assertRedirect(route('classes.show', $this->schoolClass));
    $new = $this->schoolClass->channels()->where('slug', 'project-2')->sole();
    expect($new->id)->not->toBe($old->id);
    expect($new->messages()->count())->toBe(0);
    expect($message->fresh()->school_class_channel_id)->toBe($old->id);
    $this->assertSoftDeleted($old);
    $this->delete(route('classes.channels.destroy', [$this->schoolClass, $this->announcement]))
        ->assertSessionHas('error', 'Default channels cannot be deleted.');
    $this->delete(route('classes.members.destroy', [$this->schoolClass, $this->owner]))
        ->assertSessionHas('error', 'The class owner cannot be removed.');
    $this->delete(route('classes.show', $this->schoolClass))->assertStatus(405);
});

test('active owners cannot be suspended demoted promoted away from teaching or deleted', function (string $operation): void {
    $admin = securityTestUser('super_admin');
    $payload = ['name' => $this->owner->name, 'email' => $this->owner->email, 'role_id' => $this->owner->role_id, 'status' => 'active'];
    $this->actingAs($admin);
    if ($operation === 'delete') {
        $response = $this->deleteJson(route('users.destroy', $this->owner));
    } else {
        if ($operation === 'suspend') {
            $payload['status'] = 'suspended';
        } else {
            $payload['role_id'] = Role::where('name', $operation)->sole()->id;
        }
        $response = $this->putJson(route('users.update', $this->owner), $payload);
    }
    $response->assertUnprocessable()->assertJsonValidationErrors('user');
    expect($this->owner->fresh()->status)->toBe('active');
    expect($this->owner->fresh()->role_id)->toBe($this->owner->role_id);
    expect($this->owner->fresh()->auth_version)->toBe(0);
    expect($this->owner->fresh()->deleted_at)->toBeNull();
})->with(['suspend', 'student', 'admin', 'delete']);

test('account changes succeed only after all active class ownership has been reassigned', function (): void {
    $admin = securityTestUser('admin');
    $replacement = securityTestUser('teacher');
    app(ClassManagementService::class)->transfer($admin, $this->schoolClass, $replacement->id);
    $this->actingAs($admin)->putJson(route('users.update', $this->owner), [
        'name' => $this->owner->name, 'email' => $this->owner->email,
        'role_id' => Role::where('name', 'student')->sole()->id, 'status' => 'suspended',
    ])->assertRedirect(route('users.index'));
    expect($this->owner->fresh()->status)->toBe('suspended');
    expect($this->schoolClass->memberRecords()->where('role', 'owner')->sole()->user_id)->toBe($replacement->id);
});

test('archived owners may be suspended but restore requires their eligibility first', function (): void {
    $admin = securityTestUser('admin');
    $classes = app(ClassManagementService::class);
    $classes->setArchived($this->owner, $this->schoolClass, true);
    $accounts = app(AccountManagementService::class);
    $payload = ['name' => $this->owner->name, 'email' => $this->owner->email, 'role_id' => $this->owner->role_id, 'status' => 'suspended'];
    $accounts->save($admin, $this->owner, $payload);
    $this->actingAs($admin)->postJson(route('classes.unarchive', $this->schoolClass))->assertUnprocessable()->assertJsonValidationErrors('class');
    expect($this->schoolClass->fresh()->isArchived())->toBeTrue();
    $accounts->save($admin, $this->owner->fresh(), array_replace($payload, ['status' => 'active']));
    $this->post(route('classes.unarchive', $this->schoolClass))->assertRedirect(route('classes.show', $this->schoolClass));
    expect($this->schoolClass->fresh()->isArchived())->toBeFalse();
});

test('deleting archived owners is blocked rather than leaving an unrestorable class', function (): void {
    app(ClassManagementService::class)->setArchived($this->owner, $this->schoolClass, true);
    $this->actingAs(securityTestUser('admin'))->deleteJson(route('users.destroy', $this->owner))->assertUnprocessable()->assertJsonValidationErrors('user');
});

test('class metadata cannot be used to tamper with lifecycle or ownership', function (): void {
    $this->actingAs($this->owner)->patch(route('classes.update', $this->schoolClass), ['name' => 'Renamed', 'description' => 'Updated'])
        ->assertRedirect(route('classes.show', $this->schoolClass));
    expect($this->schoolClass->fresh()->name)->toBe('Renamed');
    $this->patchJson(route('classes.update', $this->schoolClass), ['name' => 'Bypass', 'archived_at' => now()->toISOString(), 'deleted_at' => now()->toISOString()])
        ->assertUnprocessable()->assertJsonValidationErrors(['archived_at', 'deleted_at']);
});

test('cross-class announcement message routes remain scoped', function (): void {
    $other = SchoolClass::create(['name' => 'Unrelated', 'created_by' => $this->owner->id, 'join_code' => 'OTHER123']);
    $this->actingAs($this->owner)->postJson(route('classes.channels.messages.store', [$other, $this->announcement]), ['body' => 'Cross-class post'])->assertNotFound();
    $this->assertDatabaseCount('school_class_channel_messages', 0);
});

test('archive state is rechecked under the mutation lock despite stale route models', function (): void {
    $stale = $this->schoolClass;
    app(ClassManagementService::class)->setArchived($this->owner, $stale, true);
    expect(fn () => app(ClassManagementService::class)->add($this->owner, $stale, securityTestUser()->id, 'student'))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    $this->assertDatabaseCount('school_class_members', 1);
});

test('archive migration rollback cannot silently reactivate retained classes', function (): void {
    app(ClassManagementService::class)->setArchived($this->owner, $this->schoolClass, true);
    $migration = require database_path('migrations/2026_08_31_192548_add_archived_at_to_school_classes_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Restore archived classes explicitly');
    expect($this->schoolClass->fresh()->isArchived())->toBeTrue();
});

test('administrative deletion failure explains the ownership requirement in the account list', function (): void {
    $this->actingAs(securityTestUser('admin'))->from(route('users.index'))->delete(route('users.destroy', $this->owner))
        ->assertRedirect(route('users.index'))->assertSessionHasErrors('user');
    $this->get(route('users.index'))->assertOk()->assertSee('Reassign class ownership before deleting this account');
});

test('first super admin provisioning cannot bypass active class ownership protection', function (): void {
    expect(fn () => app(AccountManagementService::class)->provisionFirstSuperAdmin($this->owner->id, $this->owner->email, false))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => app(AccountManagementService::class)->provisionFirstSuperAdmin($this->owner->id, $this->owner->email, true))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($this->owner->fresh()->role->name)->toBe('teacher');
});

test('connected class clients disable posting on archive without clearing readable history', function (): void {
    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/class-channel-client.cjs'), public_path('js/classes/channel-realtime.js'), 'archive']);
    $process->mustRun();
    expect($process->isSuccessful())->toBeTrue();
});

<?php

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\SchoolClassChannelMessage;
use App\Models\User;
use App\Services\AccountManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function accountTestUser(string $roleName, array $attributes = []): User
{
    $role = Role::withTrashed()->firstOrCreate(['name' => $roleName], ['status' => true]);

    return User::factory()->onboarded()->create(array_merge(['role_id' => $role->id], $attributes));
}

function accountTestPayload(User $target, array $attributes = []): array
{
    return array_merge([
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $target->role_id,
        'status' => 'active',
    ], $attributes);
}

test('account management follows the complete actor and target matrix', function (string $actorRole, string $targetRole): void {
    $actor = accountTestUser($actorRole);
    $target = accountTestUser($targetRole);
    $allowed = in_array($targetRole, match ($actorRole) {
        'super_admin' => ['admin', 'teacher', 'student'],
        'admin' => ['teacher', 'student'],
        default => [],
    }, true);

    $this->actingAs($actor);
    $view = $this->get(route('users.edit', $target));
    $update = $this->putJson(route('users.update', $target), accountTestPayload($target, ['name' => 'Updated account']));

    if ($allowed) {
        $view->assertOk();
        $update->assertRedirect(route('users.index'));
        expect($target->fresh()->name)->toBe('Updated account');
        $this->from(route('users.index'))->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));
        $this->assertSoftDeleted($target);
    } else {
        $view->assertForbidden();
        $update->assertForbidden();
        $this->deleteJson(route('users.destroy', $target))->assertForbidden();
        expect($target->fresh()->name)->toBe($target->name);
        $this->assertNotSoftDeleted($target);
    }
})->with(Role::NAMES)->with(Role::NAMES);

test('administrators can create only their permitted role assignments', function (string $actorRole, string $newRole): void {
    $actor = accountTestUser($actorRole);
    $role = Role::where('name', $newRole)->firstOrFail();
    $allowed = in_array($newRole, Role::manageableNames($actor), true);
    $response = $this->actingAs($actor)->postJson(route('users.store'), [
        'name' => 'New account',
        'email' => 'new-account@example.test',
        'password' => 'User-selected-password-42',
        'password_confirmation' => 'User-selected-password-42',
        'role_id' => $role->id,
        'status' => 'active',
    ]);

    if (in_array($actorRole, [Role::TEACHER, Role::STUDENT], true)) {
        $response->assertForbidden();
    } elseif (! $allowed) {
        $response->assertUnprocessable()->assertJsonValidationErrors('role_id');
    } else {
        $response->assertRedirect(route('users.index'));
        $created = User::where('email', 'new-account@example.test')->firstOrFail();
        expect($created->role_id)->toBe($role->id);
        expect(Hash::check('User-selected-password-42', $created->password))->toBeTrue();
        expect($created->google2fa_enabled)->toBeFalse();
        expect((bool) $created->must_change_password)->toBeTrue();
    }

    if (! $allowed) {
        $this->assertDatabaseMissing('users', ['email' => 'new-account@example.test']);
    }
})->with(Role::NAMES)->with(Role::NAMES);

test('forged privileged and legacy role assignments cannot be submitted', function (string $roleName): void {
    $actor = accountTestUser(Role::ADMIN);
    $target = accountTestUser(Role::STUDENT);
    $role = Role::firstOrCreate(['name' => $roleName], ['status' => true]);
    $this->actingAs($actor)->putJson(route('users.update', $target), accountTestPayload($target, ['role_id' => $role->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('role_id');
    expect($target->fresh()->role_id)->toBe($target->role_id);
})->with(['admin', 'super_admin', 'manager']);

test('sensitive fields cannot be mass assigned through account forms', function (string $field, mixed $value): void {
    $actor = accountTestUser(Role::SUPER_ADMIN);
    $target = accountTestUser(Role::STUDENT);
    $before = $target->fresh()->getAttributes();
    $this->actingAs($actor)->putJson(route('users.update', $target), accountTestPayload($target, [$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($target->fresh()->getAttributes())->toBe($before);
})->with([
    ['google2fa_secret', 'FORGEDSECRET'],
    ['google2fa_enabled', true],
    ['two_factor_secret_encrypted', 'forged-ciphertext'],
    ['auth_version', 999],
    ['two_factor_recovery_token_hash', 'forged-digest'],
    ['must_change_password', false],
    ['deleted_at', '2026-01-01'],
    ['password', 'attacker-password'],
    ['remember_token', 'forged-token'],
    ['email_verified_at', '2026-01-01'],
    ['id', 99999],
]);

test('all two factor management routes enforce target boundaries', function (string $targetRole, string $action): void {
    $actor = accountTestUser(Role::ADMIN);
    $target = accountTestUser($targetRole);
    $secret = $target->google2fa_secret;
    $this->actingAs($actor)->postJson(route('users.two-factor.'.$action, $target), ['code' => '123456'])
        ->assertForbidden();
    expect($target->fresh()->google2fa_secret)->toBe($secret);
})->with(['admin', 'super_admin', 'manager'])->with(['generate', 'enable', 'disable']);

test('the last active super admin cannot be suspended demoted or deleted', function (string $actorRole): void {
    $superAdmin = accountTestUser(Role::SUPER_ADMIN);
    $actor = $actorRole === Role::SUPER_ADMIN ? $superAdmin : accountTestUser($actorRole);
    $this->actingAs($actor);
    $this->putJson(route('users.update', $superAdmin), accountTestPayload($superAdmin, ['status' => 'suspended']))->assertForbidden();
    $this->putJson(route('users.update', $superAdmin), accountTestPayload($superAdmin, [
        'role_id' => Role::where('name', Role::STUDENT)->firstOrFail()->id,
    ]))->assertForbidden();
    $this->deleteJson(route('users.destroy', $superAdmin))->assertForbidden();
    expect($superAdmin->fresh()->status)->toBe('active');
    expect($superAdmin->fresh()->role->name)->toBe(Role::SUPER_ADMIN);
})->with(Role::NAMES);

test('role definition mutation routes are unavailable even to super admin', function (): void {
    $actor = accountTestUser(Role::SUPER_ADMIN);
    $role = Role::where('name', Role::STUDENT)->firstOrFail();
    $before = Role::withTrashed()->get()->toArray();
    $this->actingAs($actor);
    $this->get('/roles/create')->assertNotFound();
    $this->get('/roles/'.$role->id.'/edit')->assertNotFound();
    $this->postJson('/roles', ['name' => 'god'])->assertStatus(405);
    $this->putJson('/roles/'.$role->id, ['name' => 'god'])->assertNotFound();
    $this->deleteJson('/roles/'.$role->id)->assertNotFound();
    $this->get(route('roles.index'))->assertOk()->assertSee('Roles are fixed');
    expect(Role::withTrashed()->get()->toArray())->toBe($before);
});

test('legacy accounts are preserved but cannot grant institution privileges', function (): void {
    $legacy = accountTestUser('manager');
    $actor = accountTestUser(Role::SUPER_ADMIN);
    $this->actingAs($legacy)->get(route('users.index'))->assertForbidden();
    $this->actingAs($actor)->putJson(route('users.update', $legacy), accountTestPayload($legacy))->assertForbidden();
    $this->deleteJson(route('users.destroy', $legacy))->assertForbidden();
    expect($legacy->fresh()->role->name)->toBe('manager');
});

test('stale actor and target role snapshots are rechecked inside the account lock', function (): void {
    $actor = accountTestUser(Role::ADMIN);
    $target = accountTestUser(Role::STUDENT);
    $actor->load('role');
    User::whereKey($actor->id)->update(['role_id' => $target->role_id]);
    expect(fn () => app(AccountManagementService::class)->save($actor, $target, accountTestPayload($target)))
        ->toThrow(AuthorizationException::class);

    $actor = accountTestUser(Role::ADMIN);
    $target->load('role');
    User::whereKey($target->id)->update(['role_id' => Role::where('name', Role::SUPER_ADMIN)->firstOrFail()->id]);
    expect(fn () => app(AccountManagementService::class)->delete($actor, $target))
        ->toThrow(AuthorizationException::class);
    $this->assertNotSoftDeleted($target);
});

test('disabled accounts and disabled roles cannot manage users', function (): void {
    $actor = accountTestUser(Role::ADMIN, ['status' => 'suspended']);
    $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
    $actor->update(['status' => 'active']);
    $actor->role->update(['status' => false]);
    $this->actingAs($actor->fresh())->get(route('users.index'))->assertForbidden();
});

test('suspension blocks existing web sessions and login without changing credentials', function (): void {
    $user = accountTestUser(Role::STUDENT, ['status' => 'suspended']);
    $this->actingAs($user)->get(route('classes.index'))->assertForbidden();
    auth()->logout();
    $this->from(route('login'))->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('account forms and navigation expose only permitted actions', function (): void {
    $actor = accountTestUser(Role::ADMIN);
    $student = accountTestUser(Role::STUDENT);
    $peer = accountTestUser(Role::ADMIN);
    $super = accountTestUser(Role::SUPER_ADMIN);
    $this->actingAs($actor)->get(route('users.create'))->assertOk()->assertViewHas('roles', function ($roles): bool {
        return $roles->pluck('name')->all() === ['student', 'teacher'];
    });
    $this->get(route('users.index'))->assertOk()->assertSee($student->email)
        ->assertDontSee($peer->email)->assertDontSee($super->email);
    $this->actingAs($student)->get(route('classes.index'))->assertOk()->assertDontSee('User Management');
});

test('institution roles do not bypass class messages or private chat membership', function (string $roleName): void {
    $owner = accountTestUser(Role::TEACHER);
    $outsider = accountTestUser($roleName);
    $schoolClass = SchoolClass::create([
        'name' => 'Private class', 'created_by' => $owner->id, 'join_code' => Str::upper(Str::random(10)),
    ]);
    $schoolClass->members()->attach($owner, ['role' => 'owner', 'joined_at' => now()]);
    $channel = SchoolClassChannel::create([
        'school_class_id' => $schoolClass->id, 'name' => 'General', 'slug' => 'general',
        'created_by' => $owner->id, 'is_default' => true, 'sort_order' => 0,
    ]);
    $message = SchoolClassChannelMessage::create([
        'school_class_channel_id' => $channel->id, 'sender_id' => $owner->id, 'body' => 'Private history',
    ]);
    $room = ChatRoom::create(['type' => 'group', 'name' => 'Private room', 'created_by' => $owner->id]);
    $room->members()->attach($owner, ['role' => 'admin', 'joined_at' => now()]);

    $this->actingAs($outsider);
    $this->get(route('classes.channels.show', [$schoolClass, $channel]))->assertForbidden();
    $this->postJson(route('classes.channels.messages.store', [$schoolClass, $channel]), ['body' => 'intrusion'])->assertForbidden();
    $this->get(route('chat.rooms.show', $room))->assertForbidden();

    $callbacks = Broadcast::getChannels();
    expect($callbacks['school-class.channel.{channelId}']($outsider, $channel->id))->toBeFalse();
    expect($callbacks['chat.room.{roomId}']($outsider, $room->id))->toBeFalse();
    expect($callbacks['school-class.channel.{channelId}']($owner, $channel->id))->toBeFalse();
    $membershipId = $schoolClass->memberRecords()->where('user_id', $owner->id)->value('id');
    expect($callbacks['school-class.membership.{membershipId}']($owner, (string) $membershipId))->toBeTrue();
    expect($callbacks['school-class.membership.{membershipId}']($outsider, (string) $membershipId))->toBeFalse();
    expect($message->fresh()->body)->toBe('Private history');
})->with(Role::NAMES);

test('deleting an account preserves class message history', function (): void {
    $actor = accountTestUser(Role::ADMIN);
    $student = accountTestUser(Role::STUDENT);
    $schoolClass = SchoolClass::create(['name' => 'History', 'created_by' => $student->id, 'join_code' => 'HISTORY1']);
    $channel = SchoolClassChannel::create([
        'school_class_id' => $schoolClass->id, 'name' => 'General', 'slug' => 'general', 'created_by' => $student->id,
    ]);
    $message = SchoolClassChannelMessage::create([
        'school_class_channel_id' => $channel->id, 'sender_id' => $student->id, 'body' => 'Keep this message',
    ]);
    $room = ChatRoom::create(['type' => 'group', 'name' => 'Retained chat', 'created_by' => $student->id]);
    $chatMessage = Message::create([
        'room_id' => $room->id, 'sender_id' => $student->id, 'message_type' => 'text', 'body' => 'Keep chat history',
    ]);
    $this->actingAs($actor)->from(route('users.index'))->delete(route('users.destroy', $student))->assertRedirect(route('users.index'));
    $this->assertSoftDeleted($student);
    expect($message->fresh()->body)->toBe('Keep this message');
    expect($message->fresh()->sender_id)->toBe($student->id);
    expect($message->fresh()->sender->id)->toBe($student->id);
    expect($chatMessage->fresh()->body)->toBe('Keep chat history');
    expect($chatMessage->fresh()->sender->id)->toBe($student->id);
});

test('super admin reaches the administrative dashboard without a private chat bypass', function (): void {
    $superAdmin = accountTestUser(Role::SUPER_ADMIN);
    $this->actingAs($superAdmin)->get(route('home'))->assertOk()->assertViewIs('home');
    $this->get(route('users.create'))->assertOk()->assertViewHas('roles', function ($roles): bool {
        return $roles->pluck('name')->all() === ['admin', 'student', 'teacher'];
    });
});

test('permitted account role changes and suspension take effect on the server', function (): void {
    $superAdmin = accountTestUser(Role::SUPER_ADMIN);
    $target = accountTestUser(Role::STUDENT);
    $adminRole = Role::where('name', Role::ADMIN)->firstOrFail();
    $this->actingAs($superAdmin)->putJson(route('users.update', $target), accountTestPayload($target, ['role_id' => $adminRole->id]))
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->role_id)->toBe($adminRole->id);

    $student = accountTestUser(Role::STUDENT);
    $teacherRole = Role::where('name', Role::TEACHER)->firstOrFail();
    $this->actingAs($target->fresh())->withSession(['auth_version' => $target->fresh()->auth_version])->putJson(route('users.update', $student), accountTestPayload($student, [
        'role_id' => $teacherRole->id, 'status' => 'suspended',
    ]))->assertRedirect(route('users.index'));
    expect($student->fresh()->role_id)->toBe($teacherRole->id);
    expect($student->fresh()->status)->toBe('suspended');
    $this->actingAs($student->fresh())->get(route('classes.index'))->assertForbidden();
});

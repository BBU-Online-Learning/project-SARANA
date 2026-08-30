<?php

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// This step adds tests for:
// - Adding class members.
// - Removing class members.
// - Preventing removal of the class owner.
// - Preventing deletion of default channels.
// - Allowing deletion of non-default channels.

function createManagementTestUser(string $roleName): User
{
    $role = Role::create([
        'name' => $roleName,
        'description' => $roleName.' management test role',
        'status' => true,
    ]);

    return User::factory()->onboarded()->create([
        'role_id' => $role->id,
    ]);
}

function createManagementTestClass(User $owner): SchoolClass
{
    $schoolClass = SchoolClass::create([
        'name' => 'Management Test Class',
        'join_code' => strtoupper(fake()->unique()->bothify('MANAGE###')),
        'description' => 'Class used for management tests.',
        'created_by' => $owner->id,
    ]);

    $schoolClass->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    return $schoolClass;
}

function addManagementClassMember(
    SchoolClass $schoolClass,
    User $user,
    string $role = 'student'
): void {
    $schoolClass->members()->attach($user->id, [
        'role' => $role,
        'joined_at' => now(),
    ]);
}

test('a teacher can add a member to a class', function () {
    $teacher = createManagementTestUser('teacher');
    $student = createManagementTestUser('student');
    $schoolClass = createManagementTestClass($teacher);

    $response = $this
        ->actingAs($teacher)
        ->from(route('classes.show', $schoolClass))
        ->post(route('classes.members.store', $schoolClass), [
            'user_id' => $student->id,
            'role' => 'student',
        ]);

    $response->assertRedirect(route('classes.show', $schoolClass));
    $response->assertSessionHasNoErrors();

    expect(
        $schoolClass->members()
            ->where('users.id', $student->id)
            ->exists()
    )->toBeTrue();

    $this->assertDatabaseHas('school_class_members', [
        'school_class_id' => $schoolClass->id,
        'user_id' => $student->id,
        'role' => 'student',
    ]);
});

test('a teacher can remove a student from a class', function () {
    $teacher = createManagementTestUser('teacher');
    $student = createManagementTestUser('student');
    $schoolClass = createManagementTestClass($teacher);

    addManagementClassMember($schoolClass, $student);

    $response = $this
        ->actingAs($teacher)
        ->from(route('classes.show', $schoolClass))
        ->delete(route('classes.members.destroy', [$schoolClass, $student]));

    $response->assertRedirect(route('classes.show', $schoolClass));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('school_class_members', [
        'school_class_id' => $schoolClass->id,
        'user_id' => $student->id,
    ]);
});

test('the class owner cannot be removed', function () {
    $teacher = createManagementTestUser('teacher');
    $schoolClass = createManagementTestClass($teacher);

    $response = $this
        ->actingAs($teacher)
        ->from(route('classes.show', $schoolClass))
        ->delete(route('classes.members.destroy', [$schoolClass, $teacher]));

    $response->assertRedirect(route('classes.show', $schoolClass));
    $response->assertSessionHas('error', 'The class owner cannot be removed.');

    $this->assertDatabaseHas('school_class_members', [
        'school_class_id' => $schoolClass->id,
        'user_id' => $teacher->id,
        'role' => 'owner',
    ]);
});

test('a default channel cannot be deleted', function () {
    $teacher = createManagementTestUser('teacher');
    $schoolClass = createManagementTestClass($teacher);

    $defaultChannel = SchoolClassChannel::create([
        'school_class_id' => $schoolClass->id,
        'name' => 'General',
        'slug' => 'general',
        'created_by' => $teacher->id,
        'description' => 'Default class channel.',
        'is_default' => true,
        'sort_order' => 1,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->from(route('classes.show', $schoolClass))
        ->delete(route('classes.channels.destroy', [$schoolClass, $defaultChannel]));

    $response->assertRedirect(route('classes.show', $schoolClass));
    $response->assertSessionHas('error', 'Default channels cannot be deleted.');

    $this->assertDatabaseHas('school_class_channels', [
        'id' => $defaultChannel->id,
        'is_default' => true,
        'deleted_at' => null,
    ]);
});

test('a non-default channel can be deleted', function () {
    $teacher = createManagementTestUser('teacher');
    $schoolClass = createManagementTestClass($teacher);

    $customChannel = SchoolClassChannel::create([
        'school_class_id' => $schoolClass->id,
        'name' => 'Project',
        'slug' => 'project',
        'created_by' => $teacher->id,
        'description' => 'Custom class channel.',
        'is_default' => false,
        'sort_order' => 2,
    ]);

    $response = $this
        ->actingAs($teacher)
        ->from(route('classes.show', $schoolClass))
        ->delete(route('classes.channels.destroy', [$schoolClass, $customChannel]));

    $response->assertRedirect(route('classes.show', $schoolClass));
    $response->assertSessionHas('success', 'Channel deleted successfully.');

    $this->assertSoftDeleted('school_class_channels', [
        'id' => $customChannel->id,
    ]);
});

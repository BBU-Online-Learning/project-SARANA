<?php

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// This step creates automated tests for:
// - Class members accessing their class.
// - Users outside a class being blocked.
// - Students being blocked from management actions.
// - Teachers being allowed to manage their class.

function createClassPermissionUser(string $roleName): User
{
    $role = Role::create([
        'name' => $roleName,
        'description' => $roleName.' test role',
        'status' => true,
    ]);

    return User::factory()->onboarded()->create([
        'role_id' => $role->id,
    ]);
}

function createPermissionTestClass(User $owner): SchoolClass
{
    $schoolClass = SchoolClass::create([
        'name' => 'Permission Test Class',
        'join_code' => strtoupper(fake()->unique()->bothify('CLASS###')),
        'description' => 'Class used for permission tests.',
        'created_by' => $owner->id,
    ]);

    $schoolClass->members()->attach($owner->id, [
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    return $schoolClass;
}

test('a class member can open the class page', function () {
    $owner = createClassPermissionUser('teacher');
    $schoolClass = createPermissionTestClass($owner);

    $response = $this
        ->actingAs($owner)
        ->get(route('classes.show', $schoolClass));

    $response->assertOk();
});

test('a user outside the class cannot open the class page', function () {
    $owner = createClassPermissionUser('teacher');
    $outsider = createClassPermissionUser('student');
    $schoolClass = createPermissionTestClass($owner);

    $response = $this
        ->actingAs($outsider)
        ->get(route('classes.show', $schoolClass));

    $response->assertForbidden();
});

test('a student cannot create a channel', function () {
    $owner = createClassPermissionUser('teacher');
    $student = createClassPermissionUser('student');
    $schoolClass = createPermissionTestClass($owner);

    $schoolClass->members()->attach($student->id, [
        'role' => 'student',
        'joined_at' => now(),
    ]);

    $response = $this
        ->actingAs($student)
        ->post(route('classes.channels.store', $schoolClass), [
            'name' => 'Unauthorized Channel',
            'description' => 'This channel should not be created.',
        ]);

    $response->assertForbidden();

    expect(
        SchoolClassChannel::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('name', 'Unauthorized Channel')
            ->exists()
    )->toBeFalse();
});

test('a teacher can create a channel in their class', function () {
    $teacher = createClassPermissionUser('teacher');
    $schoolClass = createPermissionTestClass($teacher);

    $response = $this
        ->actingAs($teacher)
        ->from(route('classes.show', $schoolClass))
        ->post(route('classes.channels.store', $schoolClass), [
            'name' => 'Teacher Channel',
            'description' => 'Created by a teacher.',
        ]);

    $response->assertRedirect(route('classes.show', $schoolClass));
    $response->assertSessionHasNoErrors();

    expect(
        SchoolClassChannel::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('name', 'Teacher Channel')
            ->exists()
    )->toBeTrue();
});

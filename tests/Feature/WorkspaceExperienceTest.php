<?php

use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('class search and status filters preserve membership boundaries', function (string $role): void {
    $user = securityTestUser($role);
    $teacher = securityTestUser('teacher');
    $active = SchoolClass::create(['name' => 'Algebra class', 'description' => 'Equations', 'created_by' => $teacher->id, 'join_code' => Str::random(8)]);
    $archived = SchoolClass::create(['name' => 'Previous algebra', 'created_by' => $teacher->id, 'join_code' => Str::random(8)]);
    $archived->forceFill(['archived_at' => now()])->save();
    $outside = SchoolClass::create(['name' => 'Private algebra', 'description' => 'Equations', 'created_by' => $teacher->id, 'join_code' => Str::random(8)]);
    foreach ([$active, $archived] as $schoolClass) {
        $schoolClass->members()->attach($user, ['role' => $role]);
    }

    $this->actingAs($user)->get(route('classes.index', ['search' => 'algebra', 'status' => 'active']))
        ->assertOk()->assertInertia(fn ($page) => $page->component('Classes/Index')->where('classes.0.id', $active->id)->has('classes', 1))
        ->assertDontSee($outside->name)->assertDontSee($archived->name);
    $this->get(route('classes.index', ['status' => 'archived']))->assertOk()
        ->assertInertia(fn ($page) => $page->where('classes.0.id', $archived->id)->has('classes', 1));
    $this->get(route('classes.index', ['search' => 'Equations']))->assertOk()
        ->assertInertia(fn ($page) => $page->where('classes.0.id', $active->id)->has('classes', 1));
    $this->get(route('classes.index', ['search' => 'missing']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('classes', 0)->where('filters.search', 'missing'));
})->with(['teacher', 'student']);

test('invalid class filters are rejected safely', function (): void {
    $this->actingAs(securityTestUser())->getJson(route('classes.index', ['search' => ['bad'], 'status' => 'unknown']))
        ->assertUnprocessable()->assertJsonValidationErrors(['search', 'status']);
});

test('class directory sends only authorized management details and supports partial visits', function (): void {
    $teacher = securityTestUser('teacher');
    $student = securityTestUser('student');
    $schoolClass = SchoolClass::create(['name' => 'Protected class', 'created_by' => $teacher->id, 'join_code' => 'SAFE1234']);
    $schoolClass->members()->attach($teacher, ['role' => 'owner']);
    $schoolClass->members()->attach($student, ['role' => 'student']);

    $studentPage = $this->actingAs($student)->get(route('classes.index'))->assertOk();
    expect($studentPage->inertiaProps('classes.0.joinCode'))->toBeNull()
        ->and($studentPage->inertiaProps('classes.0.canManage'))->toBeFalse();

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $studentPage->inertiaPage()['version'],
        'X-Inertia-Partial-Component' => 'Classes/Index',
        'X-Inertia-Partial-Data' => 'classes,filters,summary',
    ])->get(route('classes.index', ['status' => 'active']))
        ->assertOk()->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('props.classes.0.id', $schoolClass->id)
        ->assertJsonPath('props.filters.status', 'active')
        ->assertJsonMissingPath('props.eligibleTeachers');

    $this->flushHeaders();
    $teacherPage = $this->actingAs($teacher)->get(route('classes.index'))->assertOk();
    expect($teacherPage->inertiaProps('classes.0.joinCode'))->toBe('SAFE1234')
        ->and($teacherPage->inertiaProps('classes.0.canManage'))->toBeTrue();
    $schoolClass->forceFill(['archived_at' => now()])->save();
    $archivedPage = $this->get(route('classes.index'))->assertOk();
    expect($archivedPage->inertiaProps('classes.0.joinCode'))->toBeNull()
        ->and($archivedPage->inertiaProps('classes.0.canManage'))->toBeTrue();
});

test('class forms preserve invalid input and open the relevant disclosure', function (): void {
    $teacher = securityTestUser('teacher');
    $this->actingAs($teacher)->from(route('classes.index'))->post(route('classes.store'), ['name' => '', 'description' => 'Keep my notes'])
        ->assertSessionHasErrors('name');
    $this->get(route('classes.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('oldInput.description', 'Keep my notes')->has('errors.name'));
});

test('class leaving requires confirmation and dashboard filters target their status', function (): void {
    $teacher = securityTestUser('teacher');
    $schoolClass = SchoolClass::create(['name' => 'My class', 'created_by' => $teacher->id, 'join_code' => Str::random(8)]);
    $schoolClass->members()->attach($teacher, ['role' => 'teacher']);
    $this->actingAs($teacher)->get(route('classes.show', $schoolClass))->assertOk()
        ->assertSee('Class options')->assertSee('data-confirm-title="Leave this class?"', false);
    $this->get(route('home'))->assertOk()->assertSee(route('classes.index', ['status' => 'archived']))
        ->assertSee(route('classes.index', ['status' => 'active']));
});

test('class filters, search and calendar expose in-place navigation', function (): void {
    $this->actingAs(securityTestUser('student'));

    $this->get(route('classes.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Classes/Index')->where('filters.status', 'all'));
    $this->get(route('search.index'))->assertOk()->assertSee('data-workspace-nav-form', false);
    $this->get(route('calendar.index'))->assertOk()->assertSee('data-workspace-nav', false);
});

test('login labels and password visibility are accessible', function (): void {
    $this->get(route('login'))->assertOk()->assertSee('for="email"', false)->assertSee('for="password"', false)
        ->assertSee('aria-controls="password"', false)->assertDontSee('Deverlop');
});

test('phone interactions avoid accidental zoom without restricting intentional zoom', function (): void {
    $stylesheet = file_get_contents(public_path('css/touch-zoom.css'));

    expect($stylesheet)->toContain('@media (pointer: coarse)')
        ->toContain('touch-action: manipulation')
        ->toContain('font-size: max(16px, 1em)')
        ->not->toContain('touch-action: none');

    $this->get(route('login'))->assertOk()
        ->assertSee(asset('css/touch-zoom.css'), false)
        ->assertDontSee('user-scalable=no');

    $this->actingAs(securityTestUser('student'))->get(route('home'))->assertOk()
        ->assertSee(asset('css/touch-zoom.css'), false)
        ->assertDontSee('user-scalable=no');
});

test('multiline keyboard and password visibility interactions work', function (): void {
    $process = new Process(['node', base_path('tests/workspace-experience-client.cjs')], base_path());
    $process->mustRun();
    expect($process->getOutput())->toContain('checks passed');
});

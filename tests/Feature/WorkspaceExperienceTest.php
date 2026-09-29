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
        ->assertOk()->assertViewHas('classes', fn ($classes): bool => $classes->modelKeys() === [$active->id])
        ->assertDontSee($outside->name)->assertDontSee($archived->name);
    $this->get(route('classes.index', ['status' => 'archived']))->assertOk()
        ->assertViewHas('classes', fn ($classes): bool => $classes->modelKeys() === [$archived->id]);
    $this->get(route('classes.index', ['search' => 'Equations']))->assertOk()
        ->assertViewHas('classes', fn ($classes): bool => $classes->modelKeys() === [$active->id]);
    $this->get(route('classes.index', ['search' => 'missing']))->assertOk()->assertSee('No classes match these filters');
})->with(['teacher', 'student']);

test('invalid class filters are rejected safely', function (): void {
    $this->actingAs(securityTestUser())->getJson(route('classes.index', ['search' => ['bad'], 'status' => 'unknown']))
        ->assertUnprocessable()->assertJsonValidationErrors(['search', 'status']);
});

test('class forms preserve invalid input and open the relevant disclosure', function (): void {
    $teacher = securityTestUser('teacher');
    $this->actingAs($teacher)->from(route('classes.index'))->post(route('classes.store'), ['name' => '', 'description' => 'Keep my notes'])
        ->assertSessionHasErrors('name');
    $this->get(route('classes.index'))->assertOk()->assertSee('Keep my notes')
        ->assertSee('id="create-class"  open', false)->assertSee('data-pending-label="Creating…"', false);
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

    $this->get(route('classes.index'))->assertOk()->assertSee('data-workspace-nav-form', false)
        ->assertSee('data-workspace-nav', false);
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

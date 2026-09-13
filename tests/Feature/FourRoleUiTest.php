<?php

use App\Models\ChatRoom;
use App\Models\Role;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('workspace JavaScript handles mobile navigation loading and creation failures', function (): void {
    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/workspace-client.cjs')], base_path());
    $process->mustRun();
    expect($process->getOutput())->toContain('checks passed');
});

beforeEach(function (): void {
    Storage::fake('public');
});

test('authentication navigation uses live routes without bypassing onboarding', function (): void {
    $this->get(route('login'))->assertOk()->assertDontSee('href="index.html"', false);
    $user = securityTestUser();
    $this->actingAs($user)->get(route('password.change'))->assertOk()->assertSee('Back to my profile')
        ->assertSee(route('logout'))->assertDontSee('href="'.route('password.request').'"', false);
    $user->update(['must_change_password' => true]);
    $this->get(route('password.change'))->assertOk()->assertDontSee('Back to my profile');
});

test('each role sees real permitted counts without private conversation or class content', function (string $role): void {
    $user = securityTestUser($role);
    $other = securityTestUser('teacher');
    $own = SchoolClass::create(['name' => 'My permitted class', 'created_by' => $other->id, 'join_code' => Str::random(8)]);
    $own->members()->attach($user, ['role' => $role === 'teacher' ? 'teacher' : 'student']);
    $archived = SchoolClass::create(['name' => 'My archived class', 'created_by' => $other->id, 'join_code' => Str::random(8)]);
    $archived->forceFill(['archived_at' => now()])->save();
    $archived->members()->attach($user, ['role' => 'student']);
    $outside = SchoolClass::create(['name' => 'Unrelated class metadata', 'created_by' => $other->id, 'join_code' => Str::random(8)]);
    $deleted = SchoolClass::create(['name' => 'Deleted class', 'created_by' => $other->id, 'join_code' => Str::random(8)]);
    $deleted->members()->attach($user, ['role' => 'student']);
    $deleted->delete();
    foreach (['direct', 'group'] as $type) {
        $room = ChatRoom::create(['type' => $type, 'created_by' => $user->id]);
        $room->members()->attach($user);
        $private = ChatRoom::create(['type' => $type, 'created_by' => $other->id, 'name' => 'Private outsider conversation']);
        $private->members()->attach($other);
        $private->messages()->create(['sender_id' => $other->id, 'body' => 'Secret outsider message']);
    }
    $admin = in_array($role, ['super_admin', 'admin'], true);
    $response = $this->actingAs($user)->get(route('home'))->assertOk()->assertViewIs('home')
        ->assertViewHas('classCounts', ['active' => $admin ? 2 : 1, 'archived' => 1])
        ->assertViewHas('conversationCounts', ['direct' => 1, 'group' => 1])
        ->assertSee('My permitted class')->assertDontSee('Deleted class')
        ->assertDontSee('Private outsider conversation')->assertDontSee('Secret outsider message');
    $response->assertViewHas('accountCounts', function (array $counts) use ($user): bool {
        return array_keys($counts) === Role::manageableNames($user)
            && collect($counts)->every(fn ($count, $name) => $count === \App\Models\User::where('id', '!=', $user->id)->whereHas('role', fn ($query) => $query->where('name', $name))->count());
    });
    if ($admin) {
        $response->assertSee($outside->name)->assertSee(route('users.index'));
    } else {
        $response->assertDontSee($outside->name)->assertDontSee(route('users.index'));
    }
    $response->assertHeader('Cache-Control', 'no-store, private');
})->with(Role::NAMES);

test('shared navigation has permitted real links and no excluded template controls', function (string $role): void {
    $this->actingAs(securityTestUser($role));
    foreach (['home', 'profile.edit', 'chat.index', 'classes.index'] as $route) {
        $response = $this->get(route($route))->assertOk()
            ->assertSee(route('home'))->assertSee(route('classes.index'))->assertSee(route('chat.index'))->assertSee(route('profile.edit'))->assertSee(route('logout'))
            ->assertDontSee('apps-calendar.html')->assertDontSee('Products &amp; Inventory', false)->assertDontSee('dashboard-sales.js')
            ->assertDontSee('href="#"', false)->assertDontSee('aria-label="Calls"', false)->assertDontSee('aria-label="Calendar"', false);
        if (in_array($role, ['admin', 'super_admin'], true)) {
            $response->assertSee(route('users.index'))->assertSee(route('roles.index'));
        } else {
            $response->assertDontSee(route('users.index'))->assertDontSee(route('roles.index'));
        }
        if ($directory = getenv('UI_PREVIEW_DIRECTORY')) {
            if (! preg_match('/^elearning_ui_[a-f0-9]{16}$/', basename($directory)) || realpath(dirname($directory)) !== realpath(sys_get_temp_dir())) {
                throw new RuntimeException('UI previews require an isolated temporary directory.');
            }
            if (! is_dir($directory)) {
                mkdir($directory);
            }
            $html = preg_replace('/<script[^>]*type="module"[^>]*>.*?<\/script>/s', '', $response->getContent());
            file_put_contents($directory.'/'.$role.'-'.str_replace('.', '-', $route).'.html', $html);
        }
    }
    $chat = $this->get(route('chat.index'))->assertOk()
        ->assertSee('data-shell-toggle', false)->assertSee('data-chat-list', false)->assertSee('chat-load-status')
        ->assertSee('workspace-chat')->assertSee('workspace-role-badge')
        ->assertSee('id="chat-conversations"', false)
        ->assertDontSee('id="chat-navigation"', false)->assertDontSee('data-chat-menu', false);
    expect(substr_count($chat->getContent(), 'id="workspace-navigation"'))->toBe(1)
        ->and(substr_count($chat->getContent(), '<main '))->toBe(1)
        ->and(substr_count($chat->getContent(), asset('css/teamstyle.css')))->toBe(1);
})->with(Role::NAMES);

test('empty dashboards are genuine zero states for teachers and students', function (string $role): void {
    $this->actingAs(securityTestUser($role))->get(route('home'))->assertOk()
        ->assertViewHas('classCounts', ['active' => 0, 'archived' => 0])
        ->assertViewHas('conversationCounts', ['direct' => 0, 'group' => 0])
        ->assertSee('No classes yet')->assertSee('No conversations yet');
})->with(['teacher', 'student']);

test('all four roles may update only their own personal details', function (string $role): void {
    $user = securityTestUser($role);
    $other = securityTestUser();
    $before = $user->getAttributes();
    $otherBefore = $other->getAttributes();
    $this->actingAs($user)->patch(route('profile.update'), ['name' => '  New Name  ', 'phone' => '+855 (12) 345-678'])
        ->assertRedirect(route('profile.edit'))->assertSessionHas('success', 'Profile updated successfully.');
    expect($user->fresh()->name)->toBe('New Name')->and($user->fresh()->phone)->toBe('+855 (12) 345-678');
    foreach (array_diff(array_keys($before), ['name', 'phone', 'updated_at']) as $field) {
        expect($user->fresh()->getRawOriginal($field))->toBe($before[$field]);
    }
    expect($other->fresh()->getAttributes())->toBe($otherBefore);
    $this->patchJson('/profile/'.$other->id, ['name' => 'Wrong target'])->assertNotFound();
})->with(Role::NAMES);

test('profile rejects every nonallowlisted field atomically', function (string $field): void {
    $user = securityTestUser();
    $before = $user->getAttributes();
    $this->actingAs($user)->patchJson(route('profile.update'), ['name' => 'Must not save', $field => 'tampered'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($user->fresh()->getAttributes())->toBe($before);
})->with(['id', 'user_id', 'role_id', 'role', 'status', 'email', 'email_verified_at', 'password', 'google2fa_secret', 'google2fa_enabled',
    'two_factor_secret_encrypted', 'two_factor_last_used_step', 'must_change_password', 'auth_version', 'remember_token', 'deleted_at',
    'two_factor_recovery_token_hash', 'recovery_requested_by', 'profile', 'unknown']);

test('profile handles malformed fields without partial saves and renders validation feedback', function (array $data, string $field): void {
    $user = securityTestUser();
    $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), $data)
        ->assertRedirect(route('profile.edit'))->assertSessionHasErrors($field);
    $this->get(route('profile.edit'))->assertOk()->assertSee('Your profile was not saved.');
    expect($user->fresh()->name)->toBe($user->name);
})->with([
    [['name' => '   '], 'name'], [['name' => ['bad']], 'name'], [['name' => str_repeat('x', 256)], 'name'],
    [['name' => 'Valid', 'phone' => ['bad']], 'phone'], [['name' => 'Valid', 'phone' => '<script>'], 'phone'],
]);

test('profile photos use safe names and replace application managed images', function (): void {
    $user = securityTestUser('student', ['profile' => '/storage/images/users/old.png']);
    Storage::disk('public')->put('images/users/old.png', 'preserved');
    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'photo' => UploadedFile::fake()->image('personal.png')])
        ->assertRedirect(route('profile.edit'));
    expect($user->fresh()->profile)->not->toBe($user->profile)->not->toContain('personal.png');
    Storage::disk('public')->assertMissing('images/users/old.png');
    expect(Storage::disk('public')->allFiles('images/users'))->toHaveCount(1);
    $this->get(route('profile.edit'))->assertOk()->assertSee('Your current profile photo')->assertSee(route('password.change'));
});

test('profile rejects unsafe oversized and nonimage uploads', function (): void {
    $user = securityTestUser();
    $this->actingAs($user);
    foreach ([UploadedFile::fake()->create('attack.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('huge.png')->size(2049),
        UploadedFile::fake()->create('fake.jpg', 1, 'text/plain')] as $file) {
        $this->patchJson(route('profile.update'), ['name' => $user->name, 'photo' => $file])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('profile errors never flash submitted security fields and personal text is escaped', function (): void {
    $user = securityTestUser();
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Valid', 'google2fa_secret' => 'never-flash-this'])
        ->assertRedirect(route('profile.edit'))->assertSessionHasErrors('google2fa_secret')
        ->assertSessionMissing('_old_input.google2fa_secret');
    $this->get(route('profile.edit'))->assertDontSee('never-flash-this');
    $this->patch(route('profile.update'), ['name' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
    $this->get(route('home'))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

test('guests suspended and incomplete accounts cannot reach dashboard or profile writes', function (): void {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->patch(route('profile.update'), ['name' => 'No'])->assertRedirect(route('login'));
    foreach ([['google2fa_enabled' => false], ['must_change_password' => true], ['status' => 'suspended']] as $attributes) {
        $user = securityTestUser('student', $attributes);
        $this->actingAs($user);
        foreach ([['GET', route('home')], ['GET', route('profile.edit')], ['PATCH', route('profile.update')]] as [$method, $url]) {
            $this->actingAs($user);
            $response = $this->call($method, $url, ['name' => 'No']);
            if (isset($attributes['status'])) {
                $response->assertForbidden();
            } else {
                $response->assertRedirect(route(isset($attributes['google2fa_enabled']) ? '2fa.setup' : 'password.change'));
            }
        }
        expect($user->fresh()->name)->toBe($user->name);
    }
});

test('role dashboards show their own workspace and permitted quick actions', function (string $role, string $heading): void {
    $user = securityTestUser($role);
    $response = $this->actingAs($user)->get(route('home'))->assertOk()
        ->assertSee($heading)
        ->assertSee('data-workspace-role="'.$role.'"', false);

    foreach (['System overview', 'Administration overview', 'Teaching dashboard', 'Learning dashboard'] as $otherHeading) {
        if ($otherHeading !== $heading) {
            $response->assertDontSee($otherHeading);
        }
    }

    if ($user->can('access-admin')) {
        $response->assertSee('Manage accounts')->assertSee('Class administration');
    } else {
        $response->assertSee('Open my classes')->assertSee(route('classes.index').'#join-class')
            ->assertDontSee('Manage accounts');
        if ($user->can('manage-classes')) {
            $response->assertSee(route('classes.index').'#create-class');
        } else {
            $response->assertDontSee(route('classes.index').'#create-class');
        }
    }
})->with([
    ['super_admin', 'System overview'],
    ['admin', 'Administration overview'],
    ['teacher', 'Teaching dashboard'],
    ['student', 'Learning dashboard'],
]);

test('class forms and navigation match backend permissions for every role', function (string $role): void {
    $user = securityTestUser($role);
    $response = $this->actingAs($user)->get(route('classes.index'))->assertOk()
        ->assertSee('id="join-class"', false);

    if ($user->can('manage-classes')) {
        $response->assertSee('id="create-class"', false)->assertSee('action="'.route('classes.store').'"', false);
    } else {
        $response->assertDontSee('id="create-class"', false)->assertDontSee('action="'.route('classes.store').'"', false);
        $this->postJson(route('classes.store'), ['name' => 'Forbidden class'])->assertForbidden();
    }

    foreach (['users.index', 'roles.index'] as $route) {
        if ($user->can('access-admin')) {
            $response->assertSee(route($route));
            $this->get(route($route))->assertOk();
        } else {
            $response->assertDontSee(route($route));
            $this->get(route($route))->assertForbidden();
        }
    }
})->with(Role::NAMES);

test('learning design is scoped to teachers and students with distinct dashboard layouts', function (string $role): void {
    $response = $this->actingAs(securityTestUser($role))->get(route('home'))->assertOk();

    if (in_array($role, ['teacher', 'student'], true)) {
        $response->assertSee(asset('css/learning-workspace.css'));
        if ($role === 'teacher') {
            $response->assertSee('Ready for your next class?')->assertSee('class="learning-stats"', false)
                ->assertDontSee('Your next step starts here.');
        } else {
            $response->assertSee('Your next step starts here.')->assertSee('class="learning-class-grid"', false)
                ->assertDontSee('class="learning-stats"', false);
        }
    } else {
        $response->assertDontSee(asset('css/learning-workspace.css'));
    }
})->with(Role::NAMES);

test('class workspace actions follow membership permissions rather than application role', function (string $role, string $membershipRole): void {
    $owner = securityTestUser('teacher');
    $actor = $membershipRole === 'owner' ? $owner : securityTestUser($role);
    $schoolClass = SchoolClass::query()->create([
        'name' => 'Permission-aware classroom', 'description' => 'Existing class content',
        'created_by' => $owner->id, 'join_code' => Str::random(8),
    ]);
    $schoolClass->members()->attach($owner, ['role' => 'owner']);
    if ($actor->id !== $owner->id) {
        $schoolClass->members()->attach($actor, ['role' => $membershipRole]);
    }
    $channel = $schoolClass->channels()->create([
        'name' => 'Announcement', 'slug' => 'announcement', 'created_by' => $owner->id, 'is_default' => true,
    ]);

    $response = $this->actingAs($actor)->get(route('classes.show', $schoolClass))->assertOk()
        ->assertSee('id="class-channels"', false)->assertSee('id="class-members"', false)
        ->assertSee(route('classes.channels.show', [$schoolClass, $channel]));
    $dashboard = $this->get(route('home'))->assertOk();
    $announcement = $this->get(route('classes.channels.show', [$schoolClass, $channel]))->assertOk();

    if ($membershipRole === 'owner') {
        $response->assertSee('id="class-actions"', false)->assertSee('action="'.route('classes.update', $schoolClass).'"', false);
        $dashboard->assertSee(route('classes.show', $schoolClass).'#class-actions');
        $announcement->assertSee('id="class-channel-message-form"', false);
    } else {
        $response->assertDontSee('id="class-actions"', false)
            ->assertDontSee('action="'.route('classes.update', $schoolClass).'"', false)
            ->assertDontSee('action="'.route('classes.members.store', $schoolClass).'"', false)
            ->assertDontSee($schoolClass->join_code);
        $dashboard->assertDontSee(route('classes.show', $schoolClass).'#class-actions');
        $announcement->assertDontSee('id="class-channel-message-form"', false);
        $this->patchJson(route('classes.update', $schoolClass), ['name' => 'Unauthorized'])->assertForbidden();
        $this->postJson(route('classes.channels.messages.store', [$schoolClass, $channel]), [
            'body' => 'Unauthorized announcement', 'client_uuid' => (string) Str::uuid(),
        ])->assertForbidden();
        expect($schoolClass->fresh()->name)->toBe('Permission-aware classroom');
    }
})->with([
    ['teacher', 'owner'],
    ['teacher', 'student'],
    ['student', 'student'],
]);

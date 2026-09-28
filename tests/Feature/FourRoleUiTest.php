<?php

use App\Models\ChatRoom;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
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

test('BBU branding appears on guest and authenticated application shells', function (): void {
    expect(public_path('images/branding/bbu-online-learning.png'))->toBeFile()
        ->and(public_path('images/branding/bbu-mark.png'))->toBeFile()
        ->and(public_path('images/branding/favicon.png'))->toBeFile();

    $this->get(route('login'))->assertOk()
        ->assertSee(asset('images/branding/bbu-online-learning.png'), false)
        ->assertSee(asset('images/branding/favicon.png'), false)
        ->assertSee('alt="BBU Online Learning"', false)
        ->assertDontSee('backend/assets/images/logo-dark.png', false);

    $this->actingAs(securityTestUser())->get(route('home'))->assertOk()
        ->assertSee(asset('images/branding/bbu-mark.png'), false)
        ->assertSee(asset('images/branding/favicon.png'), false)
        ->assertSee(asset('css/brand.css'), false)
        ->assertSee('aria-label="'.config('app.name').' home"', false);
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
            ->assertSee('class="workspace-header-icon"', false)
            ->assertSee('class="workspace-mobile-only workspace-topbar-button"', false)
            ->assertDontSee('apps-calendar.html')->assertDontSee('Products &amp; Inventory', false)->assertDontSee('dashboard-sales.js')
            ->assertDontSee('href="#"', false)->assertDontSee('aria-label="Calls"', false);
        if (in_array($role, ['admin', 'super_admin'], true)) {
            $response->assertSee(route('users.index'))->assertSee(route('roles.index'))->assertDontSee('aria-label="Calendar"', false);
        } else {
            $response->assertDontSee(route('users.index'))->assertDontSee(route('roles.index'))
                ->assertSee('aria-label="Calendar"', false)->assertSee(route('calendar.index'));
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
        ->assertSee('data-shell-toggle', false)->assertDontSee('data-chat-list', false)->assertSee('chat-load-status')
        ->assertSee('workspace-chat')->assertSee('workspace-role-badge')
        ->assertSee('id="chat-conversations"', false)
        ->assertDontSee('id="chat-navigation"', false)->assertDontSee('data-chat-menu', false);
    expect(substr_count($chat->getContent(), 'id="workspace-navigation"'))->toBe(1)
        ->and(substr_count($chat->getContent(), '<main '))->toBe(1)
        ->and(substr_count($chat->getContent(), asset('css/teamstyle.css')))->toBe(1);
})->with(Role::NAMES);

test('phone navigation fits all permitted destinations and updates the active destination', function (string $role): void {
    $this->actingAs(securityTestUser($role));
    $dashboard = $this->get(route('home'))->assertOk()->assertSee(asset('css/mobile-workspace.css'), false);
    preg_match('/<nav class="workspace-mobile-nav"[^>]*>(.*?)<\/nav>/s', $dashboard->getContent(), $matches);
    $mobileNavigation = $matches[1] ?? '';

    expect($mobileNavigation)->not->toBeEmpty()
        ->and(substr_count($mobileNavigation, 'data-workspace-nav'))->toBe(in_array($role, ['admin', 'super_admin'], true) ? 6 : 7)
        ->and(substr_count($mobileNavigation, 'aria-current="page"'))->toBe(1)
        ->and($mobileNavigation)->toContain(route('home'), route('classes.index'), route('search.index'), route('chat.index'), route('profile.edit'));

    expect($dashboard->getContent())->toContain('--workspace-mobile-nav-count: '.(in_array($role, ['admin', 'super_admin'], true) ? 6 : 7))
        ->and(file_get_contents(public_path('css/mobile-workspace.css')))
        ->toContain('repeat(var(--workspace-mobile-nav-count, 5), minmax(0, 1fr))');

    if (in_array($role, ['admin', 'super_admin'], true)) {
        expect($mobileNavigation)->toContain(route('users.index'))->not->toContain(route('assessments.index'), route('calendar.index'));
    } else {
        expect($mobileNavigation)->toContain(route('assessments.index'), route('calendar.index'))->not->toContain(route('users.index'));
    }

    $chat = $this->get(route('chat.index'))->assertOk();
    preg_match('/<nav class="workspace-mobile-nav"[^>]*>(.*?)<\/nav>/s', $chat->getContent(), $chatMatches);
    expect($chatMatches[1] ?? '')->toContain('href="'.route('chat.index').'" class="workspace-mobile-nav-link active"')
        ->and(substr_count($chatMatches[1] ?? '', 'aria-current="page"'))->toBe(1);

    expect(file_get_contents(public_path('js/workspace.js')))
        ->toContain('currentMobileNavigation.replaceWith(nextMobileNavigation)', 'mobileStyle.before(link)');
})->with(Role::NAMES);

test('management account cards open a filtered searchable and paginated directory', function (): void {
    $superAdmin = securityTestUser('super_admin');
    $teacherRole = Role::query()->where('name', 'teacher')->firstOrFail();
    User::factory()->onboarded()->count(20)->create(['role_id' => $teacherRole->id]);
    $namedTeacher = securityTestUser('teacher', ['name' => 'Ada Lin', 'email' => 'ada@example.test']);
    $suspendedTeacher = securityTestUser('teacher', ['name' => 'Zoe Suspended', 'status' => 'suspended']);
    $student = securityTestUser('student');
    securityTestUser('admin');

    $this->actingAs($superAdmin)->get(route('home'))->assertOk()
        ->assertSee(route('users.index', ['role' => 'teacher']), false);

    $this->get(route('users.index', ['role' => 'teacher']))->assertOk()
        ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 22
            && $users->count() === 20
            && str_contains($users->nextPageUrl(), 'role=teacher'))
        ->assertDontSee($student->email);

    $this->get(route('users.index', ['role' => 'teacher', 'page' => 2]))->assertOk()
        ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 22 && $users->count() === 2);

    $this->get(route('users.index', ['role' => 'teacher', 'search' => '  Ada Lin  ', 'status' => 'active']))->assertOk()
        ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1 && $users->first()->is($namedTeacher))
        ->assertSee('value="Ada Lin"', false)
        ->assertDontSee($suspendedTeacher->email);

    $this->get(route('users.index', ['role' => 'teacher', 'status' => 'suspended']))->assertOk()
        ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1 && $users->first()->is($suspendedTeacher));

    $this->actingAs(securityTestUser('admin'))->get(route('users.index', ['role' => 'admin']))
        ->assertRedirect()->assertSessionHasErrors('role');
});

test('account forms have associated labels and clear page headings', function (): void {
    $this->actingAs(securityTestUser('super_admin'));
    $teacher = securityTestUser('teacher');

    foreach (['users.create', 'users.edit'] as $route) {
        $response = $this->get($route === 'users.edit' ? route($route, $teacher) : route($route))->assertOk()
            ->assertSee('<h1 class="h3 mb-1" id="account-form-title">', false)
            ->assertSee('Back to accounts</a>', false)
            ->assertDontSee('list_user');

        foreach (['name', 'email', 'role_id', 'phone', 'profile'] as $field) {
            $response->assertSee('for="'.$field.'"', false)->assertSee('id="'.$field.'"', false);
        }
    }

    $this->get(route('users.create'))->assertOk()
        ->assertSee('for="password"', false)
        ->assertSee('for="password_confirmation"', false);
});

test('chat uses the same application sidebar as the rest of the workspace', function (): void {
    $workspaceStyles = file_get_contents(public_path('css/workspace.css'));
    $chatStyles = file_get_contents(public_path('css/chat-workspace.css'));

    expect($workspaceStyles)->toContain('.application-shell .sidenav-menu', 'width: 240px', '.workspace-sidebar-close')
        ->toContain('.application-shell.workspace-sidebar-collapsed .sidenav-menu', 'width: 76px', '.workspace-sidebar-collapsed .page-content')
        ->toContain('.workspace-sidebar-identity', '.workspace-role-indicator', '.workspace-role-copy')
        ->toContain('--workspace-sidebar-bg: #111a3a', '--workspace-sidebar-active: #2563eb', '--workspace-sidebar-muted: #aebbdd')
        ->toContain('.application-shell.workspace-navigating', '@keyframes workspace-navigation-progress')
        ->and($chatStyles)->not->toContain('.application-shell.workspace-chat .sidenav-menu', '--osen-sidenav-width: 104px');

    $this->actingAs(securityTestUser('admin'));
    foreach (['home', 'chat.index'] as $route) {
        $this->get(route($route))->assertOk()
            ->assertSee('class="workspace-brand-logo"', false)
            ->assertSee('class="workspace-sidebar-close"', false)
            ->assertSee('data-shell-collapse', false)
            ->assertSee('class="ti ti-layout-sidebar-left-collapse"', false)
            ->assertSee('class="workspace-sidebar-identity"', false)
            ->assertSee('class="workspace-role-indicator"', false)
            ->assertSee('aria-label="Close navigation"', false)
            ->assertSee('id="workspace-navigation-status"', false)
            ->assertSee('class="workspace-nav-link active"', false);
    }
});

test('workspace shell stays aligned when the theme condenses its sidebar', function (): void {
    $workspaceStyles = file_get_contents(public_path('css/workspace.css'));
    $learningStyles = file_get_contents(public_path('css/learning-workspace.css'));

    expect($workspaceStyles)
        ->toContain('body.application-shell { --workspace-shell-sidebar-width: 240px; }')
        ->toContain('body.application-shell.workspace-sidebar-collapsed { --workspace-shell-sidebar-width: 76px; }')
        ->toContain('html[data-sidenav-size] body.application-shell .sidenav-menu {')
        ->toContain('html[data-sidenav-size] body.application-shell .page-content { margin-left: var(--workspace-shell-sidebar-width); }')
        ->toContain('left: var(--workspace-shell-sidebar-width);')
        ->toContain('html[data-sidenav-size] body.application-shell .app-topbar { left: 0 !important; margin-left: 0 !important; }')
        ->and($learningStyles)
        ->toContain('.learning-workspace .workspace-page .profile-hero {')
        ->toContain('background: linear-gradient(115deg, #1d4ed8, #4f46e5 58%, #6d3bd3);');
});

test('workspace sidebar uses partial navigation for every primary page including chat', function (): void {
    $response = $this->actingAs(securityTestUser('admin'))->get(route('home'))->assertOk();
    $html = $response->getContent();

    expect($html)->toMatch('/title="Dashboard"\s+data-workspace-nav/')
        ->toMatch('/title="Class administration"\s+data-workspace-nav/')
        ->toMatch('/title="My profile"\s+data-workspace-nav/')
        ->toMatch('/title="Chats"\s+data-workspace-nav/');

    expect($html)->toMatch('/title="My profile"[^>]*>\s*<span[^>]*class="user-avatar"/s')
        ->toContain('class="user-avatar-initials"');
    expect(file_get_contents(public_path('css/workspace.css')))
        ->toContain('.workspace-sidebar-collapsed .workspace-nav-link > .workspace-nav-label')
        ->not->toContain('.workspace-sidebar-collapsed .workspace-nav-link > span {');
});

test('empty dashboards are genuine zero states for teachers and students', function (string $role): void {
    $response = $this->actingAs(securityTestUser($role))->get(route('home'))->assertOk()
        ->assertViewHas('classCounts', ['active' => 0, 'archived' => 0])
        ->assertViewHas('conversationCounts', ['direct' => 0, 'group' => 0])
        ->assertSee('No classes yet');

    $response->assertSee('0 direct · 0 groups');
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
        if ($role === 'admin') {
            $response->assertSee(route('academics.index'))->assertSee('Academic Structure')->assertSee('aria-label="Accounts"', false);
        } else {
            $response->assertSee('Fixed Roles')->assertDontSee('Academic Structure');
        }
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

test('core workspace pages use the shared semantic color system', function (): void {
    $admin = securityTestUser('admin');

    $this->actingAs($admin)->get(route('home'))->assertOk()
        ->assertSee('management-dashboard-hero', false)
        ->assertSee('management-metrics', false)
        ->assertSee('management-account-grid', false)
        ->assertSee('management-quick-actions', false);

    $this->get(route('users.index'))->assertOk()
        ->assertSee('account-page-heading', false)
        ->assertSee('account-table-card', false);

    $this->get(route('classes.index'))->assertOk()
        ->assertSee('class-hero', false)
        ->assertSee('class-total-badge', false);

    $this->get(route('roles.index'))->assertOk()
        ->assertSee('roles-page-heading', false)
        ->assertSee('workspace-info-panel', false)
        ->assertSee('role-admin', false)
        ->assertSee('data-label="Definition"', false);

    $this->get(route('profile.edit'))->assertOk()
        ->assertSee('profile-details-card', false)
        ->assertSee('profile-security-card', false)
        ->assertSee('profile-security-status', false);

    $styles = file_get_contents(public_path('css/workspace.css'));
    expect($styles)->toContain('--workspace-purple: #7c3aed', '--workspace-teal: #0f766e', '--workspace-warning: #b45309')
        ->toContain('.workspace-page-heading', '.workspace-badge', '.profile-security-status');
});

test('class forms and navigation match backend permissions for every role', function (string $role): void {
    $user = securityTestUser($role);
    $response = $this->actingAs($user)->get(route('classes.index'))->assertOk()
        ->assertSee('id="join-class"', false);

    if (in_array($role, ['teacher', 'student'], true)) {
        $response->assertSee('classes-page-hero', false)
            ->assertSee('classes-summary', false)
            ->assertSee('classes-directory', false);
        if ($role === 'student') {
            $response->assertSee('Class enrollment')
                ->assertSee('Enter the class code from your teacher to join your learning space.')
                ->assertDontSee('Create or join a space');
        }
    }

    if (in_array($role, ['admin', 'super_admin'], true)) {
        $response->assertSee('Use a code to access class content')
            ->assertSee('Enroll your account to access class messages and learning content.');
    } elseif ($role === 'teacher') {
        $response->assertSee('Join as a student member')->assertDontSee('Use a code from your teacher');
    } else {
        $response->assertSee('Use a code from your teacher');
    }

    if ($user->can('manage-classes')) {
        $response->assertSee('id="create-class"', false)
            ->assertSee('action="'.route('classes.store').'"', false)
            ->assertSee('class="ti ti-school"', false)
            ->assertDontSee('ti-school-plus', false);
    } else {
        $response->assertDontSee('id="create-class"', false)->assertDontSee('method="POST" action="'.route('classes.store').'"', false);
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

test('all roles use the shared visual system with role specific dashboard layouts', function (string $role): void {
    $response = $this->actingAs(securityTestUser($role))->get(route('home'))->assertOk();

    $response->assertSee(asset('css/learning-workspace.css'))
        ->assertSee('class="application-shell learning-workspace', false);

    if ($role === 'teacher') {
        $response->assertSee('Ready for your next class?')->assertSee('class="teacher-metrics"', false)
            ->assertSee('Teaching Overview')->assertSee('Quick Actions')
            ->assertDontSee('Your next step starts here.');
    } elseif ($role === 'student') {
        $response->assertSee('Your next step starts here.')->assertSee('student-dashboard-hero', false)
            ->assertSee('student-metrics', false)->assertSee('student-dashboard-class-grid', false)
            ->assertSee('Learning Overview')->assertSee('Quick Actions')
            ->assertSee('class="btn teacher-secondary-action" href="'.route('attendance.mine').'"', false);
    } else {
        $response->assertSee('management-dashboard-hero', false)
            ->assertSee('management-metrics', false)
            ->assertSee('management-account-grid', false)
            ->assertSee('management-quick-actions', false);
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

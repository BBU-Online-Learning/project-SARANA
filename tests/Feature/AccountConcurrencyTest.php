<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->accountDriver = config('database.default') === 'mysql' ? 'mysql' : 'sqlite';
    $this->accountProcesses = [];
    $this->accountMarkers = [];
    if ($this->accountDriver === 'mysql') {
        $this->accountDatabase = config('database.connections.mysql.database');
        if (! preg_match('/^elearning_roles_test_[a-f0-9]{24}$/', $this->accountDatabase)) {
            throw new RuntimeException('MySQL concurrency tests require an explicitly disposable elearning_roles_test_<24 hex> database.');
        }
        config(['database.connections.mysql.url' => null]);
        DB::purge('mysql');
        expect(DB::connection()->getDatabaseName())->toBe($this->accountDatabase);
        expect(Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]))->toBe(0);

        return;
    }

    $this->accountDatabase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'elearning_roles_'.\Illuminate\Support\Str::uuid().'.sqlite';
    touch($this->accountDatabase);
    $this->accountProcesses = [];
    $this->accountMarkers = [];
    config([
        'database.default' => 'account_concurrency',
        'database.connections.account_concurrency' => array_replace(config('database.connections.sqlite'), [
            'database' => $this->accountDatabase,
            'url' => null,
            'busy_timeout' => 10000,
        ]),
    ]);
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe($this->accountDatabase);
    expect(Artisan::call('migrate', ['--database' => 'account_concurrency', '--force' => true]))->toBe(0);
});

afterEach(function (): void {
    foreach ($this->accountProcesses as $process) {
        if ($process->isRunning()) {
            $process->stop();
        }
    }
    DB::disconnect($this->accountDriver === 'mysql' ? 'mysql' : 'account_concurrency');
    if ($this->accountDriver === 'mysql') {
        foreach ($this->accountMarkers as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return;
    }
    foreach (array_merge($this->accountMarkers, [
        $this->accountDatabase, $this->accountDatabase.'-wal', $this->accountDatabase.'-shm', $this->accountDatabase.'-journal',
    ]) as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

/** @return list<Process> */
function concurrentAccountWorkers(object $test, array $jobs): array
{
    $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = $argv[1];
$driver = $argv[5];
if (config('app.env') !== 'testing' || ! str_starts_with(basename($path), 'elearning_roles_')) {
    exit(90);
}
config([
    'database.default' => $driver,
    'database.connections.mysql.database' => $path,
    'database.connections.mysql.url' => null,
    'database.connections.sqlite.database' => $path,
    'database.connections.sqlite.url' => null,
    'database.connections.sqlite.busy_timeout' => 10000,
]);
Illuminate\Support\Facades\DB::purge($driver);
$job = json_decode($argv[4], true, flags: JSON_THROW_ON_ERROR);
$actor = isset($job['actor']) ? App\Models\User::findOrFail($job['actor']) : null;
$target = App\Models\User::findOrFail($job['user']);
touch($argv[2]);
$deadline = microtime(true) + 20;
while (! is_file($argv[3])) {
    if (microtime(true) > $deadline) {
        exit(91);
    }
    usleep(10000);
}
try {
    $service = app(App\Services\AccountManagementService::class);
    if ($job['operation'] === 'class_enroll') {
        app(App\Services\ClassManagementService::class)->enroll($actor, App\Models\SchoolClass::findOrFail($job['class']));
    } elseif ($job['operation'] === 'class_transfer') {
        app(App\Services\ClassManagementService::class)->transfer($actor, App\Models\SchoolClass::findOrFail($job['class']), $target->id);
    } elseif ($job['operation'] === 'provision') {
        $service->provisionFirstSuperAdmin($target->id, $target->email, true);
    } elseif ($job['operation'] === 'otp') {
        $security = app(App\Services\AuthSecurityService::class);
        $security->withUser($target->id, function ($user) use ($security, $job): void {
            $security->consumeOtp($user, $job['code']);
        });
    } elseif ($job['operation'] === 'reset') {
        $result = app(App\Services\AccountRecoveryService::class)->reset([
            'email' => $target->email, 'token' => $job['token'], 'password' => 'Concurrent-reset-password-42',
        ]);
        exit($result === Illuminate\Support\Facades\Password::PASSWORD_RESET ? 0 : 4);
    } elseif ($job['operation'] === 'delete') {
        $service->delete($actor, $target);
    } else {
        $service->save($actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $job['role_id'] ?? $target->role_id,
            'status' => $job['status'] ?? $target->status,
        ]);
    }
    exit(0);
} catch (Illuminate\Auth\Access\AuthorizationException $exception) {
    exit(3);
} catch (Illuminate\Validation\ValidationException $exception) {
    exit(4);
}
PHP;
    $markerBase = sys_get_temp_dir().DIRECTORY_SEPARATOR.'elearning_roles_'.\Illuminate\Support\Str::uuid();
    $go = $markerBase.'.go';
    $test->accountMarkers[] = $go;
    foreach ($jobs as $index => $job) {
        $ready = $markerBase.'.ready'.$index;
        $test->accountMarkers[] = $ready;
        $process = new Process([PHP_BINARY, '-r', $script, $test->accountDatabase, $ready, $go, json_encode($job, JSON_THROW_ON_ERROR), $test->accountDriver], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $test->accountDriver,
            'DB_DATABASE' => $test->accountDatabase,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'QUEUE_CONNECTION' => 'sync',
        ]);
        $process->setTimeout(30);
        $process->start();
        $test->accountProcesses[] = $process;
    }

    $deadline = microtime(true) + 20;
    foreach (array_slice($test->accountMarkers, 1) as $ready) {
        while (! is_file($ready)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Concurrent workers did not become ready: '.implode("\n", array_map(
                    fn (Process $process): string => 'Exit '.$process->getExitCode().': '.$process->getErrorOutput().$process->getOutput(), $test->accountProcesses
                )));
            }
            usleep(10000);
        }
    }
    touch($go);
    foreach ($test->accountProcesses as $process) {
        $process->wait();
    }

    return $test->accountProcesses;
}

test('competing administrative class enrollments produce one membership and one audit', function (): void {
    $admin = securityTestUser('admin');
    $teacher = securityTestUser('teacher');
    $schoolClass = \App\Models\SchoolClass::create(['name' => 'Concurrent class', 'created_by' => $teacher->id, 'join_code' => 'RACE1234']);
    $schoolClass->members()->attach($teacher, ['role' => 'owner']);
    $job = ['operation' => 'class_enroll', 'actor' => $admin->id, 'user' => $admin->id, 'class' => $schoolClass->id];
    $workers = concurrentAccountWorkers($this, [$job, $job]);
    $codes = array_map(fn (Process $worker): ?int => $worker->getExitCode(), $workers);
    sort($codes);
    expect($codes)->toBe([0, 3]);
    expect($schoolClass->memberRecords()->where('user_id', $admin->id)->count())->toBe(1);
    expect(\App\Models\ClassMembershipAudit::where('action', 'administrative_enrollment')->count())->toBe(1);
});

test('competing ownership transfers preserve exactly one eligible owner and complete audits', function (): void {
    $admin = securityTestUser('admin');
    $teacher = securityTestUser('teacher');
    $targets = [securityTestUser('teacher'), securityTestUser('teacher')];
    $schoolClass = \App\Models\SchoolClass::create(['name' => 'Concurrent class', 'created_by' => $teacher->id, 'join_code' => 'RACE1234']);
    $schoolClass->members()->attach($teacher, ['role' => 'owner']);
    $workers = concurrentAccountWorkers($this, array_map(fn (User $target): array => [
        'operation' => 'class_transfer', 'actor' => $admin->id, 'user' => $target->id, 'class' => $schoolClass->id,
    ], $targets));
    expect(array_map(fn (Process $worker): ?int => $worker->getExitCode(), $workers))->toBe([0, 0]);
    expect($schoolClass->memberRecords()->where('role', 'owner')->count())->toBe(1);
    expect(\App\Models\ClassMembershipAudit::where('action', 'ownership_transferred_in')->count())->toBe(2);
    expect(\App\Models\ClassMembershipAudit::where('action', 'ownership_transferred_out')->count())->toBe(2);
});

test('competing first super admin provisions commit exactly one assignment', function (): void {
    $role = Role::where('name', Role::ADMIN)->firstOrFail();
    $users = User::factory()->onboarded()->count(2)->create(['role_id' => $role->id]);
    $before = $users->mapWithKeys(fn (User $user): array => [$user->id => $user->fresh()->password]);
    $workers = concurrentAccountWorkers($this, $users->map(fn (User $user): array => [
        'operation' => 'provision', 'user' => $user->id,
    ])->all());
    $codes = array_map(fn (Process $process): ?int => $process->getExitCode(), $workers);
    sort($codes);
    expect($codes)->toBe([0, 4]);
    expect(User::whereHas('role', fn ($query) => $query->where('name', Role::SUPER_ADMIN))->count())->toBe(1);
    foreach ($users as $user) {
        expect($user->fresh()->password)->toBe($before[$user->id]);
    }
});

test('concurrent requests cannot remove the last active super admin', function (string $operation): void {
    $superAdmin = User::factory()->onboarded()->create([
        'role_id' => Role::where('name', Role::SUPER_ADMIN)->firstOrFail()->id,
    ]);
    $admin = User::factory()->onboarded()->create([
        'role_id' => Role::where('name', Role::ADMIN)->firstOrFail()->id,
    ]);
    $before = $superAdmin->fresh()->getAttributes();
    $job = [
        'operation' => $operation === 'delete' ? 'delete' : 'update',
        'user' => $superAdmin->id,
        'status' => $operation === 'suspend' ? 'suspended' : 'active',
        'role_id' => $operation === 'demote' ? $admin->role_id : $superAdmin->role_id,
    ];
    $workers = concurrentAccountWorkers($this, [
        $job + ['actor' => $admin->id],
        $job + ['actor' => $superAdmin->id],
    ]);
    foreach ($workers as $worker) {
        expect($worker->getExitCode())->toBe(3);
    }
    expect($superAdmin->fresh()->getAttributes())->toBe($before);
    expect(User::where('status', 'active')->whereHas('role', fn ($query) => $query->where('name', Role::SUPER_ADMIN))->count())->toBe(1);
})->with(['suspend', 'demote', 'delete']);

test('the storage migration preserves legacy rows and enables transactional account writes', function (): void {
    $legacy = Role::create(['name' => 'legacy_manager', 'status' => false]);
    $user = User::factory()->create(['role_id' => $legacy->id]);

    if ($this->accountDriver === 'mysql') {
        DB::statement('ALTER TABLE users ENGINE = MyISAM');
        DB::statement('ALTER TABLE roles ENGINE = MyISAM');
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        try {
            DB::statement('SET SESSION sql_mode = ?', ['']);
            User::whereKey($user->id)->update(['status' => '']);
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
        expect(fn () => app(\App\Services\AccountManagementService::class)->provisionFirstSuperAdmin($user->id, $user->email, false))
            ->toThrow(\Illuminate\Validation\ValidationException::class, 'Account changes require InnoDB');
    }

    $before = $user->fresh()->getAttributes();
    $rolesBefore = Role::withTrashed()->get()->toArray();
    $migration = require database_path('migrations/2026_08_30_214622_ensure_fixed_application_roles.php');
    $migration->up();
    expect($user->fresh()->getAttributes())->toBe($before);
    expect(Role::withTrashed()->get()->toArray())->toBe($rolesBefore);
    if ($this->accountDriver === 'mysql') {
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', $this->accountDatabase)
            ->whereIn('TABLE_NAME', ['roles', 'users'])->pluck('ENGINE')->all();
        expect($engines)->toBe(['InnoDB', 'InnoDB']);
        expect($user->fresh()->status)->toBe('');
    }
});

test('concurrent verification consumes an authenticator code exactly once', function (): void {
    $user = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::STUDENT)->firstOrFail()->id]);
    $job = ['operation' => 'otp', 'user' => $user->id, 'code' => securityTestOtp($user)];
    $workers = concurrentAccountWorkers($this, [$job, $job]);
    $codes = array_map(fn (Process $worker): ?int => $worker->getExitCode(), $workers);
    sort($codes);
    expect($codes)->toBe([0, 4]);
    expect($user->fresh()->two_factor_last_used_step)->not->toBeNull();
});

test('concurrent reset requests consume an email token exactly once', function (): void {
    $user = User::factory()->onboarded()->create(['role_id' => Role::where('name', Role::STUDENT)->firstOrFail()->id]);
    $token = \Illuminate\Support\Facades\Password::createToken($user);
    $job = ['operation' => 'reset', 'user' => $user->id, 'token' => $token];
    $workers = concurrentAccountWorkers($this, [$job, $job]);
    $codes = array_map(fn (Process $worker): ?int => $worker->getExitCode(), $workers);
    sort($codes);
    expect($codes)->toBe([0, 4]);
    expect($user->fresh()->auth_version)->toBe(1);
});

<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('release seeding creates fixed roles without credentialed accounts', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->orderBy('name')->pluck('name')->all())
        ->toBe(collect(Role::NAMES)->sort()->values()->all())
        ->and(User::query()->withTrashed()->count())->toBe(0);
});

test('private chat storage is outside the public web root and cannot serve direct URLs', function (): void {
    $disk = config('filesystems.disks.chat_private');

    expect(config('database.connections.mysql.engine'))->toBe('InnoDB')
        ->and($disk['visibility'])->toBe('private')
        ->and($disk['serve'])->toBeFalse()
        ->and(str_starts_with($disk['root'], public_path()))->toBeFalse();
});

test('reverb origins are restricted to configured application origins', function (): void {
    $origins = config('reverb.apps.apps.0.allowed_origins');

    expect($origins)->toBeArray()->not->toBeEmpty()
        ->and($origins)->not->toContain('*')
        ->and($origins)->toContain(parse_url(config('app.url'), PHP_URL_HOST));
});

test('browser realtime transport follows the configured websocket scheme', function (): void {
    $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));
    $application = file_get_contents(base_path('bootstrap/app.php'));
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

    expect($bootstrap)
        ->toContain("const pageUsesHttps = window.location.protocol === 'https:'")
        ->toContain('wsHost: reverbHost')
        ->toContain('forceTLS: pageUsesHttps')
        ->not->toContain('forceTLS: false')
        ->and($layout)->toContain('window.reverbRuntimeConfig')
        ->and($application)->toContain("trustProxies(at: ['127.0.0.1', '::1'])");
});

test('development launchers support local and temporary HTTPS testing', function (): void {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['scripts'])->toHaveKeys(['dev:local', 'dev:wifi'])
        ->and(file_exists(base_path('dev-local.ps1')))->toBeTrue()
        ->and(file_exists(base_path('dev-wifi.ps1')))->toBeTrue()
        ->and(config('reverb.browser.local_host'))->not->toBeEmpty()
        ->and(config('reverb.browser.local_port'))->toBeInt()
        ->and(config('reverb.browser.public_port'))->toBeInt();
});

test('staging example requires HTTPS production protections without containing secrets', function (): void {
    $contents = file_get_contents(base_path('.env.staging.example'));

    expect($contents)->toContain(
        'APP_ENV=staging',
        'APP_DEBUG=false',
        'APP_URL=https://',
        'SESSION_SECURE_COOKIE=true',
        'SESSION_ENCRYPT=true',
        'BROADCAST_CONNECTION=reverb',
        'FILESYSTEM_DISK=local',
        'MEDIA_DISK=chat_private',
        'QUEUE_CONNECTION=redis',
        'REVERB_SCHEME=https',
    )->not->toMatch('/^(APP_KEY|DB_PASSWORD|REDIS_PASSWORD|MAIL_PASSWORD|REVERB_APP_SECRET)=.+$/m');
});

test('release pages use local assets and browser code contains no attachment debug dump', function (): void {
    $chatView = file_get_contents(resource_path('views/chat/index.blade.php'));
    $passwordView = file_get_contents(resource_path('views/auth/change-password.blade.php'));
    $attachments = file_get_contents(public_path('js/chat/attachments.js'));

    expect($chatView)->not->toContain('cdn.jsdelivr.net')
        ->and($passwordView)->not->toContain('cdn.jsdelivr.net')
        ->and($attachments)->not->toContain('console.table', 'dump()');
});

<?php

use App\Models\SchoolClass;
use App\Services\ClassAccessService;
use App\Services\ClassManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('real Reverb sockets receive committed sends edits deletes and reconnect without leaking to removed memberships', function (): void {
    $owner = securityTestUser('teacher');
    $member = securityTestUser();
    $schoolClass = SchoolClass::create(['name' => 'Wire test', 'created_by' => $owner->id, 'join_code' => Str::random(8)]);
    $schoolClass->members()->attach($owner, ['role' => 'owner']);
    $schoolClass->members()->attach($member, ['role' => 'student']);
    $channel = $schoolClass->channels()->create(['name' => 'General', 'slug' => 'general', 'created_by' => $owner->id]);
    $url = route('classes.channels.messages.index', [$schoolClass, $channel]);
    $topics = collect([$owner, $member])->map(fn ($user) => 'private-school-class.membership.'.app(ClassAccessService::class)->membership($user, $schoolClass)->id)->all();
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    expect($socket)->not->toBeFalse();
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $key = Str::random(24);
    $secret = Str::random(32);
    $env = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        'REVERB_APP_ID' => 'class-wire-test', 'REVERB_APP_KEY' => $key, 'REVERB_APP_SECRET' => $secret,
        'REVERB_HOST' => '127.0.0.1', 'REVERB_PORT' => (string) $port, 'REVERB_SCHEME' => 'http',
        'REVERB_SCALING_ENABLED' => 'false', 'REVERB_SERVER_PATH' => '',
    ];
    $server = new Process([PHP_BINARY, base_path('tests/class-reverb-server.php')], base_path(), $env);
    $server->setTimeout(30);
    $input = new InputStream;
    $clients = new Process(['node', base_path('tests/class-message-sockets.cjs')], base_path(), [
        'CLASS_TEST_PORT' => (string) $port, 'CLASS_TEST_KEY' => $key, 'CLASS_TEST_SECRET' => $secret,
        'CLASS_TEST_TOPICS' => json_encode($topics),
    ], $input);
    $clients->setTimeout(30);
    $waitFor = function (Closure $condition, string $description): void {
        $deadline = microtime(true) + 10;
        while (! $condition()) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Timed out waiting for '.$description);
            }
            usleep(20000);
        }
    };
    try {
        $server->start();
        $waitFor(function () use ($port): bool {
            $connection = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $error, 0.1);
            if (! $connection) {
                return false;
            }
            fclose($connection);

            return true;
        }, 'isolated Reverb startup');
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => $key, 'secret' => $secret, 'app_id' => 'class-wire-test',
            'options' => ['host' => '127.0.0.1', 'port' => $port, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => ['timeout' => 2, 'connect_timeout' => 1],
        ]]);
        Broadcast::purge('reverb');
        $clients->start();
        $waitFor(fn () => substr_count($clients->getOutput(), 'ready:') === 2, 'two subscriptions');
        $id = $this->actingAs($owner)->postJson($url, ['body' => 'Wire send', 'client_uuid' => (string) Str::uuid()])
            ->assertCreated()->json('message.message_id');
        $waitFor(fn () => substr_count($clients->getOutput(), 'changed:') === 2, 'send signals');
        $this->actingAs($member)->getJson($url)->assertJsonPath('messages.0.body', 'Wire send');
        $this->actingAs($owner)->patchJson("$url/$id", ['body' => 'Wire edit'])->assertOk();
        $waitFor(fn () => substr_count($clients->getOutput(), 'changed:') === 4, 'edit signals');
        $this->actingAs($member)->getJson($url.'?after_id='.$id.'&visible_ids[]='.$id)->assertJsonPath('updates.0.body', 'Wire edit');
        $input->write("reconnect\n");
        $waitFor(fn () => substr_count($clients->getOutput(), 'ready:') === 3, 'reconnected subscription');
        $this->actingAs($owner)->deleteJson("$url/$id")->assertOk();
        $waitFor(fn () => substr_count($clients->getOutput(), 'changed:') === 6, 'delete signals after reconnect');
        $this->actingAs($member)->getJson($url.'?visible_ids[]='.$id)->assertJsonPath('updates.0.deleted', true)->assertDontSee('Wire edit');
        app(ClassManagementService::class)->remove($owner, $schoolClass, $member);
        $this->actingAs($owner)->postJson($url, ['body' => 'After removal'])->assertCreated();
        $waitFor(fn () => substr_count($clients->getOutput(), 'changed:') === 7, 'remaining member signal');
        usleep(150000);
        expect(substr_count($clients->getOutput(), 'changed:1'))->toBe(3);
        $this->actingAs($member)->getJson($url)->assertForbidden();
        $server->wait();
        $uuid = (string) Str::uuid();
        $this->actingAs($owner)->postJson($url, ['body' => 'Saved during outage', 'client_uuid' => $uuid])->assertCreated();
        $this->postJson($url, ['body' => 'Saved during outage', 'client_uuid' => $uuid])->assertOk();
        $this->getJson($url)->assertJsonPath('messages.1.body', 'Saved during outage');
        expect($channel->messages()->where('client_uuid', $uuid)->count())->toBe(1);
    } finally {
        $input->write("stop\n");
        $input->close();
        if ($clients->isStarted()) {
            $clients->wait();
        }
        if ($server->isStarted()) {
            $server->wait();
        }
        Broadcast::purge('reverb');
    }
    expect($clients->getExitCode())->toBe(0)->and($server->getExitCode())->toBe(0);
});

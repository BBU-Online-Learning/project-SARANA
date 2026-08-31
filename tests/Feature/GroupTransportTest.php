<?php

use App\Events\Chat\MessageUpdated;
use App\Events\Chat\SidebarUpdated;
use App\Services\Chat\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('real group sockets stop receiving after removal including legacy shared and user topics', function (): void {
    $owner = securityTestUser();
    $member = securityTestUser();
    $groups = app(GroupMembershipService::class);
    $room = $groups->create($owner, 'Wire group', [$member->id]);
    $message = $room->messages()->create(['sender_id' => $owner->id, 'body' => 'Wire message']);
    $topics = [
        'private-chat.membership.'.$room->roomMembers()->where('user_id', $owner->id)->sole()->id,
        'private-chat.membership.'.$room->roomMembers()->where('user_id', $member->id)->sole()->id,
        'presence-chat.room.'.$room->id,
        'private-user.'.$member->id,
    ];
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    expect($socket)->not->toBeFalse();
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $key = Str::random(24);
    $secret = Str::random(32);
    $server = new Process([PHP_BINARY, base_path('tests/class-reverb-server.php')], base_path(), [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        'REVERB_APP_ID' => 'group-wire-test', 'REVERB_APP_KEY' => $key, 'REVERB_APP_SECRET' => $secret,
        'REVERB_HOST' => '127.0.0.1', 'REVERB_PORT' => (string) $port, 'REVERB_SCHEME' => 'http',
        'REVERB_SCALING_ENABLED' => 'false', 'REVERB_SERVER_PATH' => '',
    ]);
    $server->setTimeout(30);
    $input = new InputStream;
    $clients = new Process(['node', base_path('tests/group-message-sockets.cjs')], base_path(), [
        'GROUP_TEST_PORT' => (string) $port, 'GROUP_TEST_KEY' => $key, 'GROUP_TEST_SECRET' => $secret,
        'GROUP_TEST_TOPICS' => json_encode($topics),
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
            'driver' => 'reverb', 'key' => $key, 'secret' => $secret, 'app_id' => 'group-wire-test',
            'options' => ['host' => '127.0.0.1', 'port' => $port, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => ['timeout' => 2, 'connect_timeout' => 1],
        ]]);
        Broadcast::purge('reverb');
        $clients->start();
        $waitFor(fn () => substr_count($clients->getOutput(), 'ready:') === 4, 'current and previously authorized subscriptions');
        event(new MessageUpdated($message));
        $waitFor(fn () => substr_count($clients->getOutput(), 'message.updated:') === 2, 'both current members');
        $input->write("reconnect\n");
        $waitFor(fn () => substr_count($clients->getOutput(), 'ready:') === 5, 'member reconnect');
        event(new MessageUpdated($message));
        $waitFor(fn () => substr_count($clients->getOutput(), 'message.updated:') === 4, 'delivery after reconnect');
        $groups->remove($owner, $room, $member->id);
        event(new MessageUpdated($message));
        event(new SidebarUpdated($member->id, ['room_id' => $room->id, 'body' => 'Must not arrive']));
        $waitFor(fn () => substr_count($clients->getOutput(), 'message.updated:0') === 3, 'remaining owner');
        $groups->add($owner, $room, [$member->id]);
        event(new MessageUpdated($message));
        $waitFor(fn () => substr_count($clients->getOutput(), 'message.updated:0') === 4, 'owner after re-enrollment');
        usleep(200000);
        expect(substr_count($clients->getOutput(), 'message.updated:1'))->toBe(2)
            ->and($clients->getOutput())->not->toContain('message.updated:2', 'sidebar.updated:3');
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

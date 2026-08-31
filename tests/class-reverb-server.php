<?php

if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('The transport fixture requires an isolated test environment.');
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
React\EventLoop\Loop::addTimer(10, static function (): void {
    React\EventLoop\Loop::stop();
});
$kernel->call('reverb:start', ['--host' => '127.0.0.1', '--port' => getenv('REVERB_PORT'), '--no-interaction' => true]);

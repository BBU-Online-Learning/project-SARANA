<?php

use Symfony\Component\Process\Process;

test('chat room search renders an accessible closable control', function (): void {
    $view = file_get_contents(resource_path('views/chat/partials/chat-area.blade.php'));

    expect($view)
        ->toContain('id="open-room-search-btn"')
        ->toContain('aria-controls="room-search-bar"')
        ->toContain('aria-expanded="false"')
        ->toContain('id="room-search-bar"')
        ->toContain('role="search"')
        ->toContain('hidden');
});

test('chat room search can toggle close reset and ignore stale responses', function (): void {
    $process = new Process(['node', base_path('tests/chat-search-client.cjs')], base_path());

    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
});

test('chat room search remains available at tablet widths and hidden state wins', function (): void {
    $css = file_get_contents(public_path('css/teamstyle.css'));

    expect($css)
        ->toContain('.teams-room-search-bar[hidden]')
        ->toContain('.teams-chat-actions #open-room-search-btn[hidden]')
        ->toContain('position: absolute')
        ->toContain('width: min(460px, calc(100vw - 48px))')
        ->toContain('box-shadow: 0 14px 38px')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->not->toContain('.teams-chat-actions .teams-icon-button:nth-child(1)');
});

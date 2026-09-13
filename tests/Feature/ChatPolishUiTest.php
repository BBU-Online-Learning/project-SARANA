<?php

use Symfony\Component\Process\Process;

test('chat uses recoverable sends smart scrolling confirmations and keyboard controls', function (): void {
    $messages = file_get_contents(public_path('js/chat/messages.js'));
    $chat = file_get_contents(public_path('js/chat/chat.js'));
    $group = file_get_contents(resource_path('views/chat/group.blade.php'));
    $conversation = file_get_contents(resource_path('views/chat/partials/conversation-item.blade.php'));
    $chatArea = file_get_contents(resource_path('views/chat/partials/chat-area.blade.php'));

    expect($messages)
        ->toContain('retry-message-btn')
        ->toContain('Your draft was kept')
        ->toContain('window.AppConfirm.ask')
        ->not->toContain('const confirmed = confirm(')
        ->and($chat)
        ->toContain('isNearMessagesBottom')
        ->toContain('showNewMessageIndicator')
        ->toContain('document.body.addEventListener("keydown"')
        ->and($group)
        ->toContain('data-confirm-message')
        ->not->toContain('onsubmit="return confirm(')
        ->and($conversation)
        ->toContain('aria-label="Open conversation with')
        ->and($chatArea)
        ->toContain('id="jump-to-latest-btn"');
});

test('failed messages can be retried without losing their draft', function (string $mode): void {
    $process = new Process(['node', base_path('tests/chat-message-recovery-client.cjs'), $mode], base_path());

    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
})->with(['--secure', '--lan']);

test('destructive chat actions use the reusable confirmation dialog', function (): void {
    $process = new Process(['node', base_path('tests/confirmation-dialog-client.cjs')], base_path());

    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
});

test('attachment composer validates configured limits and exposes modern upload controls', function (): void {
    $process = new Process(['node', base_path('tests/chat-attachments-client.cjs')], base_path());
    $process->mustRun();

    $messages = file_get_contents(public_path('js/chat/messages.js'));
    $preview = file_get_contents(public_path('js/chat/image-preview.js'));
    $chatArea = file_get_contents(resource_path('views/chat/partials/chat-area.blade.php'));

    expect($process->getOutput())->toContain('checks passed')
        ->and($messages)->toContain(
            'onUploadProgress',
            'cancel-upload-btn',
            'attachment_context',
            'failedUploadSignatures',
            'matchingFailedUpload',
            'clearIfSignature',
        )
        ->and($preview)->toContain('dataset.downloadSrc', 'aria-hidden')
        ->and($chatArea)->toContain('data-attachment-drop-overlay', 'attachment-limit-summary');
});

test('chat polish styles stay responsive and expose visible failed states', function (): void {
    $teamStyles = file_get_contents(public_path('css/teamstyle.css'));
    $workspaceStyles = file_get_contents(public_path('css/workspace.css'));

    expect($teamStyles)
        ->toContain('.jump-to-latest-btn')
        ->toContain('.message-failed')
        ->toContain('.retry-message-btn')
        ->toContain('.attachment-upload-progress')
        ->toContain('.video-attachment-card')
        ->and($workspaceStyles)
        ->toContain('.app-confirm-dialog')
        ->toContain('@media (max-width: 576px)');
});

<?php

use App\Models\ChatRoom;
use App\Services\Chat\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('chat workspace separates direct and group controls and respects group management permissions', function (): void {
    $teacher = securityTestUser('teacher', ['name' => 'Teacher Dara']);
    $student = securityTestUser('student', ['name' => 'Sophea Chan']);
    $group = app(GroupMembershipService::class)->create($teacher, 'Web Development', [$student->id]);
    $group->messages()->create(['sender_id' => $teacher->id, 'body' => 'Welcome to our class discussion.']);
    $group->messages()->create(['sender_id' => $teacher->id, 'body' => 'Please share your questions here.']);
    $group->messages()->create(['sender_id' => $student->id, 'body' => 'Thank you! I have a question about the project.']);
    $direct = ChatRoom::create(['type' => 'direct', 'created_by' => $teacher->id, 'last_message_at' => now()]);
    $direct->members()->attach([$teacher->id, $student->id], ['joined_at' => now()]);
    $direct->messages()->create(['sender_id' => $teacher->id, 'body' => 'Hi Sophea! How is your project going?']);
    $direct->messages()->create(['sender_id' => $student->id, 'body' => "It is going well, thank you.\nCould you take a look at my layout?"]);

    $this->actingAs($student);
    $index = $this->get(route('chat.index'))->assertOk()->assertSee('data-conversation-filter="unread"', false)
        ->assertSee('css/chat-workspace.css')->assertSee('Your conversations')->assertSee('js/chat/workspace-ui.js')
        ->assertSee('data-workspace-page-style="chat-workspace"', false)
        ->assertSee('data-workspace-page-script="chat-realtime"', false)
        ->assertDontSee('chat-mobile-toolbar')->assertDontSee('>Conversations</button>', false);
    $workspaceStyles = file_get_contents(public_path('css/chat-workspace.css'));
    $shellStyles = file_get_contents(public_path('css/workspace.css'));
    $createChatStyles = file_get_contents(public_path('css/create-chat.css'));
    expect($workspaceStyles)->toContain('.application-shell.workspace-chat .app-topbar', 'linear-gradient(105deg, #111a3a', 'border-radius: 18px')
        ->toContain('.workspace-chat .teams-chat-actions', '.workspace-chat .teams-composer', '.workspace-chat .teams-empty-chat-icon')
        ->toContain('min-height: 46px', 'border-radius: 23px', '.workspace-chat .teams-send-button { width: 36px; height: 36px', 'flex-wrap: nowrap')
        ->toContain('.application-shell.workspace-chat .teams-composer-tools button { display: inline-grid; width: 32px; height: 32px; border-radius: 50%')
        ->toContain('.workspace-chat .sticker-picker { left: 18px; width: min(330px, calc(100% - 36px)); max-height: min(460px, calc(100dvh - 170px))')
        ->toContain('.group-settings-member-search) > input[type="search"]', 'outline: 0; box-shadow: none', '.teams-room-search-bar:focus-within')
        ->toContain('--chat-message-own-bg: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%)', 'width: fit-content', 'max-width: 100%', 'min-height: 0', 'padding: 7px 11px', 'border-radius: 12px')
        ->toContain('.application-shell.workspace-chat .teams-message.is-other .teams-message-bubble { margin-right: auto; }')
        ->toContain('.application-shell.workspace-chat .teams-message.is-own .teams-message-bubble { margin-left: auto; }')
        ->toContain('.workspace-chat .teams-message.is-other { justify-content: flex-start; }')
        ->toContain('.workspace-chat .teams-message.is-own { justify-content: flex-start; flex-direction: row-reverse; }');
    expect($workspaceStyles)->not->toContain('.message-continuation', '[data-conversation-type="direct"] .teams-message-meta > strong');
    expect($shellStyles)->toContain('input:not([type="search"]):focus-visible')
        ->not->toContain('.application-shell.workspace-chat input:focus-visible');
    expect($createChatStyles)->not->toContain('.create-chat-search input:focus-visible');
    $directHtml = $this->getJson(route('chat.rooms.show', $direct))->assertOk()->json('html');
    expect(substr_count($directHtml, 'class="message-profile-link"'))->toBe(2)
        ->and(substr_count($directHtml, 'class="teams-message-meta"'))->toBe(2);
    expect(strpos($directHtml, 'class="teams-message-meta"'))
        ->toBeLessThan(strpos($directHtml, 'class="teams-message-toolbar"'));
    expect($directHtml)->toContain('data-call-type="audio"', 'data-call-type="video"', 'data-emoji-toggle', '<textarea',
        'chat-mobile-back', 'data-chat-list', 'aria-label="Back to conversations"', 'ti-arrow-left',
        'teams-message is-own', 'teams-message is-other', 'chat-mobile-call-menu', 'data-mobile-message-actions', 'aria-label="Message actions"')
        ->not->toContain('id="group-info-drawer"');
    expect($directHtml)->toContain('data-message-body="Hi Sophea! How is your project going?">Hi Sophea! How is your project going?</div>');
    expect(file_get_contents(public_path('js/chat/messages.js')))
        ->toContain('<div class="teams-message-bubble">${escapeHtml(body)}</div>');
    $groupHtml = $this->getJson(route('chat.rooms.show', $group))->assertOk()->json('html');
    expect(substr_count($groupHtml, 'class="message-profile-link"'))->toBe(3)
        ->and(substr_count($groupHtml, 'class="teams-message-meta"'))->toBe(3);
    expect(strpos($groupHtml, 'class="teams-message-meta"'))
        ->toBeLessThan(strpos($groupHtml, 'class="teams-message-toolbar"'));
    expect($groupHtml)->toContain('id="group-info-drawer"', 'Group owner', 'chat-sender-role', 'Teacher', 'Student', 'Leave group')
        ->not->toContain('Edit group &amp; manage members', 'Edit group & manage members', 'data-start-voice-call');
    $this->actingAs($teacher);
    $ownerHtml = $this->getJson(route('chat.rooms.show', $group))->assertOk()->json('html');
    expect($ownerHtml)->toContain('Edit group & manage members')->not->toContain('data-confirm-title="Leave group?"');
    $this->actingAs(securityTestUser())->getJson(route('chat.rooms.show', $group))->assertForbidden();

    if ($directory = getenv('UI_PREVIEW_DIRECTORY')) {
        if (! preg_match('/^elearning_ui_[a-f0-9]{16}$/', basename($directory)) || realpath(dirname($directory)) !== realpath(sys_get_temp_dir())) {
            throw new RuntimeException('UI previews require an isolated temporary directory.');
        }
        if (! is_dir($directory)) {
            mkdir($directory);
        }
        $empty = view('chat.partials.empty-chat')->render();
        $previewRooms = [
            $direct->id => ['html' => $directHtml, 'room_id' => $direct->id, 'room_type' => 'direct', 'membership_id' => 1, 'next_cursor' => null],
            $group->id => ['html' => $groupHtml, 'name' => $group->name, 'room_id' => $group->id, 'room_type' => 'group', 'membership_id' => 2, 'next_cursor' => null],
        ];
        foreach (['direct' => $directHtml, 'group' => $groupHtml] as $type => $roomHtml) {
            $html = str_replace($empty, $roomHtml, $index->getContent());
            $html = preg_replace('/<script[^>]*type="module"[^>]*>.*?<\/script>/s', '', $html);
            $html = preg_replace('/<link[^>]+href="http:\/\/\[::1\]:5173[^>]*>/', '', $html);
            $fixture = '<script>window.previewRooms='.json_encode($previewRooms, JSON_HEX_TAG | JSON_HEX_AMP).';</script>';
            $fixture .= '<script>'.file_get_contents(base_path('tests/chat-preview-client.js')).'</script>';
            $html = str_replace('</head>', $fixture.'</head>', $html);
            $selectedId = $type === 'direct' ? $direct->id : $group->id;
            $html = str_replace('</body>', '<script>document.addEventListener("DOMContentLoaded", () => { window.chat.activeRoomId='.$selectedId.'; window.chat.roomType="'.$type.'"; document.body.classList.add("chat-mobile-room"); window.showChatLoadStatus(""); });</script></body>', $html);
            file_put_contents($directory.'/student-chat-'.$type.'.html', $html);
        }
    }
});

test('chat workspace client supports grouping drafts filters emoji and connection status', function (): void {
    $process = new Process(['node', base_path('tests/chat-workspace-client.cjs')], base_path());
    $process->mustRun();
    expect($process->getOutput())->toContain('checks passed');
});

test('direct and group chats stay at the latest message while media loads', function (): void {
    $process = new Process(['node', base_path('tests/chat-scroll-client.cjs')], base_path());
    $process->mustRun();

    expect($process->getOutput())->toContain('Direct and group chat media scroll checks passed.');
});

test('chat realtime keeps the open room quiet and updates unread badges', function (): void {
    $process = new Process(['node', base_path('tests/chat-unread-client.cjs')], base_path());
    $process->mustRun();
    expect($process->getOutput())->toContain('live unread badges passed');
});

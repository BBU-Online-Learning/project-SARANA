<?php

use App\Models\ChatGroupInvite;
use App\Models\ChatRoom;
use App\Services\Chat\GroupMembershipService;
use App\Support\ChatMessageFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function invitationGroup(): array
{
    $owner = securityTestUser('teacher', ['name' => 'Group Owner']);
    $member = securityTestUser('student', ['name' => 'Existing Member']);
    $outsider = securityTestUser('student', ['name' => 'Invited Student']);
    $room = app(GroupMembershipService::class)->create($owner, 'Laravel Study Group', [$member->id]);

    return [$owner, $member, $outsider, $room];
}

test('a group owner can create copy and regenerate a signed invitation', function (): void {
    [$owner, $member, $outsider, $room] = invitationGroup();
    expect($member->notifications()->firstOrFail()->data['title'])->toBe('Added to a group');

    $response = $this->actingAs($owner)->postJson(route('chat.groups.invites.store', $room))
        ->assertOk()
        ->assertJsonPath('message', 'Invitation link created. It will expire in 7 days.')
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'data-group-invite-url')
            && str_contains($html, 'data:image/svg+xml;base64,')
            && str_contains($html, 'Scan with another phone')
            && str_contains($html, 'data-download-group-invite-qr')
            && str_contains($html, 'group-invite.png')
            && str_contains($html, 'Download PNG')
            && ! str_contains($html, 'This local address works only on this computer'));

    $firstInvite = ChatGroupInvite::query()->sole();
    expect($firstInvite->isUsable())->toBeTrue()
        ->and($firstInvite->created_by)->toBe($owner->id)
        ->and($firstInvite->room_id)->toBe($room->id);

    $this->actingAs($owner)->postJson(route('chat.groups.invites.store', $room))->assertOk();
    expect($firstInvite->fresh()->revoked_at)->not->toBeNull()
        ->and(ChatGroupInvite::query()->usable()->count())->toBe(1);

    $this->actingAs($member)->postJson(route('chat.groups.invites.store', $room))->assertForbidden();
    $this->actingAs($outsider)->postJson(route('chat.groups.invites.store', $room))->assertForbidden();
});

test('invitation links and QR codes use the configured public tunnel address', function (): void {
    config(['chat.group_invite_public_url' => 'https://teaching-bristol-menus-homes.trycloudflare.com']);
    [$owner, $member, $outsider, $room] = invitationGroup();

    $response = $this->actingAs($owner)->postJson(route('chat.groups.invites.store', $room))->assertOk();
    $invite = ChatGroupInvite::query()->sole();
    $presentation = app(\App\Services\Chat\GroupInviteService::class)->presentation($invite);

    expect($presentation['url'])
        ->toStartWith('https://teaching-bristol-menus-homes.trycloudflare.com/chat/invites/'.$invite->id.'?')
        ->and($presentation['qr_code'])
        ->toStartWith('data:image/svg+xml;base64,');

    $response->assertJsonPath('html', fn (string $html): bool => str_contains(
        $html,
        'https://teaching-bristol-menus-homes.trycloudflare.com/chat/invites/'.$invite->id,
    ));

    auth()->logout();
    $this->get($presentation['url'])->assertRedirect();
    expect(session('url.intended'))->toBe($presentation['url']);

    $this->actingAs($outsider)->get($presentation['url'])->assertOk()->assertSee($room->name);
});

test('an authenticated user can review a signed invitation and join its group once', function (): void {
    [$owner, $member, $outsider, $room] = invitationGroup();
    $this->actingAs($owner)->postJson(route('chat.groups.invites.store', $room))->assertOk();
    $invite = ChatGroupInvite::query()->sole();
    $showUrl = URL::temporarySignedRoute('chat.group-invites.show', $invite->expires_at, ['invite' => $invite], absolute: false);
    $joinUrl = URL::temporarySignedRoute('chat.group-invites.join', $invite->expires_at, ['invite' => $invite], absolute: false);

    auth()->logout();
    $this->get($showUrl)->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(url($showUrl));

    $this->post(route('login.submit'), ['email' => $outsider->email, 'password' => 'password'])
        ->assertRedirect(route('2fa.challenge'));
    $this->post(route('2fa.challenge.submit'), ['code' => securityTestOtp($outsider)])
        ->assertRedirect(url($showUrl));

    $this->get($showUrl)->assertOk()
        ->assertSee('Laravel Study Group')
        ->assertSee('Join group chat')
        ->assertSee('Secure BBU group invitation');
    $this->get(route('chat.group-invites.show', $invite))->assertForbidden();

    $this->post($joinUrl)->assertRedirect(route('chat.index', ['room' => $room->id]));
    expect($room->roomMembers()->where('user_id', $outsider->id)->count())->toBe(1)
        ->and($invite->fresh()->joined_count)->toBe(1)
        ->and($owner->notifications()->firstOrFail()->data['category'])->toBe('group');

    $this->post($joinUrl)->assertRedirect(route('chat.index', ['room' => $room->id]));
    expect($room->roomMembers()->where('user_id', $outsider->id)->count())->toBe(1)
        ->and($invite->fresh()->joined_count)->toBe(1);

    $this->get(route('chat.index', ['room' => $room->id]))->assertOk()
        ->assertViewHas('initialRoomId', $room->id)
        ->assertSee('initialRoomId: '.$room->id, false);
});

test('disabled invitations stop new members and remain private to the group owner', function (): void {
    [$owner, $member, $outsider, $room] = invitationGroup();
    $this->actingAs($owner)->postJson(route('chat.groups.invites.store', $room))->assertOk();
    $invite = ChatGroupInvite::query()->sole();
    $showUrl = URL::temporarySignedRoute('chat.group-invites.show', $invite->expires_at, ['invite' => $invite], absolute: false);
    $joinUrl = URL::temporarySignedRoute('chat.group-invites.join', $invite->expires_at, ['invite' => $invite], absolute: false);

    $this->deleteJson(route('chat.groups.invites.destroy', [$room, $invite]))
        ->assertOk()
        ->assertJsonPath('message', 'Invitation link disabled.');
    expect($invite->fresh()->isUsable())->toBeFalse();

    $this->actingAs($outsider)->get($showUrl)->assertOk()
        ->assertSee('expired or was disabled')
        ->assertDontSee('Join group chat');
    $this->post($joinUrl)->assertRedirect()->assertSessionHasErrors('invite');
    expect($room->roomMembers()->where('user_id', $outsider->id)->exists())->toBeFalse();

    $this->actingAs($member)->deleteJson(route('chat.groups.invites.destroy', [$room, $invite]))->assertForbidden();
});

test('previous host-bound invitation links remain valid', function (): void {
    [$owner, $member, $outsider, $room] = invitationGroup();
    $this->actingAs($owner)->postJson(route('chat.groups.invites.store', $room))->assertOk();
    $invite = ChatGroupInvite::query()->sole();
    config(['app.url' => 'http://127.0.0.1:8000']);
    URL::forceRootUrl(config('app.url'));
    $legacyUrl = URL::temporarySignedRoute('chat.group-invites.show', $invite->expires_at, ['invite' => $invite]);
    URL::forceRootUrl(null);
    $normalizedUrl = parse_url($legacyUrl, PHP_URL_PATH).'?'.parse_url($legacyUrl, PHP_URL_QUERY);

    $this->actingAs($outsider)->get($normalizedUrl)->assertOk()->assertSee($room->name);
});

test('group invitation controls appear only for the group owner', function (): void {
    [$owner, $member, $outsider, $room] = invitationGroup();

    $this->actingAs($owner)->getJson(route('chat.rooms.show', $room))->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Invite with link or QR')
            && str_contains($html, 'data-group-invite-create'));

    $this->actingAs($member)->getJson(route('chat.rooms.show', $room))->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => ! str_contains($html, 'data-group-invite-panel'));

    $direct = ChatRoom::query()->create(['type' => 'direct', 'created_by' => $owner->id]);
    $direct->members()->attach([$owner->id, $outsider->id], ['joined_at' => now()]);
    $this->actingAs($owner)->postJson(route('chat.groups.invites.store', $direct))->assertForbidden();
});

test('group invitation client creates and copies links without reloading chat', function (): void {
    $process = new \Symfony\Component\Process\Process(['node', base_path('tests/chat-group-invite-client.cjs')], base_path());
    $process->mustRun();

    expect($process->getOutput())->toContain('checks passed');
});

test('chat messages safely turn invitation and regular URLs into links', function (): void {
    $inviteHtml = (string) ChatMessageFormatter::linkify('Join us: http://localhost:8000/chat/invites/12?expires=1&signature=abc');
    $regularHtml = (string) ChatMessageFormatter::linkify('Read https://example.com/docs. <script>alert(1)</script>');

    expect($inviteHtml)->toContain(
        'class="chat-message-link is-group-invite"',
        'href="/chat/invites/12?expires=1&amp;signature=abc"',
        'Join group chat',
        'target="_blank"',
    )->not->toContain('href="http://localhost:8000/chat/invites')
        ->and($regularHtml)->toContain('class="chat-message-link"', 'https://example.com/docs', '&lt;script&gt;')
        ->not->toContain('<script>');
});

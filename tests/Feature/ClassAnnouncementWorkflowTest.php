<?php

use App\Models\ClassAnnouncement;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Models\User;
use App\Services\ClassManagementService;
use Database\Seeders\ClassAnnouncementDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function noticeUser(string $roleName): User
{
    $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $role->id]);
}

/** @return array{0: SchoolClass, 1: SchoolClassChannel} */
function noticeClass(User $teacher, User ...$students): array
{
    $schoolClass = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $schoolClass->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($students as $student) {
        $schoolClass->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    }
    $channel = $schoolClass->channels()->create([
        'name' => 'Announcement', 'slug' => 'announcement', 'is_default' => true, 'created_by' => $teacher->id,
    ]);

    return [$schoolClass, $channel];
}

function noticeStoreUrl(SchoolClass $schoolClass, SchoolClassChannel $channel): string
{
    return route('classes.channels.notices.store', [$schoolClass, $channel]);
}

test('drafts stay private while published notices share the existing announcement channel', function () {
    $teacher = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($teacher, $student);
    $channel->messages()->create(['sender_id' => $teacher->id, 'body' => 'Legacy quick update']);

    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), [
        'title' => 'Draft field trip', 'body' => 'Details to follow',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $notice = ClassAnnouncement::query()->sole();
    expect($notice->status)->toBe('draft')->and($notice->published_at)->toBeNull();
    $this->actingAs($teacher)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertSee('Draft field trip')->assertSee('Legacy quick update')->assertSee('Create a notice draft')
        ->assertSee('<summary>Manage notice</summary>', false);
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertDontSee('Draft field trip')->assertSee('Legacy quick update')->assertDontSee('Create a notice draft')
        ->assertDontSee('<summary>Manage notice</summary>', false);

    $this->actingAs($teacher)->post(route('classes.channels.notices.publish', [$schoolClass, $channel, $notice]))->assertRedirect();
    expect($notice->fresh()->status)->toBe('published');
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertSee('Draft field trip')->assertSee('Legacy quick update');
    expect($student->notifications()->where('type', \App\Notifications\ActivityNotification::class)->count())->toBe(1);
});

test('scheduler publishes due notices once and skips drafts and future notices', function () {
    $teacher = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($teacher, $student);
    $this->travelTo(now()->startOfMinute());
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Scheduled library day', 'body' => 'Visit the library'])->assertRedirect();
    $notice = ClassAnnouncement::query()->sole();
    $publishAt = now()->addMinutes(2);
    $this->actingAs($teacher)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => $publishAt->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    expect($notice->fresh()->status)->toBe('scheduled');
    $this->artisan('class-announcements:process')->assertExitCode(0);
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertDontSee('Scheduled library day');
    $this->travelTo($publishAt);
    $this->artisan('class-announcements:process')->assertExitCode(0);
    $this->artisan('class-announcements:process')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('published')
        ->and($notice->fresh()->published_at)->not->toBeNull()
        ->and($student->notifications()->count())->toBe(1);
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertSee('Scheduled library day');
});

test('expired notices disappear immediately and the scheduler records expiry', function () {
    $teacher = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($teacher, $student);
    $this->travelTo(now()->startOfMinute());
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), [
        'title' => 'Temporary notice', 'body' => 'Read this now', 'expires_at' => now()->addMinutes(2)->format('Y-m-d H:i:s'),
    ]);
    $notice = ClassAnnouncement::query()->sole();
    $this->actingAs($teacher)->post(route('classes.channels.notices.publish', [$schoolClass, $channel, $notice]))->assertRedirect();
    $this->actingAs($teacher)->post(route('classes.channels.notices.pin', [$schoolClass, $channel, $notice]))->assertRedirect();
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertSee('Temporary notice');

    $this->travelTo(now()->addMinutes(2));
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertDontSee('Temporary notice');
    expect($notice->fresh()->status)->toBe('published');
    $this->artisan('class-announcements:process')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('expired')->and($notice->fresh()->pinned_at)->toBeNull();
});

test('pinning sorts live notices first and archiving restores only to a private draft', function () {
    $teacher = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($teacher, $student);
    foreach (['Pinned priority notice', 'Later ordinary notice'] as $title) {
        $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => $title, 'body' => $title]);
        $notice = ClassAnnouncement::query()->where('title', $title)->sole();
        $this->actingAs($teacher)->post(route('classes.channels.notices.publish', [$schoolClass, $channel, $notice]));
    }
    $pinned = ClassAnnouncement::query()->where('title', 'Pinned priority notice')->sole();
    $this->actingAs($teacher)->post(route('classes.channels.notices.pin', [$schoolClass, $channel, $pinned]))->assertRedirect();
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertSeeInOrder(['Pinned priority notice', 'Later ordinary notice']);
    $this->actingAs($teacher)->post(route('classes.channels.notices.archive', [$schoolClass, $channel, $pinned]))->assertRedirect();
    expect($pinned->fresh()->status)->toBe('archived');
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertDontSee('Pinned priority notice');
    $this->actingAs($teacher)->post(route('classes.channels.notices.restore', [$schoolClass, $channel, $pinned]))->assertRedirect();
    expect($pinned->fresh()->status)->toBe('draft')->and($pinned->fresh()->published_at)->toBeNull();
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertDontSee('Pinned priority notice');
});

test('student outsider and administrators without class membership cannot manage notices', function () {
    $teacher = noticeUser('teacher');
    $student = noticeUser('student');
    $outsider = noticeUser('teacher');
    $admin = noticeUser('admin');
    [$schoolClass, $channel] = noticeClass($teacher, $student);
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Protected notice', 'body' => 'Only staff publish'])->assertRedirect();
    $notice = ClassAnnouncement::query()->sole();
    foreach ([$student, $outsider, $admin] as $actor) {
        $this->actingAs($actor)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Intrusion', 'body' => 'No'])->assertForbidden();
        $this->actingAs($actor)->post(route('classes.channels.notices.publish', [$schoolClass, $channel, $notice]))->assertForbidden();
    }
    $this->actingAs($outsider)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertForbidden();
    expect(ClassAnnouncement::query()->count())->toBe(1);
});

test('notice mutation requires the exact class and announcement channel', function () {
    $teacher = noticeUser('teacher');
    [$schoolClass, $channel] = noticeClass($teacher);
    [$otherClass, $otherChannel] = noticeClass($teacher);
    $general = $schoolClass->channels()->create(['name' => 'General', 'slug' => 'general', 'created_by' => $teacher->id]);
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Scoped notice', 'body' => 'Scoped body']);
    $notice = ClassAnnouncement::query()->sole();

    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $general), ['title' => 'Wrong channel', 'body' => 'No'])->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.channels.notices.publish', [$schoolClass, $general, $notice]))->assertNotFound();
    $this->actingAs($teacher)->post(route('classes.channels.notices.publish', [$otherClass, $otherChannel, $notice]))->assertNotFound();
    $this->actingAs($teacher)->post(route('classes.channels.notices.draft', [$otherClass, $otherChannel, $notice]))->assertNotFound();
    expect($notice->fresh()->status)->toBe('draft');
});

test('archived classes pause due notices until an authorized teacher reschedules them', function () {
    $teacher = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($teacher, $student);
    $this->travelTo(now()->startOfMinute());
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Paused notice', 'body' => 'Wait for class'])->assertRedirect();
    $notice = ClassAnnouncement::query()->sole();
    $this->actingAs($teacher)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => now()->addMinute()->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    app(ClassManagementService::class)->setArchived($teacher, $schoolClass, true);
    $this->travelTo(now()->addMinutes(2));
    $this->artisan('class-announcements:process')->expectsOutputToContain('1 notice(s) paused')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('paused_archived')
        ->and($notice->fresh()->published_at)->toBeNull();
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertDontSee('Paused notice');
    $this->actingAs($student)->get(route('search.index', ['q' => 'Paused']))->assertOk()->assertDontSee('Paused notice</a>', false);
    $this->actingAs($teacher)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertSee('Publication paused.')->assertSee('The class was archived when this notice was due.');
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Blocked', 'body' => 'No'])->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.channels.notices.publish', [$schoolClass, $channel, $notice]))->assertForbidden();
    $this->actingAs($teacher)->post(route('classes.channels.notices.draft', [$schoolClass, $channel, $notice]))->assertForbidden();
    app(ClassManagementService::class)->setArchived($teacher, $schoolClass, false);
    $this->artisan('class-announcements:process')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('paused_archived');

    $nextPublishAt = now()->addMinute();
    $this->actingAs($teacher)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => $nextPublishAt->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    expect($notice->fresh()->status)->toBe('scheduled');
    $notificationsBeforePublish = $student->notifications()->count();
    $this->travelTo($nextPublishAt);
    $this->artisan('class-announcements:process')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('published')
        ->and($student->notifications()->count())->toBe($notificationsBeforePublish + 1);
});

test('invalid expiry and scheduling leave the notice unchanged', function () {
    $teacher = noticeUser('teacher');
    [$schoolClass, $channel] = noticeClass($teacher);
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Validation notice', 'body' => 'Valid body'])->assertRedirect();
    $notice = ClassAnnouncement::query()->sole();
    $this->actingAs($teacher)->patch(route('classes.channels.notices.update', [$schoolClass, $channel, $notice]), [
        'title' => 'Edited validation notice', 'body' => 'Revised body', 'expires_at' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    expect($notice->fresh()->title)->toBe('Edited validation notice');
    $this->actingAs($teacher)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => now()->addMinutes(10)->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors('expires_at');
    $this->actingAs($teacher)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => now()->subMinute()->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors('publish_at');
    $this->actingAs($teacher)->patch(route('classes.channels.notices.update', [$schoolClass, $channel, $notice]), [
        'title' => 'Validation notice', 'body' => 'Valid body', 'expires_at' => now()->subMinute()->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors('expires_at');
    expect($notice->fresh()->status)->toBe('draft')->and($notice->fresh()->expires_at)->not->toBeNull();
});

test('revoked authors pause due notices and a current teacher can return them to draft', function () {
    $owner = noticeUser('teacher');
    $coTeacher = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($owner, $student);
    $schoolClass->members()->attach($coTeacher, ['role' => 'teacher', 'joined_at' => now()]);
    $this->travelTo(now()->startOfMinute());
    $this->actingAs($coTeacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Revoked author notice', 'body' => 'Should stay private'])->assertRedirect();
    $notice = ClassAnnouncement::query()->sole();
    $this->actingAs($coTeacher)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => now()->addMinute()->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    expect($notice->fresh()->scheduled_by)->toBe($coTeacher->id);
    $notice->update(['scheduled_by' => null]);
    app(ClassManagementService::class)->remove($owner, $schoolClass, $coTeacher);
    $this->travelTo(now()->addMinutes(2));
    $this->artisan('class-announcements:process')->assertExitCode(0);

    expect($notice->fresh()->status)->toBe('paused_publisher')
        ->and($notice->fresh()->published_at)->toBeNull();
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertDontSee('Revoked author notice');
    $this->actingAs($owner)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertSee('The teacher responsible for scheduled publication no longer had permission');
    $this->actingAs($coTeacher)->post(route('classes.channels.notices.draft', [$schoolClass, $channel, $notice]))->assertForbidden();
    $this->actingAs($student)->post(route('classes.channels.notices.draft', [$schoolClass, $channel, $notice]))->assertForbidden();
    $this->actingAs($owner)->post(route('classes.channels.notices.draft', [$schoolClass, $channel, $notice]))->assertRedirect();
    expect($notice->fresh()->status)->toBe('draft')
        ->and($notice->fresh()->publish_at)->toBeNull()
        ->and($notice->fresh()->published_at)->toBeNull();
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertDontSee('Revoked author notice');
    $this->actingAs($owner)->post(route('classes.channels.notices.publish', [$schoolClass, $channel, $notice]))->assertRedirect();
    expect($notice->fresh()->status)->toBe('published')->and($student->notifications()->count())->toBe(1);
});

test('a current teacher can reschedule a paused notice while preserving its original author', function () {
    $owner = noticeUser('teacher');
    $originalAuthor = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($owner, $student);
    $schoolClass->members()->attach($originalAuthor, ['role' => 'teacher', 'joined_at' => now()]);
    $this->travelTo(now()->startOfMinute());
    $this->actingAs($originalAuthor)->post(noticeStoreUrl($schoolClass, $channel), [
        'title' => 'Rescheduled notice', 'body' => 'Reviewed by the owner',
    ])->assertRedirect();
    $notice = ClassAnnouncement::query()->sole();
    $this->actingAs($originalAuthor)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => now()->addMinute()->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    app(ClassManagementService::class)->remove($owner, $schoolClass, $originalAuthor);
    $this->travelTo(now()->addMinutes(2));
    $this->artisan('class-announcements:process')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('paused_publisher');

    $newPublishAt = now()->addMinute();
    $this->actingAs($owner)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => $newPublishAt->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    expect($notice->fresh()->status)->toBe('scheduled')
        ->and($notice->fresh()->author_id)->toBe($originalAuthor->id)
        ->and($notice->fresh()->scheduled_by)->toBe($owner->id);
    $this->actingAs($owner)->get(route('classes.channels.show', [$schoolClass, $channel]))
        ->assertOk()->assertSee('Scheduled by '.$owner->name);

    $this->travelTo($newPublishAt);
    $this->artisan('class-announcements:process')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('published')
        ->and($student->notifications()->count())->toBe(1);
    $this->actingAs($student)->get(route('classes.channels.show', [$schoolClass, $channel]))->assertOk()->assertSee('Rescheduled notice');
});

test('unavailable announcement channels pause due notices without repeated publication attempts', function () {
    $teacher = noticeUser('teacher');
    $student = noticeUser('student');
    [$schoolClass, $channel] = noticeClass($teacher, $student);
    $this->travelTo(now()->startOfMinute());
    $this->actingAs($teacher)->post(noticeStoreUrl($schoolClass, $channel), ['title' => 'Unavailable channel notice', 'body' => 'Keep private']);
    $notice = ClassAnnouncement::query()->sole();
    $this->actingAs($teacher)->post(route('classes.channels.notices.schedule', [$schoolClass, $channel, $notice]), [
        'publish_at' => now()->addMinute()->format('Y-m-d H:i:s'),
    ])->assertSessionHasNoErrors();
    $channel->delete();
    $this->travelTo(now()->addMinutes(2));
    $this->artisan('class-announcements:process')->assertExitCode(0);
    $this->artisan('class-announcements:process')->assertExitCode(0);
    expect($notice->fresh()->status)->toBe('paused_unavailable')
        ->and($notice->fresh()->published_at)->toBeNull()
        ->and($student->notifications()->count())->toBe(0);
});

test('demo seeder creates only a draft and the scheduler is registered', function () {
    $teacher = noticeUser('teacher');
    [$schoolClass, $channel] = noticeClass($teacher);
    $this->seed(ClassAnnouncementDemoSeeder::class);
    $this->seed(ClassAnnouncementDemoSeeder::class);
    expect($channel->notices()->count())->toBe(1)->and($channel->notices()->sole()->status)->toBe('draft');
    $this->artisan('schedule:list')->expectsOutputToContain('class-announcements:process')->assertExitCode(0);
});

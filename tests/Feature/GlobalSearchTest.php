<?php

use App\Models\ChatRoom;
use App\Models\ClassAnnouncement;
use App\Models\ClassMeeting;
use App\Models\CourseworkAssignment;
use App\Models\Message;
use App\Models\Quiz;
use App\Models\QuizAssignment;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannelMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function searchUser(string $role, string $name): User
{
    $record = Role::query()->firstOrCreate(['name' => $role], ['description' => $role, 'status' => true]);

    return User::factory()->onboarded()->create(['role_id' => $record->id, 'name' => $name]);
}

function searchClass(User $teacher, array $students = []): SchoolClass
{
    $class = SchoolClass::factory()->create(['created_by' => $teacher->id]);
    $class->members()->attach($teacher, ['role' => 'owner', 'joined_at' => now()]);
    foreach ($students as $student) {
        $class->members()->attach($student, ['role' => 'student', 'joined_at' => now()]);
    }

    return $class;
}

test('global search scopes classes people quizzes coursework and announcements before limiting results', function (): void {
    $teacher = searchUser('teacher', 'Teacher Alpha');
    $student = searchUser('student', 'Student Alpha');
    $otherStudent = searchUser('student', 'Private Alpha Person');
    $otherTeacher = searchUser('teacher', 'Private Alpha Teacher');
    $class = searchClass($teacher, [$student, $otherStudent]);
    $class->update(['name' => 'Shared Alpha Class']);
    $outside = searchClass($otherTeacher);
    $outside->update(['name' => 'Private Alpha Class']);

    $quiz = Quiz::query()->create(['school_class_id' => $class->id, 'creator_id' => $teacher->id, 'title' => 'Assigned Alpha Quiz', 'status' => 'published', 'published_at' => now()]);
    $assignment = QuizAssignment::query()->create(['quiz_id' => $quiz->id, 'school_class_id' => $class->id, 'assigned_by' => $teacher->id, 'starts_at' => now(), 'due_at' => now()->addDay(), 'status' => 'scheduled']);
    $assignment->students()->attach($student, ['assigned_at' => now()]);
    Quiz::query()->create(['school_class_id' => $class->id, 'creator_id' => $teacher->id, 'title' => 'Unassigned Alpha Quiz', 'status' => 'published', 'published_at' => now()]);
    CourseworkAssignment::factory()->create(['school_class_id' => $class->id, 'created_by' => $teacher->id, 'title' => 'Published Alpha Coursework', 'status' => 'published']);
    CourseworkAssignment::factory()->create(['school_class_id' => $class->id, 'created_by' => $teacher->id, 'title' => 'Draft Alpha Coursework', 'status' => 'draft']);
    CourseworkAssignment::factory()->create(['school_class_id' => $outside->id, 'created_by' => $otherTeacher->id, 'title' => 'Private Alpha Coursework', 'status' => 'published']);
    $channel = $class->channels()->create(['name' => 'Announcement', 'slug' => 'announcement', 'created_by' => $teacher->id, 'is_default' => true]);
    ClassAnnouncement::factory()->create(['school_class_channel_id' => $channel->id, 'author_id' => $teacher->id, 'title' => 'Published Alpha Notice', 'body' => 'Visible', 'status' => 'published', 'published_at' => now()->subMinute()]);
    ClassAnnouncement::factory()->create(['school_class_channel_id' => $channel->id, 'author_id' => $teacher->id, 'title' => 'Draft Alpha Notice', 'status' => 'draft']);
    ClassAnnouncement::factory()->create(['school_class_channel_id' => $channel->id, 'author_id' => $teacher->id, 'title' => 'Expired Alpha Notice', 'status' => 'published', 'published_at' => now()->subDay(), 'expires_at' => now()->subMinute()]);

    $this->actingAs($student)->get(route('search.index', ['q' => 'Alpha']))->assertOk()
        ->assertSee('aria-label="Search result categories"', false)
        ->assertSee('href="#search-section-coursework"', false)
        ->assertSee('Shared Alpha Class')->assertSee('Private Alpha Person')->assertSee('Assigned Alpha Quiz')
        ->assertSee('Published Alpha Coursework')->assertSee('Published Alpha Notice')
        ->assertDontSee('Private Alpha Class')->assertDontSee('Private Alpha Teacher')->assertDontSee('Unassigned Alpha Quiz')
        ->assertDontSee('Draft Alpha Coursework')->assertDontSee('Private Alpha Coursework')
        ->assertDontSee('Draft Alpha Notice')->assertDontSee('Expired Alpha Notice');

    $this->actingAs($teacher)->get(route('search.index', ['q' => 'Alpha']))->assertOk()
        ->assertSee('Unassigned Alpha Quiz')->assertSee('Draft Alpha Coursework')->assertSee('Draft Alpha Notice');

    $class->memberRecords()->where('user_id', $student->id)->delete();
    $this->actingAs($student)->get(route('search.index', ['q' => 'Alpha']))->assertOk()
        ->assertDontSee('Shared Alpha Class')->assertDontSee('Assigned Alpha Quiz')->assertDontSee('Published Alpha Coursework')
        ->assertDontSee('Published Alpha Notice')->assertDontSee('Private Alpha Person');
});

test('global search omits hidden deleted and attachment content while keeping visible room and class text', function (): void {
    $teacher = searchUser('teacher', 'Search Teacher');
    $student = searchUser('student', 'Search Student');
    $class = searchClass($teacher, [$student]);
    $channel = $class->channels()->create(['name' => 'Discussion', 'slug' => 'discussion', 'created_by' => $teacher->id]);
    SchoolClassChannelMessage::query()->create(['school_class_channel_id' => $channel->id, 'sender_id' => $teacher->id, 'body' => 'Visible comet class post']);
    $deletedClassMessage = SchoolClassChannelMessage::query()->create(['school_class_channel_id' => $channel->id, 'sender_id' => $teacher->id, 'body' => 'Deleted comet class post']);
    $deletedClassMessage->delete();
    $room = ChatRoom::query()->create(['type' => 'group', 'name' => 'Study group', 'created_by' => $teacher->id]);
    $room->members()->attach([$teacher->id, $student->id]);
    $visible = Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Visible comet chat post']);
    $hidden = Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Hidden comet chat post']);
    $hidden->hiddenByUsers()->create(['user_id' => $student->id]);
    Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Everyone deleted comet chat post', 'deleted_for_everyone_at' => now()]);
    $softDeleted = Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Soft deleted comet chat post']);
    $softDeleted->delete();
    Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'file', 'body' => 'Secret comet attachment metadata']);
    $otherRoom = ChatRoom::query()->create(['type' => 'group', 'created_by' => $teacher->id]);
    $otherRoom->members()->attach($teacher);
    Message::query()->create(['room_id' => $otherRoom->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Outside comet conversation']);

    $this->actingAs($student)->get(route('search.index', ['q' => 'comet']))->assertOk()
        ->assertSee('Visible comet class post')->assertSee('Visible comet chat post')
        ->assertDontSee('Deleted comet class post')->assertDontSee('Hidden comet chat post')
        ->assertDontSee('Everyone deleted comet chat post')->assertDontSee('Soft deleted comet chat post')
        ->assertDontSee('Secret comet attachment metadata')->assertDontSee('Outside comet conversation');
    $this->actingAs($teacher)->get(route('search.index', ['q' => 'comet']))->assertSee('Hidden comet chat post');
    $room->roomMembers()->where('user_id', $student->id)->delete();
    $this->actingAs($student)->get(route('search.index', ['q' => 'comet']))->assertDontSee('Visible comet chat post');
    expect($visible->exists)->toBeTrue();
});

test('search validates terms and treats wildcard characters literally', function (): void {
    $user = searchUser('student', 'Search Student');
    $teacher = searchUser('teacher', 'Search Teacher');
    $class = searchClass($teacher, [$user]);
    $class->update(['name' => '100% Science']);
    searchClass($teacher, [$user])->update(['name' => '100X Science']);

    $this->actingAs($user)->get(route('search.index'))->assertOk();
    $this->get(route('search.index', ['q' => 'x']))->assertSessionHasErrors('q');
    $this->get(route('search.index', ['q' => '  x  ']))->assertSessionHasErrors('q');
    $this->get(route('search.index', ['q' => '100%']))->assertOk()->assertSee('100% Science')->assertDontSee('100X Science');
});

test('search requires authentication and paginates matching results by category', function (): void {
    $this->get(route('search.index', ['q' => 'Bounded']))->assertRedirect(route('login'));
    $teacher = searchUser('teacher', 'Search Teacher');
    for ($number = 1; $number <= 12; $number++) {
        searchClass($teacher)->update(['name' => 'Bounded class '.$number]);
    }
    $response = $this->actingAs($teacher)->get(route('search.index', ['q' => 'Bounded']))->assertOk()
        ->assertSee('Showing 10 results per page')->assertHeader('Cache-Control', 'no-store, private')
        ->assertViewHas('results', fn (array $results): bool => $results['classes']->total() === 12 && $results['classes']->count() === 10)
        ->assertSee(route('search.index', ['q' => 'Bounded', 'category' => 'classes', 'page' => 2]));
    preg_match('/<section id="search-section-classes" class="search-result-section mb-4" aria-labelledby="search-classes">(.*?)<\/section>/s', $response->getContent(), $matches);
    expect(substr_count($matches[1] ?? '', 'Class</p>'))->toBe(10);
    $this->get(route('search.index', ['q' => 'Bounded', 'category' => 'classes', 'page' => 2]))->assertOk()
        ->assertViewHas('results', fn (array $results): bool => $results['classes']->currentPage() === 2 && $results['classes']->count() === 2);
    $this->get(route('search.index', ['q' => 'Bounded', 'category' => 'unknown']))->assertSessionHasErrors('category');
    $this->get(route('search.index', ['q' => 'Bounded', 'category' => 'classes', 'page' => 0]))->assertSessionHasErrors('page');
});

test('meeting search is scoped to current class members and links to the meeting', function (): void {
    $teacher = searchUser('teacher', 'Meeting Teacher');
    $student = searchUser('student', 'Meeting Student');
    $otherTeacher = searchUser('teacher', 'Other Teacher');
    $class = searchClass($teacher, [$student]);
    $outside = searchClass($otherTeacher);
    $visible = ClassMeeting::factory()->create([
        'school_class_id' => $class->id, 'created_by' => $teacher->id,
        'title' => 'Orion class review', 'description' => 'Orion preparation',
    ]);
    ClassMeeting::factory()->create([
        'school_class_id' => $outside->id, 'created_by' => $otherTeacher->id,
        'title' => 'Private Orion meeting',
    ]);

    $this->actingAs($student)->get(route('search.index', ['q' => 'Orion']))->assertOk()
        ->assertSee('Orion class review')->assertSee(route('classes.meetings.show', [$class, $visible]), false)
        ->assertDontSee('Private Orion meeting');
    $class->memberRecords()->where('user_id', $student->id)->delete();
    $this->get(route('search.index', ['q' => 'Orion']))->assertOk()->assertDontSee('Orion class review');
});

test('modern notices and legacy announcement messages share timestamp order across pages', function (): void {
    $teacher = searchUser('teacher', 'Announcement Teacher');
    $student = searchUser('student', 'Announcement Student');
    $class = searchClass($teacher, [$student]);
    $channel = $class->channels()->create(['name' => 'Announcement', 'slug' => 'announcement', 'created_by' => $teacher->id, 'is_default' => true]);
    $orderedTitles = [];
    $latestNotice = null;
    $latestLegacyMessage = null;
    for ($number = 1; $number <= 12; $number++) {
        $createdAt = now()->subMinutes($number);
        if ($number % 2 === 1) {
            $notice = ClassAnnouncement::factory()->create([
                'school_class_channel_id' => $channel->id, 'author_id' => $teacher->id,
                'title' => 'Nebula notice '.sprintf('%02d', $number), 'body' => 'Nebula details',
                'status' => 'published', 'published_at' => now()->subDay(),
            ]);
            $notice->forceFill(['created_at' => $createdAt])->save();
            $latestNotice ??= $notice;
        } else {
            $message = SchoolClassChannelMessage::query()->create([
                'school_class_channel_id' => $channel->id, 'sender_id' => $teacher->id,
                'body' => 'Nebula update '.sprintf('%02d', $number),
            ]);
            $message->forceFill(['created_at' => $createdAt])->save();
            $latestLegacyMessage ??= $message;
        }
        $orderedTitles[] = $number % 2 === 1 ? 'Nebula notice '.sprintf('%02d', $number) : 'Nebula update '.sprintf('%02d', $number);
    }
    $hiddenDraft = ClassAnnouncement::factory()->create([
        'school_class_channel_id' => $channel->id, 'author_id' => $teacher->id,
        'title' => 'Hidden Nebula draft', 'status' => 'draft',
    ]);

    $this->actingAs($student)->get(route('search.index', ['q' => 'Nebula']))->assertOk()
        ->assertViewHas('results', fn (array $results): bool => $results['announcements']->total() === 12
            && $results['announcements']->getCollection()->pluck('title')->all() === array_slice($orderedTitles, 0, 10))
        ->assertSee(route('classes.channels.show', [$class, $channel, 'notice_id' => $latestNotice->id]).'#notice-'.$latestNotice->id, false)
        ->assertSee(route('classes.channels.show', [$class, $channel, 'message_id' => $latestLegacyMessage->id]).'#class-message-'.$latestLegacyMessage->id, false)
        ->assertDontSee('Hidden Nebula draft');
    $this->get(route('search.index', ['q' => 'Nebula', 'category' => 'announcements', 'page' => 2]))->assertOk()
        ->assertViewHas('results', fn (array $results): bool => $results['announcements']->total() === 12
            && $results['announcements']->getCollection()->pluck('title')->all() === array_slice($orderedTitles, 10));
    $this->get(route('classes.channels.show', [$class, $channel, 'notice_id' => $latestNotice->id]))
        ->assertOk()->assertSee('id="notice-'.$latestNotice->id.'"', false);
    $this->get(route('classes.channels.show', [$class, $channel, 'message_id' => $latestLegacyMessage->id]))
        ->assertOk()->assertSee('id="class-message-'.$latestLegacyMessage->id.'"', false);
    $this->get(route('classes.channels.show', [$class, $channel, 'notice_id' => $hiddenDraft->id]))->assertNotFound();
});

test('room and class messages share timestamp order and link to supported destinations', function (): void {
    $teacher = searchUser('teacher', 'Chat Teacher');
    $student = searchUser('student', 'Chat Student');
    $class = searchClass($teacher, [$student]);
    $channel = $class->channels()->create(['name' => 'Discussion', 'slug' => 'discussion', 'created_by' => $teacher->id]);
    $room = ChatRoom::query()->create(['type' => 'group', 'name' => 'Study group', 'created_by' => $teacher->id]);
    $room->members()->attach([$teacher->id, $student->id]);
    $older = Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Meteor older chat']);
    $older->forceFill(['created_at' => now()->subMinutes(3)])->save();
    $middle = SchoolClassChannelMessage::query()->create(['school_class_channel_id' => $channel->id, 'sender_id' => $teacher->id, 'body' => 'Meteor class update']);
    $middle->forceFill(['created_at' => now()->subMinutes(2)])->save();
    $newest = Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Meteor recent chat']);
    $newest->forceFill(['created_at' => now()->subMinute()])->save();
    $hidden = Message::query()->create(['room_id' => $room->id, 'sender_id' => $teacher->id, 'message_type' => 'text', 'body' => 'Meteor hidden chat']);
    $hidden->hiddenByUsers()->create(['user_id' => $student->id]);

    $this->actingAs($student)->get(route('search.index', ['q' => 'Meteor']))->assertOk()
        ->assertViewHas('results', fn (array $results): bool => $results['chat']->getCollection()->pluck('title')->all() === [
            'Meteor recent chat', 'Meteor class update', 'Meteor older chat',
        ])
        ->assertSee(route('chat.index', ['room' => $room->id]), false)
        ->assertSee(route('classes.channels.show', [$class, $channel, 'message_id' => $middle->id]).'#class-message-'.$middle->id, false)
        ->assertDontSee('Meteor hidden chat');
    $this->get(route('classes.channels.show', [$class, $channel, 'message_id' => $middle->id]))
        ->assertOk()->assertSee('id="class-message-'.$middle->id.'"', false);
    $this->get(route('chat.index', ['room' => $room->id]))->assertOk()->assertSee('initialRoomId: '.$room->id, false);

    for ($number = 1; $number <= 10; $number++) {
        $extra = SchoolClassChannelMessage::query()->create([
            'school_class_channel_id' => $channel->id,
            'sender_id' => $teacher->id,
            'body' => 'Meteor extra '.sprintf('%02d', $number),
        ]);
        $extra->forceFill(['created_at' => now()->subMinutes($number + 3)])->save();
    }
    $this->get(route('search.index', ['q' => 'Meteor', 'category' => 'chat', 'page' => 2]))->assertOk()
        ->assertViewHas('results', fn (array $results): bool => $results['chat']->total() === 13
            && $results['chat']->currentPage() === 2 && $results['chat']->count() === 3);
});

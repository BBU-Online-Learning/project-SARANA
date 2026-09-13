<?php

use App\Events\Chat\ConversationUpdated;
use App\Events\Chat\MessageSent;
use App\Events\Chat\SidebarUpdated;
use App\Events\Chat\UnreadCountUpdated;
use App\Models\Attachment;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\MessageUserDeletion;
use App\Services\Chat\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('chat_private');
    Storage::fake('public');
    Event::fake([MessageSent::class, ConversationUpdated::class, SidebarUpdated::class, UnreadCountUpdated::class]);
    $this->sender = securityTestUser();
    $this->member = securityTestUser('teacher');
    $this->room = ChatRoom::create(['type' => 'group', 'created_by' => $this->sender->id]);
    $this->room->members()->attach([$this->sender->id, $this->member->id]);
    $this->message = $this->room->messages()->create(['sender_id' => $this->sender->id, 'body' => 'File test']);
    $this->actingAs($this->sender);
});

function chatTestAttachment(object $test, ?UploadedFile $file = null): Attachment
{
    return app(AttachmentService::class)->store($test->message, [
        $file ?? UploadedFile::fake()->image('photo.png', 40, 40),
    ])->sole()->fresh();
}

function chatTestWav(): UploadedFile
{
    $samples = str_repeat("\0", 800);
    $contents = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($samples)).$samples;

    return UploadedFile::fake()->createWithContent('voice.wav', $contents);
}

test('original thumbnail and download require login', function (string $variant): void {
    $attachment = chatTestAttachment($this);
    auth()->logout();
    $this->get(route('chat.attachments.'.$variant, $attachment))->assertRedirect(route('login'));
})->with(['show', 'thumbnail', 'download']);

test('outsiders including institutional administrators cannot retrieve attachments', function (string $role, string $variant): void {
    $attachment = chatTestAttachment($this);
    $this->actingAs(securityTestUser($role))->get(route('chat.attachments.'.$variant, $attachment))->assertForbidden();
})->with(['student', 'teacher', 'admin', 'super_admin'])->with(['show', 'thumbnail', 'download']);

test('members can upload and view private attachments in direct and group chats', function (string $type): void {
    $this->room->update(['type' => $type]);
    $image = UploadedFile::fake()->image('photo.png', 50, 50);
    $bytes = file_get_contents($image->getPathname());
    $this->postJson(route('chat.messages.store', $this->room), [
        'attachments' => [UploadedFile::fake()->createWithContent('class photo.PNG', $bytes)],
    ])->assertOk()->assertJsonPath('success', true);

    $attachment = Attachment::sole();
    $media = $attachment->getFirstMedia('attachment');
    expect($media->disk)->toBe('chat_private')
        ->and($media->conversions_disk)->toBe('chat_private')
        ->and($media->file_name)->toMatch('/^[a-f0-9-]{36}\\.png$/')
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($attachment->original_name)->toBe('class-photo.png')
        ->and($attachment->uploaded_by)->toBe($this->sender->id);
    Storage::disk('chat_private')->assertExists([$media->getPathRelativeToRoot(), $media->getPathRelativeToRoot('thumb')]);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    expect(Storage::disk('chat_private')->get($media->getPathRelativeToRoot()))->toBe($bytes);

    $this->actingAs($this->member)->get($attachment->url())->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get($attachment->thumbUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get(route('chat.attachments.download', $attachment))->assertDownload('class-photo.png');
    $html = $this->get('/chat/messages/'.$attachment->message_id.'/html')->assertOk()->json('html');
    expect($html)->toContain($attachment->url(), $attachment->thumbUrl())->not->toContain('/storage/');
    expect($media->getUrl())->toBe($attachment->url())
        ->and($media->getTemporaryUrl(now()->addMinute()))->toBe($attachment->url())
        ->and($media->toArray()['original_url'])->toBe($attachment->url());
    Event::assertDispatched(MessageSent::class);
})->with(['direct', 'group']);

test('previously authorized URLs stop working after membership removal', function (string $variant): void {
    $attachment = chatTestAttachment($this);
    $url = route('chat.attachments.'.$variant, $attachment);
    $this->actingAs($this->member)->get($url)->assertOk();
    $this->room->members()->detach($this->member);
    $this->get($url)->assertForbidden();
    $this->withHeader('Range', 'bytes=0-9')->get($url)->assertForbidden();
})->with(['show', 'thumbnail', 'download']);

test('suspended sessions cannot retrieve or upload attachments', function (): void {
    $attachment = chatTestAttachment($this);
    $this->actingAs($this->member);
    $this->member->update(['status' => 'suspended']);
    $this->get($attachment->url())->assertForbidden();
    $this->actingAs($this->member)->postJson(route('chat.messages.store', $this->room), [
        'attachments' => [UploadedFile::fake()->image('no.png')],
    ])->assertForbidden();
    expect(Attachment::count())->toBe(1);
});

test('onboarding cannot be bypassed through attachment URLs', function (array $attributes, string $route): void {
    $attachment = chatTestAttachment($this);
    $this->member->update($attributes);
    $this->actingAs($this->member)->get($attachment->url())->assertRedirect(route($route));
})->with([
    'two factor setup' => [['google2fa_enabled' => false], '2fa.setup'],
    'mandatory password change' => [['must_change_password' => true], 'password.change'],
]);

test('deleted hidden and cross-room attachment records are denied', function (string $state): void {
    $attachment = chatTestAttachment($this);
    match ($state) {
        'attachment' => $attachment->delete(),
        'message' => $this->message->delete(),
        'everyone' => $this->message->update(['deleted_for_everyone_at' => now()]),
        'room' => $this->room->delete(),
        'hidden' => MessageUserDeletion::create(['message_id' => $this->message->id, 'user_id' => $this->member->id]),
        'cross-room' => $attachment->update(['room_id' => ChatRoom::create(['type' => 'direct'])->id]),
    };
    $response = $this->actingAs($this->member)->get(route('chat.attachments.show', $attachment->id));
    $state === 'attachment' ? $response->assertNotFound() : $response->assertForbidden();
})->with(['attachment', 'message', 'everyone', 'room', 'hidden', 'cross-room']);

test('voice notes remain playable with authenticated byte ranges', function (): void {
    $this->postJson(route('chat.messages.store', $this->room), [
        'attachment_context' => 'voice',
        'attachments' => [chatTestWav()],
    ])->assertOk();
    $attachment = Attachment::sole();
    expect($attachment->message->message_type)->toBe('voice')
        ->and($attachment->getFirstMedia('attachment')->hasGeneratedConversion('thumb'))->toBeFalse();
    $response = $this->actingAs($this->member)->withHeader('Range', 'bytes=0-15')->get($attachment->url());
    $response->assertStatus(206)->assertHeader('Content-Length', '16')->assertHeader('Content-Range', 'bytes 0-15/844');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    $this->get($attachment->thumbUrl() ?? route('chat.attachments.thumbnail', $attachment))->assertNotFound();
});

test('documents use safe download headers even for malicious legacy display names', function (): void {
    $attachment = chatTestAttachment($this, UploadedFile::fake()->createWithContent('lesson.pdf', "%PDF-1.4\n%%EOF"));
    $attachment->update(['original_name' => "../bad\r\nX-Evil: yes.pdf"]);
    $response = $this->get($attachment->url());
    $response->assertOk()->assertDownload('bad-X-Evil-yes.pdf')
        ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'");
    expect($response->headers->get('X-Evil'))->toBeNull();
});

test('legacy public files are delivered with authorization without being moved or deleted', function (): void {
    $attachment = Attachment::create([
        'message_id' => $this->message->id, 'room_id' => $this->room->id, 'uploaded_by' => $this->sender->id,
        'original_name' => 'legacy.png', 'storage_path' => '', 'extension' => 'png', 'mime_type' => 'image/png',
    ]);
    $media = $attachment->addMedia(UploadedFile::fake()->image('legacy.png', 20, 20))
        ->storingConversionsOnDisk('public')->toMediaCollection('attachment', 'public');
    $paths = [$media->getPathRelativeToRoot(), $media->getPathRelativeToRoot('thumb')];
    $hash = hash_file('sha256', $media->getPath());
    $this->actingAs($this->member)->get($attachment->url())->assertOk();
    $this->get($attachment->thumbUrl())->assertOk();
    $this->room->members()->detach($this->member);
    $this->get($attachment->url())->assertForbidden();
    Storage::disk('public')->assertExists($paths);
    expect(hash_file('sha256', $media->getPath()))->toBe($hash)
        ->and($media->fresh()->disk)->toBe('public')
        ->and(Storage::disk('chat_private')->allFiles())->toBe([]);
});

test('missing files and unknown attachment IDs return not found without paths', function (): void {
    $attachment = chatTestAttachment($this);
    Storage::disk('chat_private')->delete($attachment->getFirstMedia('attachment')->getPathRelativeToRoot());
    $this->get($attachment->url())->assertNotFound();
    $this->get(route('chat.attachments.show', 999999))->assertNotFound();
});

test('unsupported disguised corrupt and oversized uploads are rejected before storage', function (string $kind): void {
    $file = match ($kind) {
        'html' => UploadedFile::fake()->createWithContent('page.html', '<html><script>alert(1)</script></html>'),
        'svg' => UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        'disguised' => UploadedFile::fake()->createWithContent('photo.png', '<?php echo "bad";'),
        'extension' => UploadedFile::fake()->image('picture.exe', 20, 20),
        'double-extension' => UploadedFile::fake()->image('shell.php.png', 20, 20),
        'corrupt' => UploadedFile::fake()->create('fake.png', 1, 'image/png'),
        'oversized' => UploadedFile::fake()->create('large.pdf', 20481, 'application/pdf'),
    };
    $this->postJson(route('chat.messages.store', $this->room), ['attachments' => [$file]])
        ->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
    expect(Attachment::count())->toBe(0)
        ->and(Message::count())->toBe(1)
        ->and(Storage::disk('chat_private')->allFiles())->toBe([]);
})->with(['html', 'svg', 'disguised', 'extension', 'double-extension', 'corrupt', 'oversized']);

test('file count and malformed payload limits reject the complete batch', function (): void {
    $files = array_map(fn (int $i) => UploadedFile::fake()->image("image-$i.png", 10, 10), range(1, 11));
    $this->postJson(route('chat.messages.store', $this->room), ['attachments' => $files])
        ->assertUnprocessable()->assertJsonValidationErrors('attachments');
    $this->postJson(route('chat.messages.store', $this->room), ['body' => ['not text'], 'attachments' => 'not files'])
        ->assertUnprocessable()->assertJsonValidationErrors(['body', 'attachments']);
    expect(Attachment::count())->toBe(0)->and(Storage::disk('chat_private')->allFiles())->toBe([]);
});

test('outsiders and removed members cannot upload files', function (bool $removed): void {
    $actor = $removed ? $this->member : securityTestUser();
    if ($removed) {
        $this->room->members()->detach($actor);
    }
    $this->actingAs($actor)->postJson(route('chat.messages.store', $this->room), [
        'attachments' => [UploadedFile::fake()->image('forbidden.png')],
    ])->assertForbidden();
    expect(Attachment::count())->toBe(0)->and(Storage::disk('chat_private')->allFiles())->toBe([]);
})->with([true, false]);

test('browser voice container MIME types are accepted without public copies', function (string $extension, string $mime): void {
    $this->postJson(route('chat.messages.store', $this->room), [
        'attachment_context' => 'voice',
        'attachments' => [UploadedFile::fake()->create('recording.'.$extension, 1, $mime)],
    ])->assertOk();
    $attachment = Attachment::sole();
    expect($attachment->message->message_type)->toBe('voice')
        ->and($attachment->getFirstMedia('attachment')->disk)->toBe('chat_private')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    ['webm', 'video/webm'], ['webm', 'audio/webm'], ['ogg', 'audio/ogg'],
    ['ogg', 'application/ogg'], ['m4a', 'video/mp4'], ['m4a', 'audio/mp4'],
]);

test('Office containers are checked without extraction and delivered as downloads', function (string $extension, bool $valid): void {
    $disk = Storage::disk('chat_private');
    $zip = new ZipArchive;
    $zip->open($disk->path('fixture.zip'), ZipArchive::CREATE);
    $entry = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
    $type = $extension === 'docx'
        ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
        : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';
    $zip->addFromString('[Content_Types].xml', '<Types><Override ContentType="'.$type.'" /></Types>');
    $zip->addFromString($valid ? $entry : 'unrelated.txt', '<document />');
    $zip->close();
    $bytes = $disk->get('fixture.zip');
    $disk->delete('fixture.zip');
    $response = $this->postJson(route('chat.messages.store', $this->room), [
        'attachments' => [UploadedFile::fake()->createWithContent('lesson.'.$extension, $bytes)],
    ]);
    if (! $valid) {
        $response->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        expect(Attachment::count())->toBe(0)->and($disk->allFiles())->toBe([]);

        return;
    }
    $response->assertOk();
    $attachment = Attachment::sole();
    $this->get($attachment->url())->assertOk()->assertDownload('lesson.'.$extension);
    expect($attachment->getFirstMedia('attachment')->hasGeneratedConversion('thumb'))->toBeFalse();
})->with(['docx', 'xlsx'])->with([true, false]);

test('audio URLs deny guests outsiders and removed members including range requests', function (): void {
    $attachment = chatTestAttachment($this, chatTestWav());
    auth()->logout();
    $this->get($attachment->url())->assertRedirect(route('login'));
    $this->actingAs(securityTestUser('super_admin'))->get($attachment->url())->assertForbidden();
    $this->actingAs($this->member)->get($attachment->url())->assertOk();
    $this->room->members()->detach($this->member);
    $this->withHeader('Range', 'bytes=10-20')->get($attachment->url())->assertForbidden();
});

test('selected video and audio files are not misclassified as recorded voice messages', function (): void {
    $cases = [
        ['lesson.mp4', 'video/mp4', 'video'],
        ['lesson.webm', 'video/webm', 'video'],
        ['podcast.mp3', 'audio/mpeg', 'file'],
    ];

    foreach ($cases as [$filename, $mime, $type]) {
        $this->postJson(route('chat.messages.store', $this->room), [
            'attachments' => [UploadedFile::fake()->create($filename, 1, $mime)],
        ])->assertOk();

        $attachment = Attachment::query()->latest('id')->firstOrFail();
        expect($attachment->message->message_type)->toBe($type);

        $html = $this->get('/chat/messages/'.$attachment->message_id.'/html')->assertOk()->json('html');
        if ($type === 'video') {
            expect($html)->toContain('<video', 'playsinline')->not->toContain('<strong>Voice message</strong>');
        } else {
            expect($html)->toContain('<audio', $filename)->not->toContain('<strong>Voice message</strong>');
        }
    }
});

test('invalid attachment batches and voice contexts are rejected before storage', function (): void {
    config()->set('chat.max_attachment_total_size_kb', 2);
    $files = array_map(
        fn (int $index) => UploadedFile::fake()->create("part-{$index}.pdf", 2, 'application/pdf'),
        range(1, 2),
    );

    $this->postJson(route('chat.messages.store', $this->room), ['attachments' => $files])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments');

    expect(Attachment::count())->toBe(0)
        ->and(Storage::disk('chat_private')->allFiles())->toBe([]);

    $this->postJson(route('chat.messages.store', $this->room), [
        'attachment_context' => 'voice',
        'attachments' => [UploadedFile::fake()->create('lesson.mp4', 1, 'video/mp4')],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachment_context');
});

test('attachment paths cannot escape their disk root', function (): void {
    $attachment = chatTestAttachment($this);
    $media = $attachment->getFirstMedia('attachment');
    $media->newQuery()->whereKey($media->id)->update(['file_name' => '../../outside.pdf']);
    $this->get($attachment->url())->assertNotFound();
});

test('attachment responses keep private cache headers for GET and HEAD', function (): void {
    $attachment = chatTestAttachment($this);
    foreach (['show', 'thumbnail', 'download'] as $variant) {
        foreach (['GET', 'HEAD'] as $method) {
            $response = $this->call($method, route('chat.attachments.'.$variant, $attachment))->assertOk();
            expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private')->not->toContain('public');
        }
    }
});

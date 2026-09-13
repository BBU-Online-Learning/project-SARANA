<?php

// app\Models\Attachment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Attachment extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'message_id',
        'room_id',
        'uploaded_by',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size',
        'extension',
    ];

    /*
    |--------------------------------------------------------------------------
    | MESSAGE
    |--------------------------------------------------------------------------
    */

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ROOM
    |--------------------------------------------------------------------------
    */

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOADER
    |--------------------------------------------------------------------------
    */

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    /*
|--------------------------------------------------------------------------
| MEDIA LIBRARY
|--------------------------------------------------------------------------
*/

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachment')
            ->useDisk('chat_private')
            ->storeConversionsOnDisk('chat_private')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (! in_array($media?->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return;
        }

        $this->addMediaConversion('thumb')
            ->format('jpg')
            ->nonOptimized()
            ->width(400)
            ->height(400)
            ->performOnCollections('attachment')
            ->nonQueued();
    }

    public function isImage(): bool
    {
        return Str::startsWith($this->mime_type ?? '', 'image/');
    }

    public function isDocument(): bool
    {
        return in_array(
            strtolower($this->extension),
            ['doc', 'docx', 'xls', 'xlsx']
        );
    }

    public function url(): ?string
    {
        return $this->getFirstMedia('attachment')
            ? route('chat.attachments.show', $this)
            : null;
    }

    public function thumbUrl(): ?string
    {
        if (! $this->isImage()) {
            return null;
        }

        return $this->getFirstMedia('attachment')
            ? route('chat.attachments.thumbnail', $this)
            : null;
    }

    public function humanSize(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function isAudio(): bool
    {
        return Str::startsWith($this->mime_type ?? '', 'audio/')
            || $this->mime_type === 'application/ogg'
            || in_array(
                strtolower($this->extension),
                ['ogg', 'oga', 'mp3', 'wav', 'm4a', 'aac', 'mpeg', 'mpga'],
                true
            );
    }

    public function isVideo(): bool
    {
        return Str::startsWith($this->mime_type ?? '', 'video/')
            && in_array(strtolower($this->extension), ['mp4', 'webm'], true);
    }

    public function fileIcon(): string
    {
        if ($this->isAudio()) {
            return 'ti ti-microphone';
        }

        return match (strtolower($this->extension)) {
            'pdf' => 'ti ti-file-type-pdf',
            'doc', 'docx' => 'ti ti-file-type-doc',
            'xls', 'xlsx' => 'ti ti-file-type-xls',
            'zip' => 'ti ti-file-zip',
            default => 'ti ti-file',
        };
    }
}

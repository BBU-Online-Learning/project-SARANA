<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAnnouncement extends Model
{
    /** @use HasFactory<\Database\Factories\ClassAnnouncementFactory> */
    use HasFactory;

    public const PAUSED_STATUSES = ['paused_archived', 'paused_publisher', 'paused_unavailable'];

    protected $fillable = [
        'school_class_channel_id', 'author_id', 'scheduled_by', 'title', 'body', 'status',
        'publish_at', 'published_at', 'expires_at', 'pinned_at', 'archived_at', 'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime', 'published_at' => 'datetime', 'expires_at' => 'datetime',
            'pinned_at' => 'datetime', 'archived_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SchoolClassChannel::class, 'school_class_channel_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by')->withTrashed();
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by')->withTrashed();
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')
            ->where('published_at', '<=', now())->whereNull('archived_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isVisible(): bool
    {
        return $this->status === 'published' && $this->published_at !== null
            && $this->published_at->lte(now()) && $this->archived_at === null
            && ($this->expires_at === null || $this->expires_at->gt(now()));
    }

    public function publicationPauseReason(): ?string
    {
        return match ($this->status) {
            'paused_archived' => 'The class was archived when this notice was due. Restore the class, then review and reschedule the notice or return it to draft.',
            'paused_publisher' => 'The teacher responsible for scheduled publication no longer had permission when this notice was due. A current class teacher can review and reschedule it or return it to draft.',
            'paused_unavailable' => 'The announcement channel or class was unavailable when this notice was due. Restore access before reviewing and rescheduling it.',
            default => null,
        };
    }
}

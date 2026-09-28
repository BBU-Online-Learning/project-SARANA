<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMeeting extends Model
{
    /** @use HasFactory<\Database\Factories\ClassMeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'school_class_id', 'created_by', 'series_key', 'title', 'description', 'recurrence',
        'occurrence_number', 'occurrence_count', 'original_starts_at', 'starts_at', 'ends_at',
        'status', 'rescheduled_at', 'rescheduled_by', 'cancelled_at', 'cancelled_by',
        'class_meeting_series_id', 'series_occurrence_on', 'series_override_at',
    ];

    protected function casts(): array
    {
        return [
            'original_starts_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'rescheduled_at' => 'datetime', 'cancelled_at' => 'datetime',
            'series_occurrence_on' => 'date', 'series_override_at' => 'datetime',
            'occurrence_number' => 'integer', 'occurrence_count' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ClassMeetingSeries::class, 'class_meeting_series_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function rescheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }
}

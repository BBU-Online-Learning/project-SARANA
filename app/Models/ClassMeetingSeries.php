<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassMeetingSeries extends Model
{
    /** @use HasFactory<\Database\Factories\ClassMeetingSeriesFactory> */
    use HasFactory;

    protected $fillable = [
        'school_class_id', 'created_by', 'series_key', 'title', 'description', 'recurrence',
        'weekdays', 'starts_on', 'ends_on', 'local_start_time', 'duration_minutes',
        'timezone', 'generated_through', 'status', 'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array', 'starts_on' => 'date', 'ends_on' => 'date',
            'generated_through' => 'date', 'cancelled_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ClassMeeting::class);
    }
}

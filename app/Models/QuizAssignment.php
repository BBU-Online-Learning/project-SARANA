<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\QuizAssignmentFactory> */
    use HasFactory;

    protected $fillable = ['quiz_id', 'school_class_id', 'assigned_by', 'instructions', 'starts_at', 'due_at', 'attempt_limit', 'time_limit_minutes', 'status', 'results_release', 'show_correct_answers', 'results_released_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'due_at' => 'datetime', 'results_released_at' => 'datetime', 'show_correct_answers' => 'boolean'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quiz_assignment_students')->withPivot('assigned_at')->withTimestamps();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function availabilityStatus(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }
        if (now()->lt($this->starts_at)) {
            return 'scheduled';
        }
        if (now()->gt($this->due_at) || $this->status === 'closed') {
            return 'closed';
        }

        return 'available';
    }

    public function resultsAreReleased(): bool
    {
        return $this->results_release === 'immediate'
            || ($this->results_release === 'after_due' && now()->gte($this->due_at))
            || ($this->results_release === 'manual' && $this->results_released_at !== null);
    }
}

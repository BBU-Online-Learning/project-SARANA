<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    /** @use HasFactory<\Database\Factories\QuizAttemptFactory> */
    use HasFactory;

    protected $fillable = ['quiz_assignment_id', 'user_id', 'attempt_number', 'status', 'started_at', 'deadline_at', 'submitted_at', 'graded_at', 'total_points', 'earned_points', 'percentage', 'feedback'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'deadline_at' => 'datetime', 'submitted_at' => 'datetime', 'graded_at' => 'datetime', 'total_points' => 'decimal:2', 'earned_points' => 'decimal:2', 'percentage' => 'decimal:2'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(QuizAssignment::class, 'quiz_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isExpired(): bool
    {
        return $this->deadline_at !== null && now()->gte($this->deadline_at);
    }
}

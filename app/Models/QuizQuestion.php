<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    /** @use HasFactory<\Database\Factories\QuizQuestionFactory> */
    use HasFactory;

    public const TYPES = ['multiple_choice', 'true_false', 'multiple_answer', 'short_answer'];

    protected $fillable = ['quiz_id', 'type', 'prompt', 'points', 'grading_mode', 'accepted_answers', 'case_sensitive', 'explanation', 'position'];

    protected function casts(): array
    {
        return ['points' => 'decimal:2', 'accepted_answers' => 'array', 'case_sensitive' => 'boolean'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class)->orderBy('position');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }
}

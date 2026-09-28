<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseworkGrade extends Model
{
    /** @use HasFactory<\Database\Factories\CourseworkGradeFactory> */
    use HasFactory;

    protected $fillable = ['coursework_submission_id', 'coursework_revision_id', 'graded_by', 'points_awarded', 'max_points_snapshot', 'feedback', 'change_reason'];

    protected function casts(): array
    {
        return ['points_awarded' => 'decimal:2', 'max_points_snapshot' => 'decimal:2'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CourseworkSubmission::class, 'coursework_submission_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(CourseworkRevision::class, 'coursework_revision_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by')->withTrashed();
    }
}

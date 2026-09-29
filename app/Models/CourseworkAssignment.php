<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseworkAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\CourseworkAssignmentFactory> */
    use HasFactory;

    protected $fillable = ['school_class_id', 'created_by', 'academic_year_id', 'subject_id', 'reporting_period_id', 'title', 'instructions', 'max_points', 'due_at', 'allow_resubmissions', 'status', 'published_at', 'closed_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'published_at' => 'datetime', 'closed_at' => 'datetime', 'allow_resubmissions' => 'boolean', 'max_points' => 'decimal:2'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CourseworkSubmission::class);
    }
}

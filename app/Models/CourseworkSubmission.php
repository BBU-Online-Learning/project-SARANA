<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseworkSubmission extends Model
{
    /** @use HasFactory<\Database\Factories\CourseworkSubmissionFactory> */
    use HasFactory;

    protected $fillable = ['coursework_assignment_id', 'student_id', 'status', 'latest_revision_number', 'last_submitted_at'];

    protected function casts(): array
    {
        return ['last_submitted_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CourseworkAssignment::class, 'coursework_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CourseworkRevision::class)->orderBy('revision_number');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(CourseworkGrade::class)->orderByDesc('id');
    }
}

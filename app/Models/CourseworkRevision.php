<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseworkRevision extends Model
{
    /** @use HasFactory<\Database\Factories\CourseworkRevisionFactory> */
    use HasFactory;

    protected $fillable = ['coursework_submission_id', 'revision_number', 'status', 'draft_slot', 'body', 'submitted_at', 'is_late'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'is_late' => 'boolean'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CourseworkSubmission::class, 'coursework_submission_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CourseworkAttachment::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(CourseworkGrade::class);
    }
}

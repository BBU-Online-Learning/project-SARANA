<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'join_code',
        'description',
        'created_by',
        'avatar',
        'academic_year_id',
        'grade_level_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects')->withTimestamps();
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class);
    }

    public function attendanceRegisters(): HasMany
    {
        return $this->hasMany(ClassAttendanceRegister::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(ClassMeeting::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherClassAssignment::class);
    }

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_class_members')
            ->withPivot([
                'role',
                'joined_at',
            ])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->members();
    }

    public function channels(): HasMany
    {
        return $this->hasMany(SchoolClassChannel::class, 'school_class_id')
            ->orderBy('sort_order');
    }

    public function memberRecords(): HasMany
    {
        return $this->hasMany(SchoolClassMember::class, 'school_class_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function quizAssignments(): HasMany
    {
        return $this->hasMany(QuizAssignment::class);
    }

    public function courseworkAssignments(): HasMany
    {
        return $this->hasMany(CourseworkAssignment::class);
    }

    public function assignments(): HasMany
    {
        return $this->quizAssignments();
    }

    public function attempts(): HasManyThrough
    {
        return $this->hasManyThrough(QuizAttempt::class, QuizAssignment::class);
    }
}

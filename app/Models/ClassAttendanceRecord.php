<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassAttendanceRecord extends Model
{
    /** @use HasFactory<\Database\Factories\ClassAttendanceRecordFactory> */
    use HasFactory;

    protected $fillable = ['class_attendance_register_id', 'student_id', 'student_class_enrollment_id', 'student_name_snapshot', 'status', 'note', 'marked_at', 'marked_by'];

    protected function casts(): array
    {
        return ['marked_at' => 'datetime'];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(ClassAttendanceRegister::class, 'class_attendance_register_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentClassEnrollment::class, 'student_class_enrollment_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(ClassAttendanceCorrection::class)->orderBy('corrected_at');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassAttendanceRegister extends Model
{
    /** @use HasFactory<\Database\Factories\ClassAttendanceRegisterFactory> */
    use HasFactory;

    protected $fillable = ['school_class_id', 'attendance_date', 'opened_by', 'roster_snapshot_at', 'reviewed_at', 'reviewed_by', 'finalized_at', 'finalized_by'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'roster_snapshot_at' => 'datetime', 'reviewed_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(ClassAttendanceRecord::class)->orderBy('student_name_snapshot');
    }
}

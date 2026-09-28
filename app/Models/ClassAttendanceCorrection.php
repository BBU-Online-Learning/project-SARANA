<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAttendanceCorrection extends Model
{
    /** @use HasFactory<\Database\Factories\ClassAttendanceCorrectionFactory> */
    use HasFactory;

    protected $fillable = ['class_attendance_record_id', 'previous_status', 'new_status', 'previous_note', 'new_note', 'reason', 'corrected_by', 'corrected_at'];

    protected function casts(): array
    {
        return ['corrected_at' => 'datetime'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ClassAttendanceRecord::class, 'class_attendance_record_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by')->withTrashed();
    }
}

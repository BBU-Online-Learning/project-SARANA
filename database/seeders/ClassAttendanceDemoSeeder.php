<?php

namespace Database\Seeders;

use App\Models\ClassAttendanceRegister;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassAttendanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Gate;

class ClassAttendanceDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolClass = SchoolClass::query()->whereNull('archived_at')->whereHas('studentEnrollments', fn ($query) => $query->whereNull('ended_at'))->first();
        if (! $schoolClass) {
            return;
        }

        $teacherId = $schoolClass->memberRecords()->whereIn('role', ['owner', 'teacher'])->value('user_id');
        $teacher = $teacherId ? User::query()->find($teacherId) : null;
        if ($teacher && Gate::forUser($teacher)->allows('create', [ClassAttendanceRegister::class, $schoolClass])) {
            app(ClassAttendanceService::class)->open($teacher, $schoolClass, now()->toDateString());
        }
    }
}

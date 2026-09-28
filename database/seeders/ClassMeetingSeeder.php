<?php

namespace Database\Seeders;

use App\Models\ClassMeeting;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassMeetingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Gate;

class ClassMeetingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolClass = SchoolClass::query()->whereNull('archived_at')->whereHas('memberRecords', fn ($query) => $query->whereIn('role', ['owner', 'teacher']))->first();
        if (! $schoolClass || $schoolClass->meetings()->exists()) {
            return;
        }

        $teacherId = $schoolClass->memberRecords()->whereIn('role', ['owner', 'teacher'])->value('user_id');
        $teacher = $teacherId ? User::query()->find($teacherId) : null;
        if (! $teacher || ! Gate::forUser($teacher)->allows('create', [ClassMeeting::class, $schoolClass])) {
            return;
        }

        $startsAt = now()->addWeek()->startOfHour();
        app(ClassMeetingService::class)->create($teacher, $schoolClass, [
            'title' => 'Example weekly class meeting',
            'description' => 'Update this schedule before sharing it with your class.',
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'ends_at' => $startsAt->copy()->addHour()->format('Y-m-d\TH:i'),
            'recurrence' => 'weekly',
            'occurrence_count' => 3,
        ]);
    }
}

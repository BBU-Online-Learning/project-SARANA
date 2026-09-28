<?php

namespace Database\Seeders;

use App\Models\ClassMeetingSeries;
use App\Models\SchoolClass;
use App\Services\ClassMeetingSeriesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ClassMeetingSeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $schoolClass = SchoolClass::query()->whereHas('memberRecords', fn ($query) => $query->where('role', 'owner'))->first();
        if ($schoolClass === null || $schoolClass->meetingSeries()->exists()) {
            return;
        }

        $series = ClassMeetingSeries::factory()->create([
            'school_class_id' => $schoolClass->id,
            'created_by' => $schoolClass->created_by,
            'starts_on' => CarbonImmutable::tomorrow(config('app.timezone'))->toDateString(),
        ]);
        app(ClassMeetingSeriesService::class)->replenish($series, CarbonImmutable::today(config('app.timezone'))->addDays(14));
    }
}

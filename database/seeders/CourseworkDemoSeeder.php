<?php

namespace Database\Seeders;

use App\Models\CourseworkAssignment;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class CourseworkDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolClass = SchoolClass::query()->whereHas('memberRecords', fn ($query) => $query->whereIn('role', ['owner', 'teacher']))->first();
        if (! $schoolClass) {
            return;
        }

        $teacherId = $schoolClass->memberRecords()->whereIn('role', ['owner', 'teacher'])->value('user_id');
        CourseworkAssignment::query()->firstOrCreate(
            ['school_class_id' => $schoolClass->id, 'title' => 'Demo written reflection'],
            ['created_by' => $teacherId, 'instructions' => 'Write a short reflection.', 'max_points' => 100, 'status' => 'draft'],
        );
    }
}

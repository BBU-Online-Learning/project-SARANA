<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\SubjectTeacherService;
use Illuminate\Database\Seeder;

class TeacherSubjectAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || SchoolClass::query()->where('name', 'Example subject teachers')->exists()) {
            return;
        }

        $teacherRole = Role::query()->firstOrCreate(['name' => Role::TEACHER], ['description' => 'Teacher', 'status' => true]);
        $owner = User::factory()->onboarded()->create(['role_id' => $teacherRole->id]);
        $teacher = User::factory()->onboarded()->create(['role_id' => $teacherRole->id]);
        $schoolClass = SchoolClass::factory()->create(['name' => 'Example subject teachers', 'created_by' => $owner->id]);
        $schoolClass->members()->attach($owner, ['role' => 'owner', 'joined_at' => now()]);
        $schoolClass->members()->attach($teacher, ['role' => 'teacher', 'joined_at' => now()]);
        $subject = Subject::factory()->create();
        $schoolClass->subjects()->attach($subject);
        app(SubjectTeacherService::class)->assign($owner, $schoolClass, $subject->id, $teacher->id);
    }
}

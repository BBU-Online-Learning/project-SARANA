<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! DB::table('school_classes')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            if (! DB::table('academic_years')->where('name', 'Legacy / unassigned')->exists()) {
                DB::table('academic_years')->insert([
                    'name' => 'Legacy / unassigned', 'status' => 'legacy', 'is_legacy' => true,
                    'updated_at' => $now, 'created_at' => $now,
                ]);
            }
            $legacyYearId = DB::table('academic_years')->where('name', 'Legacy / unassigned')->value('id');
            DB::table('school_classes')->whereNull('academic_year_id')->update(['academic_year_id' => $legacyYearId]);

            DB::table('school_class_members')->orderBy('id')->chunkById(200, function ($members) use ($now): void {
                $classYears = DB::table('school_classes')
                    ->whereIn('id', $members->pluck('school_class_id')->unique())
                    ->pluck('academic_year_id', 'id');
                foreach ($members as $member) {
                    $table = $member->role === 'student' ? 'student_class_enrollments' : 'teacher_class_assignments';
                    $key = ['school_class_id' => $member->school_class_id, 'user_id' => $member->user_id, 'active_slot' => 1];
                    if (DB::table($table)->where($key)->exists()) {
                        continue;
                    }
                    DB::table($table)->insert($key + ($table === 'teacher_class_assignments' ? ['role' => $member->role] : []) + [
                        'academic_year_id' => $classYears[$member->school_class_id],
                        'started_at' => $member->joined_at ?? $member->created_at ?? $now,
                        'source' => 'backfill',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Academic history backfill cannot be reversed without losing historical records.');
    }
};

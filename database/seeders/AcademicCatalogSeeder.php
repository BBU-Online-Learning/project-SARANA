<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class AcademicCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AcademicYear::query()->firstOrCreate(['name' => 'Demo academic year'], [
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'status' => 'planned',
        ]);
        GradeLevel::query()->firstOrCreate(['name' => 'Demo level'], ['sequence' => 65000]);
        Subject::query()->firstOrCreate(['code' => 'DEMO'], ['name' => 'Demo subject']);
    }
}

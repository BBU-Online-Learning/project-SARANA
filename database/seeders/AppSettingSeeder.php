<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppSetting::query()->firstOrCreate(
            ['key' => 'user_defaults'],
            ['value' => AppSetting::DEFAULT_PREFERENCES],
        );
    }
}

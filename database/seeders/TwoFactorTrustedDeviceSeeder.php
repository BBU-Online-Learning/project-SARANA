<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TwoFactorTrustedDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->info('Trusted browser records are created only after successful two-factor verification.');
    }
}

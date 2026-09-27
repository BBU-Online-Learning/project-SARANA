<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TwoFactorTrustedDevice>
 */
class TwoFactorTrustedDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'auth_version' => 0,
            'user_agent_hash' => hash('sha256', 'Pest test browser'),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}

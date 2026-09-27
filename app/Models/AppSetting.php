<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    /** @use HasFactory<\Database\Factories\AppSettingFactory> */
    use HasFactory;

    public const MESSAGE_TONES = [
        'classic' => 'Classic',
        'chime' => 'Chime',
        'pulse' => 'Pulse',
    ];

    public const CALL_TONES = [
        'classic' => 'Classic ring',
        'gentle' => 'Gentle ring',
        'bright' => 'Bright ring',
        'double' => 'Double ring',
        'warm' => 'Warm bell',
        'ascending' => 'Ascending ring',
    ];

    public const DEFAULT_PREFERENCES = [
        'message_sound' => true,
        'call_sound' => true,
        'message_popups' => true,
        'desktop_messages' => true,
        'larger_text' => false,
        'reduce_motion' => false,
        'message_tone' => 'chime',
        'call_tone' => 'classic',
    ];

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function defaults(): array
    {
        $saved = static::query()->where('key', 'user_defaults')->first()?->value ?? [];

        return array_replace(self::DEFAULT_PREFERENCES, array_intersect_key($saved, self::DEFAULT_PREFERENCES));
    }

    public static function forUser(User $user): array
    {
        return array_replace(self::defaults(), array_intersect_key($user->preferences ?? [], self::DEFAULT_PREFERENCES));
    }

    public static function normalize(array $preferences): array
    {
        foreach ($preferences as $key => $value) {
            if (is_bool(self::DEFAULT_PREFERENCES[$key] ?? null)) {
                $preferences[$key] = (bool) $value;
            }
        }

        return $preferences;
    }
}

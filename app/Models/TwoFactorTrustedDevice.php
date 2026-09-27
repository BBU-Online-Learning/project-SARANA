<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorTrustedDevice extends Model
{
    /** @use HasFactory<\Database\Factories\TwoFactorTrustedDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_hash',
        'auth_version',
        'user_agent_hash',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'auth_version' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

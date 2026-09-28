<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClassChannelRead extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolClassChannelReadFactory> */
    use HasFactory;

    protected $fillable = ['school_class_channel_id', 'user_id', 'last_read_message_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_message_id' => 'integer', 'last_read_at' => 'datetime'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SchoolClassChannel::class, 'school_class_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

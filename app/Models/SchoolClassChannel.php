<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClassChannel extends Model
{
    /** @use HasFactory<\Database\Factories\SchoolClassChannelFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_class_id',
        'name',
        'slug',
        'created_by',
        'description',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function isAnnouncement(): bool
    {
        return $this->is_default && $this->slug === 'announcement';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SchoolClassChannelMessage::class, 'school_class_channel_id');
    }

    public function notices(): HasMany
    {
        return $this->hasMany(ClassAnnouncement::class, 'school_class_channel_id');
    }

    public function readStates(): HasMany
    {
        return $this->hasMany(SchoolClassChannelRead::class, 'school_class_channel_id');
    }
}

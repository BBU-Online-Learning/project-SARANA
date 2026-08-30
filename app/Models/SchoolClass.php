<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'join_code',
        'description',
        'created_by',
        'avatar',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_class_members')
            ->withPivot([
                'role',
                'joined_at',
            ])
            ->withTimestamps();
    }
    public function users(): BelongsToMany
    {
        return $this->members();
    }

    public function channels(): HasMany
    {
        return $this->hasMany(SchoolClassChannel::class, 'school_class_id')
            ->orderBy('sort_order');
    }

    public function memberRecords(): HasMany
    {
        return $this->hasMany(SchoolClassMember::class, 'school_class_id');
    }
}

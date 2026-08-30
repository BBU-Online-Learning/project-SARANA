<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMembershipAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'school_class_id', 'actor_id', 'target_id', 'actor_role', 'action', 'old_role', 'new_role',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id')->withTrashed();
    }
}

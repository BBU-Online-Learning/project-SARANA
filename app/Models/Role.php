<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const TEACHER = 'teacher';

    public const STUDENT = 'student';

    public const NAMES = [self::SUPER_ADMIN, self::ADMIN, self::TEACHER, self::STUDENT];

    protected $fillable = ['name', 'description', 'status'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    /** @return list<string> */
    public static function manageableNames(User $actor): array
    {
        if ($actor->trashed() || $actor->status !== 'active' || ! $actor->role?->status) {
            return [];
        }

        return match ($actor->role->name) {
            self::SUPER_ADMIN => [self::ADMIN, self::TEACHER, self::STUDENT],
            self::ADMIN => [self::TEACHER, self::STUDENT],
            default => [],
        };
    }
}

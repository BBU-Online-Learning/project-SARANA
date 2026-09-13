<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'password',
        'profile',
        'phone',
        'bio',
        'google2fa_secret',
        'google2fa_enabled',
        'must_change_password',
        'last_login',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
        'two_factor_secret_encrypted',
        'two_factor_last_used_step',
        'two_factor_recovery_token_hash',
        'recovery_requested_by',
        'auth_version',
    ];

    protected function google2faSecret(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes): ?string => ! empty($attributes['two_factor_secret_encrypted'])
                ? Crypt::decryptString($attributes['two_factor_secret_encrypted']) : $value,
            set: fn ($value): array => [
                'google2fa_secret' => null,
                'two_factor_secret_encrypted' => $value === null ? null : Crypt::encryptString($value),
            ],
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
            'google2fa_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'auth_version' => 'integer',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function chatRooms()
    {
        return $this->belongsToMany(
            ChatRoom::class,
            'chat_room_members',
            'user_id',
            'room_id'
        )
            ->withPivot([
                'role',
                'joined_at',
                'last_read_at',
            ])
            ->withTimestamps();
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function messageReads()
    {
        return $this->hasMany(MessageRead::class);
    }

    public function initiatedCalls()
    {
        return $this->hasMany(CallSession::class, 'initiated_by');
    }

    public function callParticipations()
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function uploadedAttachments()
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    public function lastSeenLabel(): ?string
    {
        return $this->last_seen_at?->diffForHumans();
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim($this->name)) ?: [])
            ->filter()->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: '?';
    }

    public function profileUrl(): ?string
    {
        $profile = $this->profile;
        if (! is_string($profile) || $profile === '') {
            return null;
        }
        if (filter_var($profile, FILTER_VALIDATE_URL) && in_array(parse_url($profile, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $profile;
        }
        if (preg_match('~^/?storage/(images/users/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp))$~iD', $profile, $matches)) {
            return Storage::disk('public')->url($matches[1]);
        }
        if (preg_match('~^(images/users/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp))$~iD', $profile)) {
            return Storage::disk('public')->url($profile);
        }

        return null;
    }

    public function managedProfilePath(): ?string
    {
        $profile = $this->profile;
        if (! is_string($profile)) {
            return null;
        }
        $path = parse_url($profile, PHP_URL_PATH) ?: $profile;

        return preg_match('~^/?(?:storage/)?(images/users/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp))$~iD', $path, $matches)
            ? $matches[1] : null;
    }

    public function createdSchoolClasses()
    {
        return $this->hasMany(SchoolClass::class, 'created_by');
    }

    public function schoolClassMemberships()
    {
        return $this->hasMany(SchoolClassMember::class);
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'school_class_members')
            ->withPivot([
                'role',
                'joined_at',
            ])
            ->withTimestamps();
    }
}

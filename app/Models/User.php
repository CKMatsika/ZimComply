<?php

namespace App\Models;

use App\Domains\AuthAndTenant\Models\Membership;
use App\Domains\AuthAndTenant\Models\Tenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasUuid;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'mfa_secret',
        'mfa_enabled_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null;
    }
}

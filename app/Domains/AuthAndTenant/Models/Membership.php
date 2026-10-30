<?php

namespace App\Domains\AuthAndTenant\Models;

use App\Models\User;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'organisation_id',
        'role',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function isOwnerOrAdmin(): bool
    {
        return in_array($this->role, ['OWNER', 'ADMIN']);
    }
}

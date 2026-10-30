<?php

namespace App\Shared\Ai\Models;

use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiVerificationItem extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'ai_verification_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'proposed_payload' => 'array',
            'verified_payload' => 'array',
            'confidence_score' => 'float',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isVerified(): bool
    {
        return $this->status === 'VERIFIED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }
}

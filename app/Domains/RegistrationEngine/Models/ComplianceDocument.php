<?php

namespace App\Domains\RegistrationEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Models\User;
use App\Shared\Ai\Models\AiVerificationItem;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceDocument extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'compliance_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function obligationInstance(): BelongsTo
    {
        return $this->belongsTo(ObligationInstance::class, 'obligation_instance_id');
    }

    public function aiVerificationItem(): BelongsTo
    {
        return $this->belongsTo(AiVerificationItem::class, 'ai_verification_item_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'VERIFIED';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'PENDING';
    }
}

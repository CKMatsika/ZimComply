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
            'extracted_metadata' => 'array',
            'expiry_date' => 'date',
            'issue_date' => 'date',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function obligationInstance(): BelongsTo
    {
        return $this->belongsTo(ObligationInstance::class, 'obligation_instance_id');
    }

    public function filingOccurrence(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\ComplianceEngine\Models\FilingOccurrence::class, 'filing_occurrence_id');
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

    public function isRejected(): bool
    {
        return $this->verification_status === 'REJECTED';
    }

    public function isExtracted(): bool
    {
        return $this->processing_status === 'EXTRACTED';
    }

    public function formattedFileSize(): string
    {
        $bytes = $this->file_size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

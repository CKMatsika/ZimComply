<?php

namespace App\Domains\ComplianceEngine\Models;

use App\Domains\AuthAndTenant\Models\Organisation;
use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Models\User;
use App\Shared\Traits\HasTenant;
use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalEvidenceLink extends Model
{
    use HasTenant, HasUuid;

    protected $table = 'renewal_evidence_links';

    protected $guarded = [];

    public const PURPOSE_CURRENT_INSTRUMENT = 'CURRENT_INSTRUMENT';
    public const PURPOSE_PRIOR_INSTRUMENT = 'PRIOR_INSTRUMENT';
    public const PURPOSE_REPLACEMENT_INSTRUMENT = 'REPLACEMENT_INSTRUMENT';
    public const PURPOSE_APPLICATION_SUPPORTING = 'APPLICATION_SUPPORTING_EVIDENCE';
    public const PURPOSE_AUTHORITY_RECEIPT = 'AUTHORITY_RECEIPT';
    public const PURPOSE_REJECTION_CORRESPONDENCE = 'REJECTION_CORRESPONDENCE';
    public const PURPOSE_SUSPENSION_CANCELLATION = 'SUSPENSION_CANCELLATION_NOTICE';

    public const SUPPORTED_PURPOSES = [
        self::PURPOSE_CURRENT_INSTRUMENT => 'Current Active Instrument',
        self::PURPOSE_PRIOR_INSTRUMENT => 'Prior / Superseded Instrument',
        self::PURPOSE_REPLACEMENT_INSTRUMENT => 'Replacement Instrument (Renewal)',
        self::PURPOSE_APPLICATION_SUPPORTING => 'Supporting Application Evidence',
        self::PURPOSE_AUTHORITY_RECEIPT => 'Authority Receipt / Acknowledgement',
        self::PURPOSE_REJECTION_CORRESPONDENCE => 'Rejection / Query Correspondence',
        self::PURPOSE_SUSPENSION_CANCELLATION => 'Suspension or Cancellation Notice',
    ];

    protected function casts(): array
    {
        return [
            'is_superseded' => 'boolean',
            'superseded_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(StatutoryInstrument::class, 'statutory_instrument_id');
    }

    public function renewalAttempt(): BelongsTo
    {
        return $this->belongsTo(RenewalAttempt::class, 'renewal_attempt_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ComplianceDocument::class, 'compliance_document_id');
    }

    public function supersededByDocument(): BelongsTo
    {
        return $this->belongsTo(ComplianceDocument::class, 'superseded_by_document_id');
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }
}


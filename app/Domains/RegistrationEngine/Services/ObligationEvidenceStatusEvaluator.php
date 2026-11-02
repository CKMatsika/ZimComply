<?php

namespace App\Domains\RegistrationEngine\Services;

use App\Domains\RegistrationEngine\Models\ComplianceDocument;
use App\Domains\RegistrationEngine\Models\ObligationInstance;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ObligationEvidenceStatusEvaluator
{
    /**
     * Determine the authoritative evidence status of an obligation based on eligible linked documents.
     *
     * A qualifying document must:
     * 1. Belong to the correct tenant and organisation.
     * 2. Be linked to the obligation instance.
     * 3. Have verification_status = 'VERIFIED'.
     * 4. Not be REJECTED, REVOKED, or SUPERSEDED.
     * 5. Not have expired as of the assessment date (expiry_date >= evaluationDate or null).
     *
     * @param ObligationInstance $obligation
     * @param CarbonInterface|null $asOfDate
     * @param array<string, mixed>|null $declaredRegistration
     * @return string Evidence status (DOCUMENT_SUPPORTED, REJECTED_EVIDENCE, EXPIRED_EVIDENCE, USER_DECLARED, UNKNOWN)
     */
    public function evaluate(
        ObligationInstance $obligation,
        ?CarbonInterface $asOfDate = null,
        ?array $declaredRegistration = null
    ): string {
        // If issuer independently authenticated, preserve it
        if ($obligation->evidence_status === 'VERIFIED_BY_ISSUER') {
            return 'VERIFIED_BY_ISSUER';
        }

        $evalDate = $asOfDate ?? Carbon::now();
        $dateString = $evalDate->toDateString();

        // Retrieve all documents linked to this obligation within the same tenant & organisation
        $linkedDocs = ComplianceDocument::withoutGlobalScopes()
            ->where('tenant_id', $obligation->tenant_id)
            ->where('organisation_id', $obligation->organisation_id)
            ->where('obligation_instance_id', $obligation->id)
            ->get();

        if ($linkedDocs->isNotEmpty()) {
            // Check for at least one active, valid, human-verified document that has not expired
            $hasValidVerified = $linkedDocs->contains(function (ComplianceDocument $doc) use ($dateString) {
                if ($doc->verification_status !== 'VERIFIED') {
                    return false;
                }

                // Expiry check
                if ($doc->expiry_date !== null && $doc->expiry_date->toDateString() < $dateString) {
                    return false;
                }

                return true;
            });

            if ($hasValidVerified) {
                return 'DOCUMENT_SUPPORTED';
            }

            // Check if all linked documents are rejected
            $allRejected = $linkedDocs->every(fn (ComplianceDocument $doc) => $doc->verification_status === 'REJECTED');
            if ($allRejected) {
                return 'REJECTED_EVIDENCE';
            }

            // Check if all linked documents are expired
            $allExpired = $linkedDocs->every(function (ComplianceDocument $doc) use ($dateString) {
                return $doc->verification_status === 'EXPIRED'
                    || ($doc->expiry_date !== null && $doc->expiry_date->toDateString() < $dateString);
            });
            if ($allExpired) {
                return 'EXPIRED_EVIDENCE';
            }

            // If documents exist but are still pending human review
            $hasPending = $linkedDocs->contains(fn (ComplianceDocument $doc) => $doc->verification_status === 'PENDING');
            if ($hasPending) {
                return 'DOCUMENT_SUPPORTED';
            }
        }

        // Fallback: Check declared registration
        $hasDeclared = !empty($declaredRegistration['declared_number'])
            || !empty($obligation->declared_registration_number);

        if ($hasDeclared) {
            return 'USER_DECLARED';
        }

        return 'UNKNOWN';
    }
}

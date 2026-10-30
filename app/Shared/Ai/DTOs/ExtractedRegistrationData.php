<?php

namespace App\Shared\Ai\DTOs;

class ExtractedRegistrationData
{
    /**
     * @param string|null $companyName
     * @param string|null $registrationNumber
     * @param string|null $incorporationDate
     * @param array<string> $directors
     * @param string|null $taxClearanceNumber
     * @param float $confidenceScore (0.0 to 1.0)
     * @param string $sourceDocumentType (e.g. 'CR6', 'CR14', 'ITF263', 'INCORPORATION_CERT')
     * @param string $verificationStatus ('PENDING', 'VERIFIED', 'REJECTED')
     * @param string|null $verificationItemId ID in ai_verification_items table
     */
    public function __construct(
        public readonly ?string $companyName,
        public readonly ?string $registrationNumber,
        public readonly ?string $incorporationDate,
        public readonly array $directors = [],
        public readonly ?string $taxClearanceNumber = null,
        public readonly float $confidenceScore = 0.0,
        public readonly string $sourceDocumentType = 'UNKNOWN',
        public readonly string $verificationStatus = 'PENDING',
        public readonly ?string $verificationItemId = null
    ) {}

    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'registration_number' => $this->registrationNumber,
            'incorporation_date' => $this->incorporationDate,
            'directors' => $this->directors,
            'tax_clearance_number' => $this->taxClearanceNumber,
            'confidence_score' => $this->confidenceScore,
            'source_document_type' => $this->sourceDocumentType,
            'verification_status' => $this->verificationStatus,
            'verification_item_id' => $this->verificationItemId,
        ];
    }
}

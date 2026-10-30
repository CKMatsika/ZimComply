<?php

namespace App\Shared\Ai\DTOs;

class RoadmapExplanationRequest
{
    /**
     * @param string $entityType e.g. 'Private Limited Company (Pvt Ltd)', 'PBC'
     * @param string $industrySector e.g. 'Mining & Beneficiation', 'Information Technology'
     * @param array<string> $mandatorySteps e.g. ['CIPZ Name Search', 'ZIMRA BP Registration', 'NSSA Employer Registration']
     * @param string $userQuestion Specific question or context
     */
    public function __construct(
        public readonly string $entityType,
        public readonly string $industrySector,
        public readonly array $mandatorySteps,
        public readonly string $userQuestion = 'What are my mandatory statutory milestones and sequence?'
    ) {}

    public function toPromptString(): string
    {
        $stepsList = implode(', ', $this->mandatorySteps);

        return "Entity Type: {$this->entityType}\n"
            ."Industry Sector: {$this->industrySector}\n"
            ."Mandatory Statutory Steps: {$stepsList}\n"
            ."User Query: {$this->userQuestion}\n"
            ."Note: Provide statutory citations for Zimbabwe (e.g. COBE Act [Chapter 24:31], ZIMRA Revenue Act, NSSA Act [Chapter 17:04]).";
    }
}

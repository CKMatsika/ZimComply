<?php

namespace App\Shared\Ai\DTOs;

class RoadmapExplanationResponse
{
    /**
     * @param string $summary Executive plain-English roadmap summary
     * @param array<string> $statutoryCitations Validated Acts and statutory instruments
     * @param array<string, string> $deadlines Statutory deadlines per obligation
     * @param string $plainEnglishAdvice Practical guidance for the compliance officer
     * @param string $disclaimer Regulatory disclaimer (ADR-0005)
     * @param int $tokensUsed Metered tokens used in generation
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $statutoryCitations,
        public readonly array $deadlines,
        public readonly string $plainEnglishAdvice,
        public readonly string $disclaimer = 'Statutory guidance provided for informational advisory purposes only under ZimComply AI Governance (ADR-0005). Final statutory determination rests with ZIMRA, CIPZ, NSSA, PRAZ and relevant statutory authorities.',
        public readonly int $tokensUsed = 0
    ) {}

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'statutory_citations' => $this->statutoryCitations,
            'deadlines' => $this->deadlines,
            'plain_english_advice' => $this->plainEnglishAdvice,
            'disclaimer' => $this->disclaimer,
            'tokens_used' => $this->tokensUsed,
        ];
    }
}

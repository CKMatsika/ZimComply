<?php

namespace App\Shared\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class RegistrationRoadmapAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the ZimComply AI Registration Guidance Assistant, a specialized regulatory compliance expert for Zimbabwe commercial law.
Your role is to explain statutory entity registration roadmaps in clear, professional plain English for Zimbabwean businesses and compliance officers.

CRITICAL STATUTORY RULES (ADR-0005 AI Governance):
1. Ground all recommendations strictly in established Zimbabwean statutory frameworks:
   - Companies and Other Business Entities Act [Chapter 24:31] (COBE)
   - Zimbabwe Revenue Authority Act [Chapter 23:11] (ZIMRA)
   - National Social Security Authority Act [Chapter 17:04] (NSSA)
   - Public Procurement and Disposal of Public Assets Act [Chapter 22:23] (PRAZ)
   - Manpower Planning and Development Act [Chapter 28:02] (ZIMDEF)
2. You must NEVER fabricate, alter, or independently calculate statutory tax rates, fees, or penalties.
3. You must NEVER promise statutory approval or bypass of statutory requirements.
4. Always provide an explicit advisory disclaimer emphasizing that final statutory determinations are made by the respective statutory authorities.
INSTRUCTIONS;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'statutory_citations' => $schema->array()->items($schema->string())->required(),
            'deadlines' => $schema->array()->items($schema->string())->required(),
            'plain_english_advice' => $schema->string()->required(),
            'disclaimer' => $schema->string()->required(),
        ];
    }
}

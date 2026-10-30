<?php

namespace App\Shared\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class RegistrationDocumentExtractorAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the ZimComply Regulatory Document Extraction Agent.
Your responsibility is to extract key company registration entities from Zimbabwean corporate documents:
- Certificate of Incorporation (CIPZ)
- Form CR6 (Notice of Situation of Registered Office)
- Form CR14 / Form CR5 (List of Directors and Secretaries)
- ZIMRA Tax Clearance Certificate (ITF263)

CRITICAL GOVERNANCE RULES (ADR-0005):
1. Extract values exactly as they appear on the document. Do not extrapolate, guess, or synthesize company numbers.
2. If a field is not present or illegible, set its value to an empty string.
3. Compute an objective confidence_score between 0.00 and 1.00 based on legibility and completeness.
4. All extracted entities will undergo mandatory human verification before committing to permanent company registries.
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
            'company_name' => $schema->string()->required(),
            'registration_number' => $schema->string()->required(),
            'incorporation_date' => $schema->string()->required(),
            'directors' => $schema->array()->items($schema->string())->required(),
            'tax_clearance_number' => $schema->string()->required(),
            'confidence_score' => $schema->number()->required(),
        ];
    }
}

<?php

namespace App\Shared\Ai\Services;

class DataPrivacySanitizer
{
    /**
     * Regex patterns for sensitive Zimbabwean PII and authentication tokens.
     */
    protected array $patterns = [
        // Zimbabwe National ID format: e.g. 63-1234567 A 89 or 63-1234567A89
        'zim_national_id' => '/\b\d{2}-\d{6,7}\s*[A-Za-z]\s*\d{2}\b/',
        
        // Passwords in prompt / json payloads
        'password' => '/\bpassword\s*[:=]\s*["\']?[^"\'\s,;]+["\']?/i',
        
        // Secret keys & API keys
        'api_key' => '/\b(?:sk-[a-zA-Z0-9_-]{20,}|AIza[0-9A-Za-z_-]{35}|ghp_[a-zA-Z0-9]{36})\b/',

        // Credit / Debit card patterns (13-19 digits)
        'payment_card' => '/\b(?:\d{4}[ -]?){3}\d{4}\b/',
    ];

    /**
     * Sanitize plain text before sending to any AI provider gateway.
     *
     * @return array{
     *     text: string,
     *     redacted_count: int,
     *     redacted_types: array<string>
     * }
     */
    public function sanitizeText(string $text): array
    {
        $sanitized = $text;
        $totalRedactions = 0;
        $redactedTypes = [];

        foreach ($this->patterns as $type => $pattern) {
            $matchCount = preg_match_all($pattern, $sanitized);
            if ($matchCount > 0) {
                $sanitized = preg_replace($pattern, "[REDACTED_{$type}]", $sanitized);
                $totalRedactions += $matchCount;
                $redactedTypes[] = $type;
            }
        }

        return [
            'text' => $sanitized,
            'redacted_count' => $totalRedactions,
            'redacted_types' => array_unique($redactedTypes),
        ];
    }

    /**
     * Recursively sanitize an associative array payload.
     */
    public function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeText($value)['text'];
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}

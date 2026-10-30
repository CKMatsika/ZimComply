<?php

namespace Tests\Feature\Ai;

use App\Shared\Ai\Services\DataPrivacySanitizer;
use Tests\TestCase;

class DataPrivacySanitizerTest extends TestCase
{
    public function test_sanitizer_masks_zimbabwe_national_id_numbers(): void
    {
        $sanitizer = new DataPrivacySanitizer();

        $textWithId = 'Director Tatenda Chiweshe National ID is 63-1234567 A 89, born in Harare.';
        $result = $sanitizer->sanitizeText($textWithId);

        $this->assertEquals(1, $result['redacted_count']);
        $this->assertContains('zim_national_id', $result['redacted_types']);
        $this->assertStringNotContainsString('63-1234567 A 89', $result['text']);
        $this->assertStringContainsString('[REDACTED_zim_national_id]', $result['text']);
    }

    public function test_sanitizer_masks_api_keys_and_passwords(): void
    {
        $sanitizer = new DataPrivacySanitizer();

        $textWithSecrets = 'Connecting with key sk-proj-1234567890abcdefghijk and password: "SecretPassword123!" for database.';
        $result = $sanitizer->sanitizeText($textWithSecrets);

        $this->assertGreaterThanOrEqual(2, $result['redacted_count']);
        $this->assertStringNotContainsString('sk-proj-1234567890abcdefghijk', $result['text']);
        $this->assertStringNotContainsString('SecretPassword123!', $result['text']);
    }

    public function test_sanitizer_cleans_nested_payloads(): void
    {
        $sanitizer = new DataPrivacySanitizer();

        $payload = [
            'applicant' => 'Farai Mutasa',
            'id_number' => '42-9876543B12',
            'nested' => [
                'auth' => 'password: MySuperSecretPass!',
            ],
        ];

        $cleaned = $sanitizer->sanitizePayload($payload);

        $this->assertEquals('Farai Mutasa', $cleaned['applicant']);
        $this->assertEquals('[REDACTED_zim_national_id]', $cleaned['id_number']);
        $this->assertStringContainsString('[REDACTED_password]', $cleaned['nested']['auth']);
    }
}

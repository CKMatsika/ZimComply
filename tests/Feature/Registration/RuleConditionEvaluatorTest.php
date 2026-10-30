<?php

namespace Tests\Feature\Registration;

use App\Domains\RegistrationEngine\Exceptions\InvalidConditionException;
use App\Domains\RegistrationEngine\Services\RuleConditionEvaluator;
use Tests\TestCase;

class RuleConditionEvaluatorTest extends TestCase
{
    protected RuleConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new RuleConditionEvaluator();
    }

    public function test_evaluates_equality_and_inequality_operators(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            'has_physical_premises' => true,
        ];

        // equals
        $res1 = $this->evaluator->evaluate([
            'field' => 'entity_type',
            'operator' => 'equals',
            'value' => 'PRIVATE_LIMITED_COMPANY',
        ], $profile);
        $this->assertTrue($res1['matched']);
        $this->assertSame('MATCHED', $res1['status']);

        // not_equals
        $res2 = $this->evaluator->evaluate([
            'field' => 'entity_type',
            'operator' => 'not_equals',
            'value' => 'SOLE_PROPRIETORSHIP',
        ], $profile);
        $this->assertTrue($res2['matched']);

        $res3 = $this->evaluator->evaluate([
            'field' => 'entity_type',
            'operator' => 'equals',
            'value' => 'PBC',
        ], $profile);
        $this->assertFalse($res3['matched']);
        $this->assertSame('NOT_MATCHED', $res3['status']);
    }

    public function test_evaluates_numeric_comparisons(): void
    {
        $profile = [
            'employee_count' => 10,
            'annual_turnover_usd' => 50000.00,
        ];

        // greater_than_or_equal
        $res1 = $this->evaluator->evaluate([
            'field' => 'employee_count',
            'operator' => 'greater_than_or_equal',
            'value' => 1,
        ], $profile);
        $this->assertTrue($res1['matched']);

        // greater_than
        $res2 = $this->evaluator->evaluate([
            'field' => 'annual_turnover_usd',
            'operator' => 'greater_than',
            'value' => 25000.00,
        ], $profile);
        $this->assertTrue($res2['matched']);

        // less_than
        $res3 = $this->evaluator->evaluate([
            'field' => 'annual_turnover_usd',
            'operator' => 'less_than',
            'value' => 25000.00,
        ], $profile);
        $this->assertFalse($res3['matched']);

        // less_than_or_equal
        $res4 = $this->evaluator->evaluate([
            'field' => 'employee_count',
            'operator' => 'less_than_or_equal',
            'value' => 10,
        ], $profile);
        $this->assertTrue($res4['matched']);
    }

    public function test_evaluates_in_and_contains_operators(): void
    {
        $profile = [
            'jurisdiction_code' => 'ZW-HARARE',
            'tags' => ['MANUFACTURING', 'EXPORTER'],
            'company_name' => 'Acme Zimbabwe Steel Ltd',
        ];

        // in operator
        $res1 = $this->evaluator->evaluate([
            'field' => 'jurisdiction_code',
            'operator' => 'in',
            'value' => ['ZW-HARARE', 'ZW-BULAWAYO'],
        ], $profile);
        $this->assertTrue($res1['matched']);

        $res2 = $this->evaluator->evaluate([
            'field' => 'jurisdiction_code',
            'operator' => 'in',
            'value' => ['ZW-MUTARE', 'ZW-GWERU'],
        ], $profile);
        $this->assertFalse($res2['matched']);

        // contains operator on array
        $res3 = $this->evaluator->evaluate([
            'field' => 'tags',
            'operator' => 'contains',
            'value' => 'EXPORTER',
        ], $profile);
        $this->assertTrue($res3['matched']);

        // contains operator on string
        $res4 = $this->evaluator->evaluate([
            'field' => 'company_name',
            'operator' => 'contains',
            'value' => 'steel',
        ], $profile);
        $this->assertTrue($res4['matched']);
    }

    public function test_evaluates_all_and_any_logical_grouping(): void
    {
        $profile = [
            'employee_count' => 5,
            'pays_wages' => true,
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
        ];

        // 'all' operator (AND)
        $resAll = $this->evaluator->evaluate([
            'operator' => 'all',
            'conditions' => [
                ['field' => 'employee_count', 'operator' => 'greater_than_or_equal', 'value' => 1],
                ['field' => 'pays_wages', 'operator' => 'equals', 'value' => true],
            ],
        ], $profile);
        $this->assertTrue($resAll['matched']);

        // 'any' operator (OR)
        $resAny = $this->evaluator->evaluate([
            'operator' => 'any',
            'conditions' => [
                ['field' => 'employee_count', 'operator' => 'greater_than', 'value' => 100],
                ['field' => 'entity_type', 'operator' => 'equals', 'value' => 'PRIVATE_LIMITED_COMPANY'],
            ],
        ], $profile);
        $this->assertTrue($resAny['matched']);
    }

    public function test_missing_required_profile_field_produces_requires_information(): void
    {
        $profile = [
            'entity_type' => 'PRIVATE_LIMITED_COMPANY',
            // employee_count is missing!
        ];

        $res = $this->evaluator->evaluate([
            'field' => 'employee_count',
            'operator' => 'greater_than_or_equal',
            'value' => 1,
        ], $profile);

        $this->assertFalse($res['matched']);
        $this->assertSame('REQUIRES_INFORMATION', $res['status']);
        $this->assertContains('employee_count', $res['unresolved_fields']);
    }

    public function test_detects_contradictory_inputs_and_returns_requires_information(): void
    {
        $contradictoryProfile = [
            'employee_count' => 0,
            'pays_wages' => true, // Contradiction: 0 employees but paying wages
        ];

        $res = $this->evaluator->evaluate([
            'field' => 'employee_count',
            'operator' => 'greater_than_or_equal',
            'value' => 1,
        ], $contradictoryProfile);

        $this->assertFalse($res['matched']);
        $this->assertSame('REQUIRES_INFORMATION', $res['status']);
        $this->assertNotEmpty($res['contradictions']);
    }

    public function test_resolves_parameter_from_rule_version(): void
    {
        $profile = [
            'annual_turnover_usd' => 30000.00,
        ];

        $ruleParameters = [
            'compulsory_turnover_threshold_usd' => 25000.00,
        ];

        $res = $this->evaluator->evaluate([
            'field' => 'annual_turnover_usd',
            'operator' => 'greater_than_or_equal',
            'param' => 'compulsory_turnover_threshold_usd',
        ], $profile, $ruleParameters);

        $this->assertTrue($res['matched']);
        $this->assertSame('MATCHED', $res['status']);
    }

    public function test_throws_exception_on_unsupported_operator(): void
    {
        $this->expectException(InvalidConditionException::class);

        $this->evaluator->evaluate([
            'field' => 'entity_type',
            'operator' => 'sql_regex_match', // Invalid operator
            'value' => '.*',
        ], ['entity_type' => 'TEST']);
    }
}

<?php

namespace App\Domains\RegistrationEngine\Services;

use App\Domains\RegistrationEngine\Exceptions\InvalidConditionException;

class RuleConditionEvaluator
{
    /**
     * Supported condition operators.
     */
    public const SUPPORTED_OPERATORS = [
        'equals',
        'not_equals',
        'greater_than',
        'greater_than_or_equal',
        'less_than',
        'less_than_or_equal',
        'in',
        'contains',
        'all',
        'any',
    ];

    /**
     * Evaluate a structured condition tree or criteria dictionary against business profile inputs.
     *
     * @param array<string, mixed> $conditionOrCriteria Condition tree or criteria array
     * @param array<string, mixed> $profile Business profile inputs
     * @param array<string, mixed> $ruleParameters Approved rule version parameters
     * @return array{
     *     matched: bool,
     *     status: string,
     *     evaluated_conditions: array<int, string>,
     *     trigger_reasons: array<int, string>,
     *     unresolved_fields: array<int, string>,
     *     contradictions: array<int, string>
     * }
     */
    public function evaluate(array $conditionOrCriteria, array $profile, array $ruleParameters = []): array
    {
        $contradictions = $this->detectContradictions($profile);
        if (!empty($contradictions)) {
            return [
                'matched' => false,
                'status' => 'REQUIRES_INFORMATION',
                'evaluated_conditions' => ['Contradictory business profile inputs identified.'],
                'trigger_reasons' => ['Contradictory business profile inputs must be resolved before statutory determination.'],
                'unresolved_fields' => array_keys($contradictions),
                'contradictions' => $contradictions,
            ];
        }

        // If the criteria is in normalized dictionary form (e.g. entity_types, min_employees, min_annual_turnover_usd),
        // convert to structured condition tree
        $structured = $this->normalizeToConditionTree($conditionOrCriteria, $ruleParameters);

        $evaluatedLogs = [];
        $triggerReasons = [];
        $unresolvedFields = [];

        $matched = $this->evaluateConditionNode(
            $structured,
            $profile,
            $ruleParameters,
            $evaluatedLogs,
            $triggerReasons,
            $unresolvedFields
        );

        $status = 'MATCHED';
        if (!empty($unresolvedFields)) {
            $status = 'REQUIRES_INFORMATION';
            $matched = false;
        } elseif (!$matched) {
            $status = 'NOT_MATCHED';
        }

        return [
            'matched' => $matched,
            'status' => $status,
            'evaluated_conditions' => $evaluatedLogs,
            'trigger_reasons' => $triggerReasons,
            'unresolved_fields' => array_values(array_unique($unresolvedFields)),
            'contradictions' => [],
        ];
    }

    /**
     * Recursively evaluate a condition node.
     *
     * @param array<string, mixed> $node
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $ruleParameters
     * @param array<int, string> &$evaluatedLogs
     * @param array<int, string> &$triggerReasons
     * @param array<int, string> &$unresolvedFields
     */
    protected function evaluateConditionNode(
        array $node,
        array $profile,
        array $ruleParameters,
        array &$evaluatedLogs,
        array &$triggerReasons,
        array &$unresolvedFields
    ): bool {
        if (!isset($node['operator'])) {
            throw new InvalidConditionException($node, 'Missing required [operator] key in condition node.');
        }

        $operator = strtolower((string) $node['operator']);

        if (!in_array($operator, self::SUPPORTED_OPERATORS, true)) {
            throw new InvalidConditionException($node, "Unsupported operator [{$operator}].");
        }

        // Logical grouping operators: 'all' (AND) and 'any' (OR)
        if ($operator === 'all') {
            if (!isset($node['conditions']) || !is_array($node['conditions'])) {
                throw new InvalidConditionException($node, "Operator [all] requires a [conditions] array.");
            }

            if (empty($node['conditions'])) {
                return true;
            }

            $allMatched = true;
            foreach ($node['conditions'] as $subCondition) {
                $subResult = $this->evaluateConditionNode(
                    $subCondition,
                    $profile,
                    $ruleParameters,
                    $evaluatedLogs,
                    $triggerReasons,
                    $unresolvedFields
                );
                if (!$subResult) {
                    $allMatched = false;
                }
            }

            return $allMatched;
        }

        if ($operator === 'any') {
            if (!isset($node['conditions']) || !is_array($node['conditions'])) {
                throw new InvalidConditionException($node, "Operator [any] requires a [conditions] array.");
            }

            if (empty($node['conditions'])) {
                return true;
            }

            $anyMatched = false;
            foreach ($node['conditions'] as $subCondition) {
                $subResult = $this->evaluateConditionNode(
                    $subCondition,
                    $profile,
                    $ruleParameters,
                    $evaluatedLogs,
                    $triggerReasons,
                    $unresolvedFields
                );
                if ($subResult) {
                    $anyMatched = true;
                }
            }

            return $anyMatched;
        }

        // Leaf condition evaluation
        if (!isset($node['field'])) {
            throw new InvalidConditionException($node, "Leaf condition requires a [field] specification.");
        }

        $field = (string) $node['field'];
        $hasValue = array_key_exists($field, $profile) && $profile[$field] !== null;

        if (!$hasValue) {
            $unresolvedFields[] = $field;
            $evaluatedLogs[] = "Field [{$field}] is missing or null in business profile.";
            $triggerReasons[] = "Requires information: [{$field}] is required for statutory applicability determination.";
            return false;
        }

        $profileValue = $profile[$field];

        // Resolve target value: either from 'value' directly or from 'param' in rule parameters
        $targetValue = null;
        if (isset($node['value'])) {
            $targetValue = $node['value'];
        } elseif (isset($node['param'])) {
            $paramKey = (string) $node['param'];
            if (!array_key_exists($paramKey, $ruleParameters)) {
                throw new InvalidConditionException($node, "Rule parameter [{$paramKey}] not found in rule version.");
            }
            $targetValue = $ruleParameters[$paramKey];
        } else {
            throw new InvalidConditionException($node, "Leaf condition must specify either [value] or [param].");
        }

        $result = $this->applyLeafOperator($operator, $profileValue, $targetValue);

        $profileStr = is_scalar($profileValue) ? (string) $profileValue : json_encode($profileValue);
        $targetStr = is_scalar($targetValue) ? (string) $targetValue : json_encode($targetValue);
        $statusStr = $result ? 'PASSED' : 'FAILED';

        $evaluatedLogs[] = "Condition [{$field} {$operator} {$targetStr}] evaluated against profile value [{$profileStr}]: {$statusStr}";

        if ($result && isset($node['reason'])) {
            $triggerReasons[] = $node['reason'];
        } elseif ($result) {
            $triggerReasons[] = "Triggered by [{$field}] matching requirement ({$profileStr} {$operator} {$targetStr}).";
        }

        return $result;
    }

    /**
     * Apply leaf comparison operator.
     */
    protected function applyLeafOperator(string $operator, mixed $profileVal, mixed $targetVal): bool
    {
        return match ($operator) {
            'equals' => $this->compareEquals($profileVal, $targetVal),
            'not_equals' => !$this->compareEquals($profileVal, $targetVal),
            'greater_than' => is_numeric($profileVal) && is_numeric($targetVal) && ((float) $profileVal > (float) $targetVal),
            'greater_than_or_equal' => is_numeric($profileVal) && is_numeric($targetVal) && ((float) $profileVal >= (float) $targetVal),
            'less_than' => is_numeric($profileVal) && is_numeric($targetVal) && ((float) $profileVal < (float) $targetVal),
            'less_than_or_equal' => is_numeric($profileVal) && is_numeric($targetVal) && ((float) $profileVal <= (float) $targetVal),
            'in' => $this->compareIn($profileVal, $targetVal),
            'contains' => $this->compareContains($profileVal, $targetVal),
            default => throw new InvalidConditionException([], "Unsupported operator [{$operator}]."),
        };
    }

    protected function compareEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        if (is_string($a) && is_string($b)) {
            return strcasecmp(trim($a), trim($b)) === 0;
        }

        return $a === $b;
    }

    protected function compareIn(mixed $needle, mixed $haystack): bool
    {
        if (!is_array($haystack)) {
            return false;
        }

        // If needle is an array, check if any element of needle is in haystack
        if (is_array($needle)) {
            foreach ($needle as $item) {
                if ($this->compareIn($item, $haystack)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($haystack as $item) {
            if ($this->compareEquals($needle, $item)) {
                return true;
            }
        }

        return false;
    }

    protected function compareContains(mixed $haystack, mixed $needle): bool
    {
        if (is_array($haystack)) {
            return $this->compareIn($needle, $haystack);
        }

        if (is_string($haystack) && is_string($needle)) {
            return str_contains(strtolower($haystack), strtolower($needle));
        }

        return false;
    }

    /**
     * Check for explicit contradictions in business profile inputs.
     *
     * @param array<string, mixed> $profile
     * @return array<string, string>
     */
    protected function detectContradictions(array $profile): array
    {
        $contradictions = [];

        // 1. Employee count vs wage payment / has_employees
        if (isset($profile['employee_count']) && (int) $profile['employee_count'] === 0) {
            if (isset($profile['has_employees']) && (bool) $profile['has_employees'] === true) {
                $contradictions['employee_count'] = 'Profile reports 0 employee count but declares has_employees = true.';
            }
            if (isset($profile['pays_wages']) && (bool) $profile['pays_wages'] === true) {
                $contradictions['pays_wages'] = 'Profile reports 0 employees but declares pays_wages = true.';
            }
        }

        // 2. Incorporation status vs registration number
        if (isset($profile['incorporation_status']) && $profile['incorporation_status'] === 'NOT_INCORPORATED') {
            if (!empty($profile['company_registration_number'])) {
                $contradictions['incorporation_status'] = 'Profile declared status NOT_INCORPORATED but provided an existing company registration number.';
            }
        }

        // 3. Turnover declared as 0 or negative with VAT voluntary or large gross receipts
        if (isset($profile['annual_turnover_usd']) && (float) $profile['annual_turnover_usd'] < 0) {
            $contradictions['annual_turnover_usd'] = 'Annual turnover cannot be negative.';
        }

        return $contradictions;
    }

    /**
     * Normalize dictionary criteria (entity_types, min_employees, etc.) into structured condition tree.
     *
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $ruleParameters
     * @return array<string, mixed>
     */
    public function normalizeToConditionTree(array $criteria, array $ruleParameters = []): array
    {
        // If it's a condition node with an operator key, return as is so operator is validated
        if (isset($criteria['operator'])) {
            return $criteria;
        }

        $conditions = [];

        // 1. Entity type
        if (isset($criteria['entity_types'])) {
            $types = (array) $criteria['entity_types'];
            // If rule parameters specify supported entities, intersect or defer
            if (isset($ruleParameters['supported_entities']) && is_array($ruleParameters['supported_entities'])) {
                $types = array_values(array_intersect($types, $ruleParameters['supported_entities']));
            }
            $conditions[] = [
                'field' => 'entity_type',
                'operator' => 'in',
                'value' => $types,
                'reason' => 'Entity structure matches statutory category: ' . implode(', ', $types),
            ];
        }

        // 2. Minimum employees (Threshold can come from rule version parameter or criteria)
        if (isset($criteria['min_employees'])) {
            $threshold = $criteria['min_employees'];
            if (isset($ruleParameters['min_employees_for_compulsory_reg'])) {
                $threshold = $ruleParameters['min_employees_for_compulsory_reg'];
            }
            $conditions[] = [
                'field' => 'employee_count',
                'operator' => 'greater_than_or_equal',
                'value' => (int) $threshold,
                'reason' => "Employer engages >= {$threshold} employees.",
            ];
        }

        // 3. Minimum annual turnover in USD (Threshold can come from rule parameters)
        if (isset($criteria['min_annual_turnover_usd'])) {
            $threshold = (float) $criteria['min_annual_turnover_usd'];
            if (isset($ruleParameters['compulsory_turnover_threshold_usd'])) {
                $threshold = (float) $ruleParameters['compulsory_turnover_threshold_usd'];
            }

            // If voluntary registration is allowed, allow or_voluntary_declared
            if (!empty($criteria['or_voluntary_declared'])) {
                $conditions[] = [
                    'operator' => 'any',
                    'conditions' => [
                        [
                            'field' => 'annual_turnover_usd',
                            'operator' => 'greater_than_or_equal',
                            'value' => $threshold,
                            'reason' => "Annual taxable turnover meets or exceeds compulsory statutory threshold of USD {$threshold}.",
                        ],
                        [
                            'field' => 'wants_voluntary_vat',
                            'operator' => 'equals',
                            'value' => true,
                            'reason' => 'Voluntary VAT registration declared by business.',
                        ],
                    ],
                ];
            } else {
                $conditions[] = [
                    'field' => 'annual_turnover_usd',
                    'operator' => 'greater_than_or_equal',
                    'value' => $threshold,
                    'reason' => "Annual taxable turnover meets or exceeds statutory threshold of USD {$threshold}.",
                ];
            }
        }

        // 4. Jurisdiction codes
        if (isset($criteria['jurisdiction_codes'])) {
            $jurisdictions = (array) $criteria['jurisdiction_codes'];
            $conditions[] = [
                'field' => 'jurisdiction_code',
                'operator' => 'in',
                'value' => $jurisdictions,
                'reason' => 'Business operates within designated municipal jurisdiction: ' . implode(', ', $jurisdictions),
            ];
        }

        // 5. Industry sectors
        if (isset($criteria['industry_sectors'])) {
            $sectors = (array) $criteria['industry_sectors'];
            $conditions[] = [
                'field' => 'industry_sector',
                'operator' => 'in',
                'value' => $sectors,
                'reason' => 'Business sector covered: ' . implode(', ', $sectors),
            ];
        }

        // 6. Physical premises
        if (isset($criteria['has_physical_premises'])) {
            $conditions[] = [
                'field' => 'has_physical_premises',
                'operator' => 'equals',
                'value' => (bool) $criteria['has_physical_premises'],
                'reason' => 'Occupies physical commercial or industrial premises.',
            ];
        }

        // 7. Government tenders
        if (isset($criteria['intends_to_bid_government_tenders'])) {
            $conditions[] = [
                'field' => 'bids_on_government_tenders',
                'operator' => 'equals',
                'value' => (bool) $criteria['intends_to_bid_government_tenders'],
                'reason' => 'Entity participates or intends to bid on public procurement tenders.',
            ];
        }

        // 8. Licensed profession
        if (isset($criteria['licensed_profession_involved'])) {
            $conditions[] = [
                'field' => 'licensed_profession_involved',
                'operator' => 'equals',
                'value' => (bool) $criteria['licensed_profession_involved'],
                'reason' => 'Practicing regulated professional activities.',
            ];
        }

        // 9. New business formation
        if (isset($criteria['is_new_business']) && $criteria['is_new_business'] === true) {
            $conditions[] = [
                'field' => 'is_already_incorporated',
                'operator' => 'equals',
                'value' => false,
                'reason' => 'New business entity formation required.',
            ];
        }

        // 10. Wage payments
        if (isset($criteria['pays_wages']) && $criteria['pays_wages'] === true) {
            $conditions[] = [
                'field' => 'pays_wages',
                'operator' => 'equals',
                'value' => true,
                'reason' => 'Employer maintains a payroll and pays regular employee remuneration.',
            ];
        }

        // If no conditions were extracted, return an empty 'all' node which matches by default
        return [
            'operator' => 'all',
            'conditions' => $conditions,
        ];
    }
}

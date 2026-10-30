<?php

namespace App\Domains\RegistrationEngine\Services;

use App\Domains\RegistrationEngine\Exceptions\CircularDependencyException;
use App\Domains\RegistrationEngine\Exceptions\MissingPrerequisiteException;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use Illuminate\Support\Collection;

class DependencyGraph
{
    /**
     * Legal classification priority weights for deterministic ordering tie-breaking.
     * Lower number = higher priority.
     */
    public const CLASSIFICATION_PRIORITY = [
        'MANDATORY_STATUTORY' => 1,
        'CONDITIONAL_STATUTORY' => 2,
        'CBA_COLLECTIVE_AGREEMENT' => 3,
        'PROFESSIONAL_REGULATION' => 4,
        'TENDER_SPECIFIC' => 5,
        'OPTIONAL_ASSOCIATION' => 6,
    ];

    /**
     * Build and topologically sort a collection or array of obligation definitions using Kahn's algorithm.
     *
     * @param Collection<int, ObligationDefinition>|array<int, ObligationDefinition> $definitions
     * @param bool $strictPrerequisites If true, throws MissingPrerequisiteException for undefined codes
     * @return array<int, array{
     *     obligation: ObligationDefinition,
     *     sequence: int,
     *     priority: int,
     *     hard_prerequisites: array<int, string>,
     *     procedural_recommendations: array<int, string>
     * }>
     */
    public function sort(Collection|array $definitions, bool $strictPrerequisites = true): array
    {
        /** @var array<string, ObligationDefinition> $nodeMap */
        $nodeMap = [];
        foreach ($definitions as $def) {
            $nodeMap[$def->code] = $def;
        }

        if (empty($nodeMap)) {
            return [];
        }

        // 1. Build adjacency list and compute in-degrees
        /** @var array<string, array<int, string>> $adjacency */
        $adjacency = [];
        /** @var array<string, int> $inDegree */
        $inDegree = [];
        /** @var array<string, array{hard: array<int, string>, procedural: array<int, string>}> $prereqMeta */
        $prereqMeta = [];

        foreach ($nodeMap as $code => $def) {
            $adjacency[$code] = [];
            $inDegree[$code] = 0;
            $prereqMeta[$code] = [
                'hard' => [],
                'procedural' => [],
            ];
        }

        // Also build a catalog of all known codes (for missing prerequisite check)
        $allKnownCodes = $this->resolveAllKnownCodes($nodeMap);

        foreach ($nodeMap as $targetCode => $def) {
            $rawPrereqs = (array) ($def->prerequisites ?? []);

            foreach ($rawPrereqs as $raw) {
                $prereqCode = is_array($raw) ? ($raw['code'] ?? null) : (string) $raw;
                $isHard = is_array($raw) ? (($raw['type'] ?? 'HARD_LEGAL') === 'HARD_LEGAL') : true;

                if (!$prereqCode) {
                    continue;
                }

                // Verify prerequisite code exists in known catalog
                if (!in_array($prereqCode, $allKnownCodes, true)) {
                    if ($strictPrerequisites) {
                        throw new MissingPrerequisiteException($targetCode, $prereqCode);
                    }
                }

                if ($isHard) {
                    $prereqMeta[$targetCode]['hard'][] = $prereqCode;
                } else {
                    $prereqMeta[$targetCode]['procedural'][] = $prereqCode;
                }

                // If the prerequisite is also part of the current active node set, add an edge: prereq -> target
                // Note: Only HARD_LEGAL prerequisites establish blocking topological order dependencies.
                if ($isHard && isset($nodeMap[$prereqCode])) {
                    $adjacency[$prereqCode][] = $targetCode;
                    $inDegree[$targetCode]++;
                }
            }
        }

        // 2. Initialize queue with in-degree 0 nodes
        $zeroInDegreeNodes = [];
        foreach ($inDegree as $code => $deg) {
            if ($deg === 0) {
                $zeroInDegreeNodes[] = $code;
            }
        }

        // Deterministic sort for the initial queue
        $this->sortNodesDeterministically($zeroInDegreeNodes, $nodeMap);

        // 3. Process queue with Kahn's algorithm
        $sortedCodes = [];
        while (!empty($zeroInDegreeNodes)) {
            $currentCode = array_shift($zeroInDegreeNodes);
            $sortedCodes[] = $currentCode;

            // Collect newly freed neighbors
            $newlyFreed = [];
            foreach ($adjacency[$currentCode] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $newlyFreed[] = $neighbor;
                }
            }

            if (!empty($newlyFreed)) {
                $this->sortNodesDeterministically($newlyFreed, $nodeMap);
                foreach ($newlyFreed as $freed) {
                    $zeroInDegreeNodes[] = $freed;
                }
                // Re-sort queue to maintain deterministic order
                $this->sortNodesDeterministically($zeroInDegreeNodes, $nodeMap);
            }
        }

        // 4. Cycle detection
        if (count($sortedCodes) < count($nodeMap)) {
            $unresolved = array_keys(array_filter($inDegree, fn (int $d) => $d > 0));
            $cyclePath = $this->traceCycle($unresolved, $adjacency);
            throw new CircularDependencyException($cyclePath);
        }

        // 5. Build final structured result with sequence numbers
        $result = [];
        $seq = 1;
        foreach ($sortedCodes as $code) {
            $def = $nodeMap[$code];
            $priority = self::CLASSIFICATION_PRIORITY[$def->legal_classification] ?? 99;

            $result[] = [
                'obligation' => $def,
                'sequence' => $seq++,
                'priority' => $priority,
                'hard_prerequisites' => $prereqMeta[$code]['hard'],
                'procedural_recommendations' => $prereqMeta[$code]['procedural'],
            ];
        }

        return $result;
    }

    /**
     * Sort nodes deterministically:
     * 1. Legal classification priority (MANDATORY_STATUTORY first, etc.)
     * 2. Obligation code alphabetically
     *
     * @param array<int, string> &$codes
     * @param array<string, ObligationDefinition> $nodeMap
     */
    protected function sortNodesDeterministically(array &$codes, array $nodeMap): void
    {
        usort($codes, function (string $a, string $b) use ($nodeMap) {
            $defA = $nodeMap[$a];
            $defB = $nodeMap[$b];

            $priorityA = self::CLASSIFICATION_PRIORITY[$defA->legal_classification] ?? 99;
            $priorityB = self::CLASSIFICATION_PRIORITY[$defB->legal_classification] ?? 99;

            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return strcmp($a, $b);
        });
    }

    /**
     * Trace cycle path among remaining nodes with positive in-degree.
     *
     * @param array<int, string> $unresolved
     * @param array<string, array<int, string>> $adjacency
     * @return array<int, string>
     */
    protected function traceCycle(array $unresolved, array $adjacency): array
    {
        $visited = [];
        $stack = [];

        foreach ($unresolved as $startNode) {
            $path = $this->dfsFindCycle($startNode, $adjacency, $visited, $stack);
            if (!empty($path)) {
                return $path;
            }
        }

        // Fallback: return unresolved set
        return $unresolved;
    }

    /**
     * @param string $current
     * @param array<string, array<int, string>> $adjacency
     * @param array<string, bool> &$visited
     * @param array<int, string> &$stack
     * @return array<int, string>
     */
    protected function dfsFindCycle(string $current, array $adjacency, array &$visited, array &$stack): array
    {
        $index = array_search($current, $stack, true);
        if ($index !== false) {
            // Found loop in stack
            $cycle = array_slice($stack, $index);
            $cycle[] = $current;
            return $cycle;
        }

        if (isset($visited[$current])) {
            return [];
        }

        $visited[$current] = true;
        $stack[] = $current;

        foreach ($adjacency[$current] ?? [] as $neighbor) {
            $result = $this->dfsFindCycle($neighbor, $adjacency, $visited, $stack);
            if (!empty($result)) {
                return $result;
            }
        }

        array_pop($stack);
        return [];
    }

    /**
     * Resolve all known obligation codes from the database or provided map.
     *
     * @param array<string, ObligationDefinition> $nodeMap
     * @return array<int, string>
     */
    protected function resolveAllKnownCodes(array $nodeMap): array
    {
        $databaseCodes = ObligationDefinition::query()->pluck('code')->all();

        return array_values(array_unique(array_merge(array_keys($nodeMap), $databaseCodes)));
    }
}

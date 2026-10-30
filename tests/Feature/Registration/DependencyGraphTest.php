<?php

namespace Tests\Feature\Registration;

use App\Domains\RegistrationEngine\Exceptions\CircularDependencyException;
use App\Domains\RegistrationEngine\Exceptions\MissingPrerequisiteException;
use App\Domains\RegistrationEngine\Models\ObligationDefinition;
use App\Domains\RegistrationEngine\Services\DependencyGraph;
use Database\Seeders\RegulatoryCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DependencyGraphTest extends TestCase
{
    use RefreshDatabase;

    protected DependencyGraph $graph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RegulatoryCatalogSeeder::class);
        $this->graph = new DependencyGraph();
    }

    public function test_topological_sort_returns_deterministic_sequence(): void
    {
        // Fetch real seeded definitions
        $definitions = ObligationDefinition::whereIn('code', [
            'CIPZ_INCORP',
            'ZIMRA_TIN_REG',
            'NSSA_EMPLOYER_REG',
            'ZIMDEF_LEVY_REG',
            'ITF263_CLEARANCE',
            'PRAZ_SUPPLIER_REG',
        ])->get();

        $sorted = $this->graph->sort($definitions);

        $codes = array_map(fn ($item) => $item['obligation']->code, $sorted);

        // CIPZ_INCORP has no prerequisites -> must be 1st
        $this->assertSame('CIPZ_INCORP', $codes[0]);

        // ZIMRA_TIN_REG requires CIPZ_INCORP -> must appear after CIPZ_INCORP
        $cipzIdx = array_search('CIPZ_INCORP', $codes, true);
        $tinIdx = array_search('ZIMRA_TIN_REG', $codes, true);
        $this->assertGreaterThan($cipzIdx, $tinIdx);

        // ITF263 requires ZIMRA_TIN_REG -> must appear after ZIMRA_TIN_REG
        $itfIdx = array_search('ITF263_CLEARANCE', $codes, true);
        $this->assertGreaterThan($tinIdx, $itfIdx);

        // PRAZ requires CIPZ, TIN, ITF263, NSSA -> must appear after all of them
        $prazIdx = array_search('PRAZ_SUPPLIER_REG', $codes, true);
        $this->assertGreaterThan($itfIdx, $prazIdx);

        // Verify sequence numbers are sequential (1, 2, 3...)
        $sequences = array_column($sorted, 'sequence');
        $this->assertSame([1, 2, 3, 4, 5, 6], $sequences);
    }

    public function test_detects_circular_dependencies_and_throws_exception(): void
    {
        // Create 3 cyclic obligation definitions
        $defA = new ObligationDefinition([
            'code' => 'CYCLE_A',
            'title' => 'Cycle A',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'prerequisites' => ['CYCLE_C'],
        ]);
        $defB = new ObligationDefinition([
            'code' => 'CYCLE_B',
            'title' => 'Cycle B',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'prerequisites' => ['CYCLE_A'],
        ]);
        $defC = new ObligationDefinition([
            'code' => 'CYCLE_C',
            'title' => 'Cycle C',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'prerequisites' => ['CYCLE_B'],
        ]);

        $this->expectException(CircularDependencyException::class);

        $this->graph->sort([$defA, $defB, $defC], strictPrerequisites: false);
    }

    public function test_detects_missing_prerequisite_definition_and_throws_exception(): void
    {
        $defA = new ObligationDefinition([
            'code' => 'ORPHAN_TASK',
            'title' => 'Task with non-existent prerequisite',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'prerequisites' => ['COMPLETELY_NON_EXISTENT_PREREQUISITE_XYZ'],
        ]);

        $this->expectException(MissingPrerequisiteException::class);

        $this->graph->sort([$defA], strictPrerequisites: true);
    }

    public function test_distinguishes_hard_legal_prerequisites_from_procedural_recommendations(): void
    {
        $defA = new ObligationDefinition([
            'code' => 'ITEM_A',
            'title' => 'Item A',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'prerequisites' => [],
        ]);

        $defB = new ObligationDefinition([
            'code' => 'ITEM_B',
            'title' => 'Item B',
            'legal_classification' => 'MANDATORY_STATUTORY',
            'prerequisites' => [
                ['code' => 'ITEM_A', 'type' => 'PROCEDURAL_RECOMMENDATION'],
            ],
        ]);

        $sorted = $this->graph->sort([$defA, $defB], strictPrerequisites: false);

        $bItem = collect($sorted)->firstWhere('obligation.code', 'ITEM_B');
        $this->assertEmpty($bItem['hard_prerequisites']);
        $this->assertContains('ITEM_A', $bItem['procedural_recommendations']);
    }

    public function test_deterministic_ordering_is_repeatable(): void
    {
        $definitions = ObligationDefinition::whereIn('code', [
            'CIPZ_INCORP',
            'ZIMRA_TIN_REG',
            'NSSA_EMPLOYER_REG',
            'ZIMDEF_LEVY_REG',
            'ITF263_CLEARANCE',
        ])->get();

        $run1 = array_map(fn ($i) => $i['obligation']->code, $this->graph->sort($definitions));
        $run2 = array_map(fn ($i) => $i['obligation']->code, $this->graph->sort($definitions));
        $run3 = array_map(fn ($i) => $i['obligation']->code, $this->graph->sort($definitions));

        $this->assertSame($run1, $run2);
        $this->assertSame($run2, $run3);
    }
}

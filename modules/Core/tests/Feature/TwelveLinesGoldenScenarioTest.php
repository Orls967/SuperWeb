<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\TwelveLinesGoldenScenarioService;
use Modules\Core\Domain\Models\GoldenScenarioRun;
use Tests\TestCase;

class TwelveLinesGoldenScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected TwelveLinesGoldenScenarioService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TwelveLinesGoldenScenarioService::class);

        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_golden_scenario_runs_end_to_end_across_all_lines(): void
    {
        $res = $this->service->executeGoldenScenario();

        $this->assertTrue($res['all_lines_healthy']);
        $this->assertTrue($res['ledger_balanced']);
        $this->assertGreaterThanOrEqual(12, $res['steps_completed']);
    }

    public function test_golden_scenario_runs_all_twelve_business_lines_successfully(): void
    {
        $res = $this->service->executeGoldenScenario();

        $this->assertSame(12, $res['steps_completed']);
        $this->assertTrue($res['all_lines_healthy']);

        $expectedLines = [
            'AGRI', 'LOGISTICS', 'MANUFACTURING', 'RETAIL', 'WHOLESALES',
            'FITNESS', 'FNB', 'HOSPITALITY', 'VENUE', 'HOSPITAL', 'MINING', 'COMPLIANCE',
        ];

        $executedLines = array_column($res['steps'], 'line');
        $this->assertSame($expectedLines, $executedLines);
    }

    public function test_golden_scenario_generates_perfectly_balanced_double_entry_ledgers(): void
    {
        $res = $this->service->executeGoldenScenario();

        $this->assertTrue($res['ledger_balanced']);
        $this->assertGreaterThan(0, $res['total_amount_idr']);
    }

    public function test_golden_scenario_persists_run_and_step_records_in_database(): void
    {
        $res = $this->service->executeGoldenScenario();

        $this->assertDatabaseHas('core_golden_scenario_runs', [
            'run_uuid' => $res['run_uuid'],
            'total_steps' => 12,
            'passed_steps' => 12,
            'is_balanced' => true,
        ]);

        $run = GoldenScenarioRun::where('run_uuid', $res['run_uuid'])->firstOrFail();
        $this->assertCount(12, $run->steps);
    }
}

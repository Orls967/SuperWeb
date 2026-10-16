<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\TwelveLinesGoldenScenarioService;
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
        $this->assertGreaterThanOrEqual(6, $res['steps_completed']);
    }
}

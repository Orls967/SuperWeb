<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
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

        $accounts = [
            'clearing:golden_scenario:IDR' => 'clearing',
            'revenue:group_conglomerate:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Account {$code}",
                    'kind' => $kind,
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                ]
            );
        }
    }

    public function test_golden_scenario_runs_end_to_end_across_all_lines(): void
    {
        $res = $this->service->executeGoldenScenario();

        $this->assertTrue($res['all_lines_healthy']);
        $this->assertTrue($res['ledger_balanced']);
        $this->assertGreaterThanOrEqual(6, $res['steps_completed']);
    }
}

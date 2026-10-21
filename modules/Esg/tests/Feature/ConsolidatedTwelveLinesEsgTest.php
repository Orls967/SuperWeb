<?php

declare(strict_types=1);

namespace Modules\Esg\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Esg\Application\Services\ConsolidatedTwelveLinesEsgService;
use Tests\TestCase;

class ConsolidatedTwelveLinesEsgTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LedgerAccount::query()->firstOrCreate(
            ['code' => 'esg:carbon_offset_expense:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Carbon Offset Expense',
                'asset_code' => 'IDR',
                'kind' => 'EXPENSE',
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        LedgerAccount::query()->firstOrCreate(
            ['code' => 'esg:carbon_credit_inventory:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Carbon Credit Inventory',
                'asset_code' => 'IDR',
                'kind' => 'ASSET',
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public function test_record_emissions_and_aggregate_consolidated_report(): void
    {
        $service = app(ConsolidatedTwelveLinesEsgService::class);

        // Scope 1: Heavy Truck Fleet Diesel (10,000 L * 2.68 kg = 26,800 kg)
        $service->recordEmission('FLEET', 'SCOPE_1', 'Heavy Truck Diesel', 10000, 'LITER', 26800, '2026-Q4');

        // Scope 2: Hotel Grid Electricity (50,000 kWh * 0.78 kg = 39,000 kg)
        $service->recordEmission('HOTEL', 'SCOPE_2', 'Grid Electricity', 50000, 'KWH', 7800, '2026-Q4');

        // Scope 3: Logistics Circular Packaging (-5,000 kg * 1.45 kg avoidance = 7,250 kg net)
        $service->recordEmission('LOGISTICS', 'SCOPE_3', 'Packaging Recycling', 5000, 'KG', 14500, '2026-Q4');

        $report = $service->aggregateConsolidatedReport('2026-Q4');

        $this->assertSame('2026-Q4', $report['reporting_period']);
        $this->assertSame(26800, $report['scope_1_kg']);
        $this->assertSame(39000, $report['scope_2_kg']);
        $this->assertSame(7250, $report['scope_3_kg']);
        $this->assertSame(73050, $report['net_co2_kg']);
        $this->assertSame('COMPLIANT_GRI', $report['compliance_status']);
    }

    public function test_carbon_credit_retirement_double_entry_ledger_posting(): void
    {
        $service = app(ConsolidatedTwelveLinesEsgService::class);

        $res = $service->retireCarbonCredits(100, 150000, 'VCS-ID-2026-0044');

        $this->assertSame(100, $res['retired_tonnes']);
        $this->assertSame(15000000, $res['total_cost_idr']);
        $this->assertSame('RETIRED_VERIFIED', $res['status']);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Services\GroupCommandCenterExecutiveService;
use Modules\Core\Application\Services\UniversalEventSpineOutboxService;
use Modules\Esg\Application\Services\ConsolidatedTwelveLinesEsgService;
use Modules\Hcm\Application\Services\UniversalTalentGigOrchestratorService;
use Tests\TestCase;

class TwelveLinesCrossEcosystemFullSpineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@typeshit.local', 'password' => 'secret'],
            ['id' => 10, 'name' => 'Supervisor', 'email' => 'supervisor@typeshit.local', 'password' => 'secret'],
            ['id' => 20, 'name' => 'Gig Worker', 'email' => 'worker@typeshit.local', 'password' => 'secret'],
        ]);

        foreach (['hotel', 'venue', 'mining', 'logistics', 'hospital', 'resto'] as $domain) {
            LedgerAccount::query()->firstOrCreate(
                ['code' => "{$domain}:gig_expense:IDR"],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => "{$domain} Gig Expense",
                    'asset_code' => 'IDR',
                    'kind' => 'EXPENSE',
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }

        LedgerAccount::query()->firstOrCreate(
            ['code' => 'payroll:worker_payable:20:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Worker 20 Payable',
                'asset_code' => 'IDR',
                'kind' => 'LIABILITY',
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

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

    public function test_event_spine_idempotent_replay(): void
    {
        $service = app(UniversalEventSpineOutboxService::class);
        $key = 'evt:min:shipped:BATCH-1001';

        $event1 = $service->publishEvent('mining', 'min.ore_shipped', ['tonnes' => 500], $key);
        $event2 = $service->publishEvent('mining', 'min.ore_shipped', ['tonnes' => 500], $key);

        $this->assertSame($event1->id, $event2->id);

        $consumedCount = 0;
        $handler = function (array $payload) use (&$consumedCount): string {
            $consumedCount++;

            return 'SUCCESS';
        };

        $res1 = $service->consumeEvent($event1->id, 'trade_finance_group', $handler);
        $this->assertSame('PROCESSED', $res1['status']);
        $this->assertSame(1, $consumedCount);

        $res2 = $service->consumeEvent($event1->id, 'trade_finance_group', $handler);
        $this->assertSame('ALREADY_PROCESSED', $res2['status']);
        $this->assertSame(1, $consumedCount);
    }

    public function test_consolidated_esg_and_carbon_retire(): void
    {
        $service = app(ConsolidatedTwelveLinesEsgService::class);
        $service->recordEmission('FLEET', 'SCOPE_1', 'Heavy Truck Diesel', 10000, 'LITER', 26800, '2026-Q4');
        $service->recordEmission('HOTEL', 'SCOPE_2', 'Grid Electricity', 50000, 'KWH', 7800, '2026-Q4');

        $report = $service->aggregateConsolidatedReport('2026-Q4');
        $this->assertSame(26800, $report['scope_1_kg']);
        $this->assertSame(39000, $report['scope_2_kg']);
        $this->assertSame(65800, $report['net_co2_kg']);

        $retirement = $service->retireCarbonCredits(65, 150000, 'CERT-VCS-2026-0099');
        $this->assertSame(65, $retirement['retired_tonnes']);
        $this->assertSame(9750000, $retirement['total_cost_idr']);
        $this->assertSame('RETIRED_VERIFIED', $retirement['status']);
    }

    public function test_gig_marketplace_claim_and_instant_payroll(): void
    {
        $service = app(UniversalTalentGigOrchestratorService::class);
        $task = $service->publishGigTask('HOTEL', 'Banqueting Emergency Shift', 'Support major gala', 75000, 8, 10, 'K3-HOSPITALITY-BASIC');
        $this->assertSame('OPEN', $task->status);
        $this->assertSame(600000, $task->total_compensation_idr);

        $claimed = $service->claimGigTask($task->id, 20, 'K3-HOSPITALITY-BASIC');
        $this->assertSame('CLAIMED', $claimed->status);

        $payout = $service->approveAndDisburseGigPayout($task->id, 10, 'PROOF-SHIFT-CHECKOUT-OK');
        $this->assertSame('PAID', $payout['status']);
        $this->assertSame(600000, $payout['net_paid_idr']);
    }

    public function test_command_center_query_budget_and_health_score(): void
    {
        $service = app(GroupCommandCenterExecutiveService::class);
        $score = $service->calculateCompositeHealthScore('HOLDING-CORP', 95, 90, 88, 92);

        $this->assertSame('HOLDING-CORP', $score->entity_code);
        $this->assertGreaterThanOrEqual(85, $score->composite_health_score);
        $this->assertSame('EXCELLENT', $score->status);

        $overview = $service->getExecutiveOverview();
        $this->assertSame('ALL_40_COMMANDS_HEALTHY', $overview['audit_suite_status']);
        $this->assertArrayHasKey('pnl_consolidated', $overview);
    }
}

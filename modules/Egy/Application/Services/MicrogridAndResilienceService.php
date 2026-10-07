<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Egy\Domain\Models\BackupGeneratorTest;
use Modules\Egy\Domain\Models\BessArbitrageRun;
use Modules\Egy\Domain\Models\MicrogridController;
use Modules\Egy\Domain\Models\MicrogridLoadPriority;

class MicrogridAndResilienceService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function createMicrogrid(string $siteName, float $availablePowerKw): MicrogridController
    {
        return MicrogridController::create([
            'id' => (string) Str::uuid(),
            'microgrid_code' => 'UGRID-'.strtoupper(Str::random(8)),
            'site_name' => $siteName,
            'grid_mode' => 'GRID_CONNECTED',
            'available_power_kw' => $availablePowerKw,
        ]);
    }

    public function addLoadPriority(string $microgridId, string $loadName, string $tier, int $priorityRank, float $requiredKw): MicrogridLoadPriority
    {
        return MicrogridLoadPriority::create([
            'id' => (string) Str::uuid(),
            'microgrid_id' => $microgridId,
            'load_name' => $loadName,
            'tier' => $tier,
            'priority_rank' => $priorityRank,
            'required_load_kw' => $requiredKw,
            'is_shed' => false,
        ]);
    }

    /**
     * Trigger islanding mode when primary grid is lost. Sheds loads by priority rank if power is insufficient.
     */
    public function triggerIslandingMode(string $microgridId, float $emergencyGenerationKw): array
    {
        $grid = MicrogridController::findOrFail($microgridId);
        $grid->update([
            'grid_mode' => 'ISLANDED',
            'available_power_kw' => $emergencyGenerationKw,
        ]);

        $loads = MicrogridLoadPriority::where('microgrid_id', $microgridId)
            ->orderBy('priority_rank', 'asc')
            ->get();

        $runningCapacity = $emergencyGenerationKw;
        $activeLoads = [];
        $shedLoads = [];

        foreach ($loads as $load) {
            if ($runningCapacity >= $load->required_load_kw) {
                $load->update(['is_shed' => false]);
                $runningCapacity -= $load->required_load_kw;
                $activeLoads[] = $load->load_name;
            } else {
                $load->update(['is_shed' => true]);
                $shedLoads[] = $load->load_name;
            }
        }

        return [
            'microgrid_code' => $grid->microgrid_code,
            'mode' => 'ISLANDED',
            'active_loads' => $activeLoads,
            'shed_loads' => $shedLoads,
            'surplus_kw' => $runningCapacity,
        ];
    }

    public function recordBessArbitrageRun(array $params): BessArbitrageRun
    {
        $kwh = (float) $params['energy_discharged_kwh'];
        $chargeCost = (int) $params['charging_cost_minor'];
        $dischargeRev = (int) $params['discharging_revenue_minor'];
        $netProfit = $dischargeRev - $chargeCost;

        $runCode = 'BESS-ARB-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($params, $kwh, $chargeCost, $dischargeRev, $netProfit, $runCode) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_BESS_ARBITRAGE_PROFIT',
                description: "Battery arbitrage net profit for run {$runCode}",
                idempotencyKey: 'EGY-BESS-'.$runCode,
                entries: [
                    PostingEntryDTO::forCode('egy:utility_receivable:IDR', 'IDR', $netProfit),
                    PostingEntryDTO::forCode('egy:bess_arbitrage_revenue:IDR', 'IDR', -$netProfit),
                ],
                referenceType: 'BESS_ARBITRAGE_RUN',
                referenceId: $runCode,
            ));

            return BessArbitrageRun::create([
                'id' => (string) Str::uuid(),
                'bess_asset_id' => $params['bess_asset_id'],
                'run_code' => $runCode,
                'energy_discharged_kwh' => $kwh,
                'charging_cost_minor' => $chargeCost,
                'discharging_revenue_minor' => $dischargeRev,
                'net_arbitrage_profit_minor' => $netProfit,
                'battery_cycle_count_increment' => 1,
                'state_of_health_pct' => (float) ($params['state_of_health_pct'] ?? 99.8),
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function recordBackupGeneratorTest(array $params): BackupGeneratorTest
    {
        $loadPct = (float) $params['load_test_pct'];
        $runtime = (int) $params['runtime_minutes'];
        $isPassed = $loadPct >= 80.0 && $runtime >= 30; // standard compliance requires at least 80% load for 30 mins

        return BackupGeneratorTest::create([
            'id' => (string) Str::uuid(),
            'test_code' => 'BKP-TEST-'.strtoupper(Str::random(8)),
            'property_id' => $params['property_id'],
            'generator_asset_id' => $params['generator_asset_id'],
            'scheduled_test_date' => Carbon::parse($params['scheduled_test_date']),
            'load_test_pct' => $loadPct,
            'runtime_minutes' => $runtime,
            'is_passed' => $isPassed,
            'notes' => $isPassed ? 'Compliant with critical emergency backup standard.' : 'Load or runtime below compliance threshold.',
        ]);
    }
}

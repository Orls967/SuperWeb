<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\BessArbitrageRun;
use Modules\Shared\Application\BaseAction;

class DispatchMicrogridBatteryPeakShavingAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): BessArbitrageRun
    {
        return DB::transaction(function () use ($params) {
            $assetId = (string) ($params['bess_asset_id'] ?? $params['asset_id']);
            $dischargeKwh = (int) ($params['energy_discharged_kwh'] ?? $params['discharge_kwh']);
            $revenueMinor = (int) ($params['discharging_revenue_minor'] ?? $params['discharge_revenue_minor'] ?? ($dischargeKwh * 2500));
            $costMinor = (int) ($params['charging_cost_minor'] ?? $params['charge_cost_minor'] ?? ($dischargeKwh * 1000));
            $netProfit = $revenueMinor - $costMinor;

            $runCode = 'BESS-'.strtoupper(substr(hash('sha256', "{$assetId}:{$dischargeKwh}:".microtime()), 0, 10));

            $run = BessArbitrageRun::create([
                'id' => (string) Str::uuid(),
                'bess_asset_id' => $assetId,
                'run_code' => $runCode,
                'energy_discharged_kwh' => $dischargeKwh,
                'charging_cost_minor' => $costMinor,
                'discharging_revenue_minor' => $revenueMinor,
                'net_arbitrage_profit_minor' => $netProfit,
                'battery_cycle_count_increment' => 1,
                'state_of_health_pct' => 99.8,
            ]);

            if ($netProfit > 0) {
                $idempotencyKey = hash('sha256', "BESS-DISPATCH:{$runCode}:{$netProfit}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_BESS_ARBITRAGE_PROFIT',
                    description: "BESS peak shaving arbitrage profit for {$runCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:utility_receivable:IDR', $netProfit)
                        ->credit('egy:bess_arbitrage_revenue:IDR', $netProfit)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_BESS_RUN',
                    referenceId: $runCode,
                ));
            }

            $this->audit(
                action: 'egy_battery_dispatched',
                auditable: $run,
                newValues: ['discharge_kwh' => $dischargeKwh, 'net_profit' => $netProfit],
                impactType: 'grid_operations',
            );

            return $run;
        });
    }
}

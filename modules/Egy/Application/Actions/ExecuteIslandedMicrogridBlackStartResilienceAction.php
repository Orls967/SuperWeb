<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class ExecuteIslandedMicrogridBlackStartResilienceAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Executes microgrid islanded black-start resilience, load priority shedding, and BESS arbitrage.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $operationCode = (string) ($params['operation_code'] ?? 'MCG-'.hash('sha256', (string) ($params['site_id'] ?? 'default')));
            $siteId = (string) ($params['site_id'] ?? 'SITE-HOSPITAL-CAMPUS');
            $gridStatus = (string) ($params['grid_status'] ?? 'GRID_DOWN');

            $batteryKw = (int) ($params['battery_available_kw'] ?? 1000);
            $gensetKw = (int) ($params['genset_available_kw'] ?? 2000);
            $totalAvailableSupplyKw = $batteryKw + $gensetKw;

            /** @var list<array{facility: string, type: string, priority: int, demand_kw: int}> $connectedLoads */
            $connectedLoads = $params['connected_loads'] ?? [];

            // Sort loads by priority ascending (1 = highest critical, e.g., Hospital ICU)
            usort($connectedLoads, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

            $servedLoads = [];
            $shedLoads = [];
            $currentAllocatedKw = 0;

            foreach ($connectedLoads as $load) {
                $demandKw = (int) $load['demand_kw'];

                if (($currentAllocatedKw + $demandKw) <= $totalAvailableSupplyKw) {
                    $currentAllocatedKw += $demandKw;
                    $servedLoads[] = [
                        'facility' => $load['facility'],
                        'priority' => $load['priority'],
                        'demand_kw' => $demandKw,
                        'status' => 'POWERED',
                    ];
                } else {
                    $shedLoads[] = [
                        'facility' => $load['facility'],
                        'priority' => $load['priority'],
                        'demand_kw' => $demandKw,
                        'status' => 'SHED',
                    ];
                }
            }

            // BESS Arbitrage calculation
            $dischargeKwh = (int) ($params['discharge_kwh'] ?? 500);
            $revenueMinor = (int) ($params['discharge_revenue_minor'] ?? ($dischargeKwh * 3000));
            $costMinor = (int) ($params['charge_cost_minor'] ?? ($dischargeKwh * 1200));
            $netProfitMinor = $revenueMinor - $costMinor;

            if ($netProfitMinor > 0) {
                $idempotencyKey = hash('sha256', "EGY:BESS-RESILIENCE:{$operationCode}:{$netProfitMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_BESS_ARBITRAGE_PROFIT',
                    description: "BESS resilience arbitrage profit {$operationCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:utility_receivable:IDR', $netProfitMinor)
                        ->credit('egy:bess_arbitrage_revenue:IDR', $netProfitMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_BESS_OPERATION',
                    referenceId: $operationCode,
                ));
            }

            $backupTestPassed = (string) ($params['backup_compliance_status'] ?? 'PASS');

            $result = [
                'operation_code' => $operationCode,
                'site_id' => $siteId,
                'grid_status' => $gridStatus,
                'is_islanded' => $gridStatus === 'GRID_DOWN',
                'total_supply_kw' => $totalAvailableSupplyKw,
                'allocated_kw' => $currentAllocatedKw,
                'served_loads' => $servedLoads,
                'shed_loads' => $shedLoads,
                'net_arbitrage_profit_minor' => $netProfitMinor,
                'backup_compliance_status' => $backupTestPassed,
            ];

            $this->audit(
                action: 'egy.microgrid.resilience_executed',
                newValues: [
                    'operation_code' => $operationCode,
                    'is_islanded' => $gridStatus === 'GRID_DOWN' ? 'YES' : 'NO',
                    'allocated_kw' => $currentAllocatedKw,
                    'shed_count' => count($shedLoads),
                ],
                correlationId: "egy_microgrid_{$operationCode}",
                impactType: 'microgrid_resilience'
            );

            $this->outbox(
                eventType: 'IslandedMicrogridBlackStartExecuted',
                payload: [
                    'operation_code' => $operationCode,
                    'site_id' => $siteId,
                    'allocated_kw' => $currentAllocatedKw,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:MCG:{$operationCode}")
            );

            return $result;
        });
    }
}

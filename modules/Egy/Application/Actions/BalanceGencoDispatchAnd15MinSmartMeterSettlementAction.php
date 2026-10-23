<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class BalanceGencoDispatchAnd15MinSmartMeterSettlementAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Balances generation commitment across assets and settles 15-minute smart meter TOU billing.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $settlementCode = (string) ($params['settlement_code'] ?? 'EGY-SETTLE-'.hash('sha256', (string) ($params['batch_id'] ?? 'default')));
            $totalDemandKw = (int) ($params['total_demand_kw'] ?? 0);

            /** @var list<array{asset_id: string, name: string, capacity_kw: int, dispatch_kw: int, marginal_cost_minor: int}> $assets */
            $assets = $params['generation_assets'] ?? [];

            $totalDispatchedKw = 0;
            $totalGenerationCostMinor = 0;
            $assetCommitments = [];

            foreach ($assets as $asset) {
                $capacityKw = (int) $asset['capacity_kw'];
                $dispatchKw = (int) $asset['dispatch_kw'];

                if ($dispatchKw > $capacityKw) {
                    throw new DomainException("Dispatch {$dispatchKw} kW exceeds installed capacity {$capacityKw} kW on asset {$asset['name']}");
                }

                $marginalCost = (int) ($asset['marginal_cost_minor'] ?? 0);
                $costMinor = $dispatchKw * $marginalCost;

                $totalDispatchedKw += $dispatchKw;
                $totalGenerationCostMinor += $costMinor;

                $assetCommitments[] = [
                    'asset_id' => $asset['asset_id'],
                    'name' => $asset['name'],
                    'dispatch_kw' => $dispatchKw,
                    'cost_minor' => $costMinor,
                ];
            }

            // Meter TOU Settlement
            /** @var list<array{meter_id: string, property_type: string, kwh_consumed: int, is_peak_slot: int, tou_rate_minor: int, solar_export_kwh?: int, feed_in_tariff_minor?: int}> $readings */
            $readings = $params['meter_readings'] ?? [];

            $totalBilledMinor = 0;
            $totalFeedInMinor = 0;
            $meterSettlements = [];

            foreach ($readings as $reading) {
                $kwh = (int) $reading['kwh_consumed'];
                $rateMinor = (int) $reading['tou_rate_minor'];
                $chargeMinor = $kwh * $rateMinor;

                $solarExport = (int) ($reading['solar_export_kwh'] ?? 0);
                $feedInRate = (int) ($reading['feed_in_tariff_minor'] ?? 0);
                $feedInCredit = $solarExport * $feedInRate;

                $netPayableMinor = max(0, $chargeMinor - $feedInCredit);

                $totalBilledMinor += $chargeMinor;
                $totalFeedInMinor += $feedInCredit;

                $meterSettlements[] = [
                    'meter_id' => $reading['meter_id'],
                    'property_type' => $reading['property_type'],
                    'kwh' => $kwh,
                    'charge_minor' => $chargeMinor,
                    'feed_in_credit_minor' => $feedInCredit,
                    'net_payable_minor' => $netPayableMinor,
                ];
            }

            if ($totalBilledMinor > 0) {
                $idempotencyKey = hash('sha256', "EGY:DISPATCH-METER:{$settlementCode}:{$totalBilledMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'ENERGY_METER_READING_BILL',
                    description: "15-min smart meter TOU billing settlement {$settlementCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('egy:utility_receivable:IDR', $totalBilledMinor)
                        ->credit('egy:grid_electricity_revenue:IDR', $totalBilledMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EGY_METER_SETTLEMENT',
                    referenceId: $settlementCode,
                ));
            }

            $curtailmentKw = max(0, $totalDispatchedKw - $totalDemandKw);

            $result = [
                'settlement_code' => $settlementCode,
                'total_demand_kw' => $totalDemandKw,
                'total_dispatched_kw' => $totalDispatchedKw,
                'curtailment_kw' => $curtailmentKw,
                'total_generation_cost_minor' => $totalGenerationCostMinor,
                'total_billed_minor' => $totalBilledMinor,
                'total_feed_in_minor' => $totalFeedInMinor,
                'asset_commitments' => $assetCommitments,
                'meter_settlements' => $meterSettlements,
            ];

            $this->audit(
                action: 'egy.genco.dispatch_meter_settled',
                newValues: [
                    'settlement_code' => $settlementCode,
                    'dispatched_kw' => $totalDispatchedKw,
                    'billed_minor' => $totalBilledMinor,
                ],
                correlationId: "egy_dispatch_{$settlementCode}",
                impactType: 'grid_dispatch_settlement'
            );

            $this->outbox(
                eventType: 'GencoDispatchAndMeterSettled',
                payload: [
                    'settlement_code' => $settlementCode,
                    'total_dispatched_kw' => $totalDispatchedKw,
                    'total_billed_minor' => $totalBilledMinor,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:EGY:{$settlementCode}")
            );

            return $result;
        });
    }
}

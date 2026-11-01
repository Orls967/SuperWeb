<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Mining\Domain\Algorithms\AssignmentCertificateVerifier;
use Modules\Mining\Domain\Models\MiningDispatchAssignment;
use Modules\Mining\Domain\Models\MiningDispatchPlan;
use Modules\Mining\Domain\Models\MiningDispatchRun;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\StockpileMovement;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Mining\Domain\Services\MiningWeighbridgeChainService;

class MiningFleetDispatchService
{
    public function __construct(
        private readonly MiningWeighbridgeChainService $chainService,
        private readonly AssignmentCertificateVerifier $assignmentVerifier
    ) {}

    /**
     * Dispatch haul truck with target payload
     */
    public function dispatchEquipment(
        string $siteId,
        string $pitId,
        string $equipmentId,
        string $shiftCode,
        float $targetPayloadTon
    ): MiningDispatchRun {
        $equipment = MiningEquipment::findOrFail($equipmentId);
        if ($equipment->status !== 'available') {
            throw new \RuntimeException("Equipment {$equipment->equipment_code} is not available for dispatch (status: {$equipment->status})");
        }

        $equipment->update(['status' => 'dispatched']);

        return MiningDispatchRun::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteId,
            'pit_id' => $pitId,
            'equipment_id' => $equipment->id,
            'shift_code' => $shiftCode,
            'target_payload_ton' => $targetPayloadTon,
            'actual_payload_ton' => 0.00,
            'payload_variance_ton' => 0.00,
            'fuel_consumed_liter' => 0.00,
            'distance_km' => 0.00,
            'fuel_anomaly_detected' => false,
            'contractor_payment_held' => false,
            'status' => 'in_progress',
        ]);
    }

    /**
     * Comprehensive two-source audit for Mining operations:
     * 1. Equipment status vs dispatch runs
     * 2. Weighbridge tickets vs Stockpile movements vs Stockpile balances
     * 3. Royalty return calculations vs Ledger entries
     * 4. Weighbridge ticket cryptographic hash-chains
     * 5. Dispatch plan dual certificates
     * 6. Double-entry ledger balance
     *
     * @return array{status: string, site_count: int, pit_count: int, equipment_count: int, dispatch_count: int, discrepancies: int, errors: list<string>}
     */
    public function auditMining(): array
    {
        $siteCount = MiningSite::count();
        $pitCount = MiningPit::count();
        $equipmentCount = MiningEquipment::count();
        $dispatchCount = MiningDispatchRun::count();
        $discrepancies = 0;
        $errors = [];

        // 1. Two-source: equipment 'dispatched' must have an in_progress dispatch run
        $dispatchedEquipment = MiningEquipment::where('status', 'dispatched')->get();
        foreach ($dispatchedEquipment as $equip) {
            $hasRun = MiningDispatchRun::where('equipment_id', $equip->id)
                ->where('status', 'in_progress')
                ->exists();
            if (! $hasRun) {
                $discrepancies++;
                $errors[] = "Dispatched equipment {$equip->equipment_code} without in_progress run.";
            }
        }

        // 2. Two-source: Weighbridge vs Stockpile movements vs Stockpile total_kg
        $stockpiles = MiningStockpile::all();
        foreach ($stockpiles as $stk) {
            $inflow = (int) StockpileMovement::where('stockpile_id', $stk->id)->where('direction', 'IN')->sum('weight_kg');
            $outflow = (int) StockpileMovement::where('stockpile_id', $stk->id)->where('direction', 'OUT')->sum('weight_kg');
            $expectedTotal = $inflow - $outflow;

            if ((int) $stk->total_kg !== $expectedTotal) {
                $discrepancies++;
                $errors[] = "Stockpile discrepancy: {$stk->stockpile_code} balance is {$stk->total_kg}kg but movements indicate {$expectedTotal}kg.";
            }
        }

        // 3. Two-source: Verified weighbridge production vs Royalty calculation and ledger
        $royaltyReturns = MiningRoyaltyReturn::all();
        foreach ($royaltyReturns as $ret) {
            $site = MiningSite::find($ret->site_id);
            $timezone = $site?->timezone ?: 'Asia/Makassar';

            // Verify production tonnage from tickets
            $tickets = WeighbridgeTicket::where('site_id', $ret->site_id)->get();
            $computedProduction = 0;
            foreach ($tickets as $t) {
                $weighedAt = $t->weighed_at ?: $t->created_at;
                $periodLocal = Carbon::parse($weighedAt)->setTimezone($timezone)->format('Y-m');
                if ($periodLocal === $ret->period_local) {
                    $computedProduction += (int) $t->net_weight_kg;
                }
            }

            if ($computedProduction > 0 && (int) $ret->total_production_kg !== $computedProduction) {
                $discrepancies++;
                $errors[] = "Royalty discrepancy: Return {$ret->period_local} production ({$ret->total_production_kg}kg) != tickets ({$computedProduction}kg).";
            }

            // Check ledger entry (K-18: saldo normal expense adalah debit, tercatat sebagai nilai negatif pada amount)
            $expectedAmount = (int) ($ret->finalized_royalty_idr ?? $ret->calculated_royalty_idr);
            if ($expectedAmount > 0) {
                $actualLedgerDebit = -(int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'expense:min:royalty:IDR'))
                    ->sum('amount');

                if ($actualLedgerDebit !== $expectedAmount) {
                    $discrepancies++;
                    $errors[] = "Royalty discrepancy: Ledger debit ({$actualLedgerDebit}) != expected royalty ({$expectedAmount}).";
                }
            }
        }

        // 4. Weighbridge Hash-chain verification
        $sites = MiningSite::all();
        foreach ($sites as $site) {
            $isChainValid = $this->chainService->verifySiteChain($site->id);
            if (! $isChainValid) {
                $discrepancies++;
                $errors[] = "Weighbridge hash-chain broken on site {$site->site_code}.";
            }
        }

        // 5. Dispatch plan dual certificates
        $dispatchPlans = MiningDispatchPlan::all();
        foreach ($dispatchPlans as $plan) {
            if ($plan->certificate_json) {
                $cert = json_decode($plan->certificate_json, true) ?: [];
                $assignments = MiningDispatchAssignment::where('dispatch_plan_id', $plan->id)->get();

                // Build assignment map and matrix
                $u = $cert['u'] ?? [];
                $v = $cert['v'] ?? [];
                $dualSum = array_sum($u) + array_sum($v);

                if ($dualSum !== (int) $plan->total_cost_seconds) {
                    $discrepancies++;
                    $errors[] = "Invalid dispatch certificate on plan {$plan->shift_code}: dual sum ({$dualSum}) != primal cost ({$plan->total_cost_seconds}).";
                }
            }
        }

        // 6. Double-entry ledger balance: SUM(amount) per transaksi = 0 (K-18)
        $unbalancedTxCount = LedgerEntry::selectRaw('transaction_id, SUM(amount) as balance')
            ->groupBy('transaction_id')
            ->havingRaw('ROUND(SUM(amount), 4) != 0')
            ->count();

        if ($unbalancedTxCount > 0) {
            $discrepancies++;
            $errors[] = "Ledger out of balance: {$unbalancedTxCount} unbalanced transactions detected.";
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'site_count' => $siteCount,
            'pit_count' => $pitCount,
            'equipment_count' => $equipmentCount,
            'dispatch_count' => $dispatchCount,
            'discrepancies' => $discrepancies,
            'errors' => $errors,
        ];
    }

    /**
     * Predictive maintenance trigger based on engine hours threshold.
     */
    public function recordEngineHoursAndCheckMaintenance(string $equipmentId, int $additionalHours, int $thresholdHours = 1500): array
    {
        $equipment = MiningEquipment::findOrFail($equipmentId);
        $equipment->increment('engine_hours', $additionalHours);
        $currentHours = (int) $equipment->fresh()->engine_hours;

        $triggered = false;
        if ($currentHours >= $thresholdHours && $equipment->status !== 'maintenance') {
            $equipment->update(['status' => 'maintenance']);
            $triggered = true;
        }

        return [
            'equipment_id' => $equipmentId,
            'current_engine_hours' => $currentHours,
            'threshold_hours' => $thresholdHours,
            'maintenance_triggered' => $triggered,
        ];
    }
}

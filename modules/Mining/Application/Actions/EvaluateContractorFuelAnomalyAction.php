<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Mining\Domain\Models\MiningContractorEvaluation;
use Modules\Mining\Ledger\MiningAccounts;
use Modules\Mining\Ledger\MiningTransactionTypes;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Algorithms\Stats\RobustStatistics;

class EvaluateContractorFuelAnomalyAction extends BaseAction
{
    public function __construct(
        private readonly RobustStatistics $stats,
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * @param  list<int>  $historicalSfcSamples
     */
    public function handle(
        string $siteId,
        string $contractorPartyId,
        string $period,
        int $totalTonKm,
        int $fuelConsumedLiters,
        array $historicalSfcSamples,
        int $penaltyAmountIdr,
        string $idempotencyKey = '',
        int $kBps = 35000,
        int $absoluteFloor = 5
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        // Idempotency check
        $existing = MiningContractorEvaluation::where('contractor_party_id', $contractorPartyId)
            ->where('period', $period)
            ->where('site_id', $siteId)
            ->first();

        if ($existing) {
            return (object) [
                'evaluation' => $existing,
                'isAnomaly' => (bool) $existing->is_anomaly,
                'paymentHeld' => (bool) $existing->payment_held,
                'baselineMedian' => (float) $existing->baseline_median,
                'baselineMad' => (float) $existing->baseline_mad,
            ];
        }

        return DB::transaction(function () use (
            $siteId,
            $contractorPartyId,
            $period,
            $totalTonKm,
            $fuelConsumedLiters,
            $historicalSfcSamples,
            $penaltyAmountIdr,
            $idempotencyKey,
            $kBps,
            $absoluteFloor
        ) {
            // SFC per 100 ton-km: (Liters * 100) / totalTonKm
            $sfc = $totalTonKm > 0
                ? (int) round(($fuelConsumedLiters * 100) / $totalTonKm)
                : 0;

            $median = $this->stats->median($historicalSfcSamples);
            $mad = $this->stats->mad($historicalSfcSamples, $median);

            $isAnomaly = $this->stats->isAnomaly($sfc, $median, $mad, $kBps, $absoluteFloor);
            $paymentHeld = $isAnomaly;

            $medianFloat = (float) $median->toScale(2, RoundingMode::HalfUp)->toFloat();
            $madFloat = (float) $mad->toScale(2, RoundingMode::HalfUp)->toFloat();

            $evaluation = MiningContractorEvaluation::create([
                'id' => (string) Str::uuid(),
                'evaluation_uuid' => $idempotencyKey,
                'contractor_party_id' => $contractorPartyId,
                'site_id' => $siteId,
                'period' => $period,
                'total_ton_km' => $totalTonKm,
                'fuel_consumed_liters' => $fuelConsumedLiters,
                'sfc_per_100_ton_km' => $sfc,
                'baseline_median' => $medianFloat,
                'baseline_mad' => $madFloat,
                'is_anomaly' => $isAnomaly,
                'penalty_amount_idr' => $penaltyAmountIdr,
                'payment_held' => $paymentHeld,
            ]);

            // Post penalty to ledger if anomaly
            if ($isAnomaly && $penaltyAmountIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: MiningTransactionTypes::MINING_CONTRACTOR_PENALTY->value,
                    description: "Penalti pelanggaran anomali BBM kontraktor {$contractorPartyId} periode {$period}",
                    idempotencyKey: "min:penalty:{$evaluation->id}",
                    entries: Posting::lines()
                        ->debit(MiningAccounts::contractorReceivable($contractorPartyId), $penaltyAmountIdr)
                        ->credit(MiningAccounts::penaltyIncome(), $penaltyAmountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'MINING_CONTRACTOR_EVALUATION',
                    referenceId: $evaluation->id,
                ));
            }

            return (object) [
                'evaluation' => $evaluation,
                'isAnomaly' => $isAnomaly,
                'paymentHeld' => $paymentHeld,
                'baselineMedian' => $medianFloat,
                'baselineMad' => $madFloat,
            ];
        });
    }
}

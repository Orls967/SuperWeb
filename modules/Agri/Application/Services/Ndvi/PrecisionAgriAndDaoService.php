<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Services\Ndvi;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Agri\Domain\Models\Ndvi\AgriFinancingInstallment;
use Modules\Agri\Domain\Models\Ndvi\AgriSatelliteScan;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;

final class PrecisionAgriAndDaoService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null,
        protected ?RationalNdviCalculator $rationalCalculator = null,
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
        $this->rationalCalculator = $rationalCalculator ?? new RationalNdviCalculator;
    }

    /**
     * D86-1: Record satellite NDVI scan with rational exact calculation.
     */
    public function recordSatelliteScan(
        string $plotCode,
        Carbon|CarbonImmutable $scanDate,
        float|array $ndviScore = 0.0,
        ?array $polygonGeojson = null
    ): AgriSatelliteScan {
        $dateStr = $scanDate instanceof CarbonImmutable ? $scanDate->toDateString() : $scanDate->toDateString();
        $key = hash('sha256', "{$plotCode}:{$dateStr}");

        if (is_array($ndviScore)) {
            $calc = $this->rationalCalculator->calculateFieldNdvi($ndviScore);
            $score = $calc['float_val'];
        } else {
            $score = (float) $ndviScore;
        }

        $health = match (true) {
            $score >= 0.70 => 'HEALTHY',
            $score >= 0.50 => 'MILD_STRESS',
            $score >= 0.30 => 'SEVERE_STRESS',
            default => 'CRITICAL',
        };

        return AgriSatelliteScan::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'scan_code' => 'SCN-'.strtoupper(substr(hash('sha256', "{$plotCode}:{$dateStr}"), 0, 12)),
                'plot_code' => $plotCode,
                'scan_date' => $dateStr,
                'ndvi_score' => $score,
                'crop_health_status' => $health,
                'polygon_geojson' => $polygonGeojson,
            ]
        );
    }

    /**
     * D86-2: Conditional loan installment disbursement based on rational NDVI benchmark.
     */
    public function evaluateAndDisburseInstallment(
        AgriFinancingInstallment $installment,
        float|array $latestNdviOrPixels
    ): AgriFinancingInstallment {
        return DB::transaction(function () use ($installment, $latestNdviOrPixels) {
            $locked = AgriFinancingInstallment::where('id', $installment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'DISBURSED') {
                return $locked; // Idempoten: tidak cair 2x
            }

            $passes = false;
            $scoreFloat = 0.0;
            $num = null;
            $denom = null;

            if (is_array($latestNdviOrPixels)) {
                $calc = $this->rationalCalculator->calculateFieldNdvi($latestNdviOrPixels);
                $num = $calc['numerator'];
                $denom = $calc['denominator'];
                $scoreFloat = $calc['float_val'];

                $thresholdBps = (int) round(((float) $locked->required_min_ndvi) * 10000);
                $passes = $this->rationalCalculator->meetsThreshold($num, $denom, $thresholdBps);
            } else {
                $scoreFloat = (float) $latestNdviOrPixels;
                $passes = ($scoreFloat >= (float) $locked->required_min_ndvi);
            }

            $locked->evaluated_ndvi = $scoreFloat;
            if ($num !== null && $denom !== null) {
                $locked->evaluated_ndvi_numerator = $num;
                $locked->evaluated_ndvi_denominator = $denom;
            }

            if (! $passes) {
                $failures = ((int) $locked->consecutive_failures) + 1;
                $locked->consecutive_failures = $failures;

                if ($failures >= 2) {
                    $locked->status = 'RESTRUCTURED_PENDING_APPROVAL';
                    $locked->corrective_action_plan = 'NDVI consistently below benchmark (2x failures). Escalated to Restructuring & Approval Engine.';
                } else {
                    $locked->status = 'HELD_CORRECTIVE_PLAN';
                    $locked->corrective_action_plan = 'NDVI below standard. Apply automated drip fertigation and soil moisture replenishment.';
                }

                $locked->save();

                return $locked;
            }

            // NDVI meets benchmark -> reset failures & disburse
            $locked->consecutive_failures = 0;
            $locked->status = 'DISBURSED';
            $locked->disbursed_at = now();
            $locked->save();

            // Post loan disbursement to ledger: Debit Piutang Petani, Kredit Escrow Disbursement
            $this->ledgerService->post(new PostingDTO(
                type: 'AGRI_LOAN_DISBURSEMENT',
                description: "Disburse tranche {$locked->tranche_number} for plot {$locked->plot_code}",
                idempotencyKey: "AGR-DISB-{$locked->installment_code}",
                entries: [
                    PostingEntryDTO::forCode('agri:farmer_loan_ar:IDR', 'IDR', (int) $locked->amount_idr),
                    PostingEntryDTO::forCode('agri:escrow_disbursement:IDR', 'IDR', -(int) $locked->amount_idr),
                ],
                referenceType: 'AGRI_INSTALLMENT',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }
}

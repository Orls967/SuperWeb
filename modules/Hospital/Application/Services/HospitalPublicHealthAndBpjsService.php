<?php

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\BpjsClaimBatch;
use Modules\Hospital\Domain\Models\BpjsClaimItem;
use Modules\Hospital\Domain\Models\EpidemicSurveillance;
use Modules\Hospital\Domain\Models\HealthCommandCenterForecast;

class HospitalPublicHealthAndBpjsService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 107.1 Create JKN/BPJS Claim Batch with Gapless Sequence
     */
    public function createBpjsClaimBatch(string $claimMonth, array $episodesWithDrg): BpjsClaimBatch
    {
        return DB::transaction(function () use ($claimMonth, $episodesWithDrg) {
            $count = BpjsClaimBatch::where('claim_month', $claimMonth)->count() + 1;
            $batchNumber = sprintf('BPJS-%s-%04d', str_replace('-', '', $claimMonth), $count);

            $batch = BpjsClaimBatch::create([
                'batch_number' => $batchNumber,
                'claim_month' => $claimMonth,
                'total_episodes' => count($episodesWithDrg),
                'total_claimed_idr' => 0,
                'status' => 'SUBMITTED',
            ]);

            $totalClaimed = 0;
            foreach ($episodesWithDrg as $item) {
                /** @var BillingEpisode $episode */
                $episode = $item['episode'];
                $drgCode = $item['drg_code'];
                $drgTariff = $item['drg_tariff_idr'];

                BpjsClaimItem::create([
                    'batch_id' => $batch->id,
                    'billing_episode_id' => $episode->id,
                    'drg_code' => $drgCode,
                    'drg_tariff_idr' => $drgTariff,
                    'verification_status' => 'SUBMITTED',
                ]);

                $totalClaimed += $drgTariff;
            }

            $batch->update(['total_claimed_idr' => $totalClaimed]);

            return $batch;
        });
    }

    /**
     * 107.1 & 107.6 (a) Verify and Settle BPJS Claim Batch: Denied claims do not accrue revenue
     */
    public function adjudicateBpjsClaimBatch(BpjsClaimBatch $batch, array $itemDecisions): BpjsClaimBatch
    {
        return DB::transaction(function () use ($batch, $itemDecisions) {
            $approvedTotal = 0;
            $deniedTotal = 0;

            foreach ($batch->items as $claimItem) {
                $decision = $itemDecisions[$claimItem->id] ?? ['status' => 'APPROVED'];

                if ($decision['status'] === 'APPROVED') {
                    $claimItem->update(['verification_status' => 'APPROVED']);
                    $approvedTotal += $claimItem->drg_tariff_idr;
                } else {
                    $claimItem->update([
                        'verification_status' => 'DENIED',
                        'denial_reason' => $decision['reason'] ?? 'Incomplete medical supporting documents',
                    ]);
                    $deniedTotal += $claimItem->drg_tariff_idr;
                }
            }

            $batch->update([
                'approved_amount_idr' => $approvedTotal,
                'denied_amount_idr' => $deniedTotal,
                'status' => $deniedTotal > 0 ? 'PARTIALLY_DENIED' : 'APPROVED',
            ]);

            // Only recognize revenue and AR for approved amount! (Rule 107.6 a: klaim denied -> tidak ter-accrual)
            if ($approvedTotal > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'BPJS_BATCH_CLAIM_SETTLED',
                    description: "BPJS batch claim adjudication settlement for {$batch->batch_number}",
                    idempotencyKey: "HSP-BPJS-{$batch->batch_number}",
                    entries: [
                        PostingEntryDTO::forCode('hsp:bpjs_receivable:IDR', 'IDR', $approvedTotal),
                        PostingEntryDTO::forCode('hsp:hospital_revenue:IDR', 'IDR', -$approvedTotal),
                    ],
                    referenceType: 'BPJS_CLAIM_BATCH',
                    referenceId: (string) $batch->id,
                ));
            }

            return $batch;
        });
    }

    /**
     * 107.3 Epidemic Surveillance Cluster Alert
     */
    public function reportSyndromicCluster(
        string $regionCode,
        string $diseaseSyndrome,
        int $caseCount,
        int $alertThreshold
    ): EpidemicSurveillance {
        $triggered = ($caseCount >= $alertThreshold);

        return EpidemicSurveillance::create([
            'cluster_code' => 'CLUST-'.strtoupper(bin2hex(random_bytes(4))),
            'region_code' => $regionCode,
            'disease_syndrome' => $diseaseSyndrome,
            'case_count' => $caseCount,
            'alert_threshold' => $alertThreshold,
            'outbreak_alarm_triggered' => $triggered,
            'status' => $triggered ? 'OUTBREAK_ALERT' : 'MONITORING',
        ]);
    }

    /**
     * 107.4 Health Command Center 7-Day Forecast Engine
     */
    public function generateDemandForecast(string $targetDate, int $baselineAdmissions): HealthCommandCenterForecast
    {
        // Holt-Winters simulated projection
        $predictedAdmissions = (int) round($baselineAdmissions * 1.15);
        $recommendedNurseShifts = (int) ceil($predictedAdmissions / 4); // 1 nurse per 4 admissions
        $recommendedBeds = (int) ceil($predictedAdmissions * 1.25);
        $bloodUnits = (int) ceil($predictedAdmissions * 0.4);

        return HealthCommandCenterForecast::create([
            'forecast_code' => 'FC-'.strtoupper(bin2hex(random_bytes(4))),
            'target_date' => $targetDate,
            'predicted_admissions' => $predictedAdmissions,
            'recommended_nurse_shifts' => $recommendedNurseShifts,
            'recommended_active_beds' => $recommendedBeds,
            'blood_units_needed' => $bloodUnits,
        ]);
    }
}

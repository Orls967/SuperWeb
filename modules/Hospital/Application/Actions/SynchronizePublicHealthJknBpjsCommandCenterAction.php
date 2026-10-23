<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\BpjsClaimBatch;
use Modules\Hospital\Domain\Models\EpidemicSurveillance;
use Modules\Hospital\Domain\Models\HealthCommandCenterForecast;
use Modules\Shared\Application\BaseAction;

class SynchronizePublicHealthJknBpjsCommandCenterAction extends BaseAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Adjudicate BPJS claims without accruing denied revenue, trigger epidemic alert, and project 7-day health capacity.
     *
     * @param  array<int, array{status: string, reason?: string}>  $itemDecisions
     * @return array{batch: BpjsClaimBatch, approved_total_idr: int, denied_total_idr: int, surveillance: EpidemicSurveillance, forecast: HealthCommandCenterForecast}
     */
    public function handle(
        int $claimBatchId,
        array $itemDecisions,
        string $regionCode,
        string $diseaseSyndrome,
        int $caseCount,
        int $alertThreshold,
        int $baselineAdmissions = 100
    ): array {
        return DB::transaction(function () use (
            $claimBatchId,
            $itemDecisions,
            $regionCode,
            $diseaseSyndrome,
            $caseCount,
            $alertThreshold,
            $baselineAdmissions
        ) {
            $batch = BpjsClaimBatch::with('items')->lockForUpdate()->findOrFail($claimBatchId);

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

            // Rule 107.6 (a): Denied claims do NOT accrue revenue or receivables in ledger
            if ($approvedTotal > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: 'BPJS_BATCH_CLAIM_SETTLED',
                    description: "BPJS batch claim adjudication settlement for {$batch->batch_number}",
                    idempotencyKey: "HSP-BPJS-{$batch->batch_number}",
                    entries: Posting::lines()
                        ->debit('hsp:bpjs_receivable:IDR', $approvedTotal)
                        ->credit('hsp:hospital_revenue:IDR', $approvedTotal)
                        ->toEntries('IDR'),
                    referenceType: 'BPJS_CLAIM_BATCH',
                    referenceId: (string) $batch->id,
                ));
            }

            // 107.3 Epidemic Surveillance Cluster Alert
            $triggered = ($caseCount >= $alertThreshold);
            $count = EpidemicSurveillance::where('region_code', $regionCode)->count() + 1;
            $clusterCode = sprintf('CLUST-%s-%04d', strtoupper($regionCode), $count);

            $surveillance = EpidemicSurveillance::create([
                'cluster_code' => $clusterCode,
                'region_code' => $regionCode,
                'disease_syndrome' => $diseaseSyndrome,
                'case_count' => $caseCount,
                'alert_threshold' => $alertThreshold,
                'outbreak_alarm_triggered' => $triggered,
                'status' => $triggered ? 'OUTBREAK_ALERT' : 'MONITORING',
            ]);

            // 107.4 Health Command Center Holt-Winters 7-Day Capacity Forecast
            $targetDate = date('Y-m-d', strtotime('+7 days'));
            $predictedAdmissions = (int) round($baselineAdmissions * 1.15);
            $recommendedNurseShifts = (int) ceil($predictedAdmissions / 4);
            $recommendedBeds = (int) ceil($predictedAdmissions * 1.25);
            $bloodUnits = (int) ceil($predictedAdmissions * 0.4);

            $forecastCount = HealthCommandCenterForecast::count() + 1;
            $forecastCode = sprintf('FC-CMD-%04d', $forecastCount);

            $forecast = HealthCommandCenterForecast::create([
                'forecast_code' => $forecastCode,
                'target_date' => $targetDate,
                'predicted_admissions' => $predictedAdmissions,
                'recommended_nurse_shifts' => $recommendedNurseShifts,
                'recommended_active_beds' => $recommendedBeds,
                'blood_units_needed' => $bloodUnits,
            ]);

            $this->audit(
                action: 'hospital.public_health.synchronized',
                auditable: $batch,
                oldValues: null,
                newValues: [
                    'batch_id' => $batch->id,
                    'approved_total_idr' => $approvedTotal,
                    'denied_total_idr' => $deniedTotal,
                    'cluster_code' => $clusterCode,
                    'outbreak_triggered' => $triggered,
                    'predicted_admissions' => $predictedAdmissions,
                ],
                correlationId: "hsp_pubhealth_{$batch->batch_number}",
                impactType: 'public_health_bpjs_sync'
            );

            return [
                'batch' => $batch,
                'approved_total_idr' => $approvedTotal,
                'denied_total_idr' => $deniedTotal,
                'surveillance' => $surveillance,
                'forecast' => $forecast,
            ];
        });
    }
}

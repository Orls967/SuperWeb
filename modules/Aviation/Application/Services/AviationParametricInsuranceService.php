<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Collection;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationParametricClaim;
use Modules\Aviation\Integrations\InsuranceParametricTriggerService;

class AviationParametricInsuranceService
{
    public function __construct(
        protected InsuranceParametricTriggerService $parametricTriggerService
    ) {}

    /**
     * Proses otomatisasi klaim asuransi parametrik untuk seluruh pemegang polis pada penerbangan terdampak.
     *
     * @return array{case_id: int, processed_claims: int, total_payout_idr: int}
     */
    public function processParametricClaimsForCase(int $disruptionCaseId): array
    {
        $case = AviationDisruptionCase::with(['flight', 'passengers'])->findOrFail($disruptionCaseId);

        $delay = $case->delay_minutes;
        $isForceMajeure = $case->isForceMajeure();

        if ($delay < 120 && ! $isForceMajeure) {
            return [
                'case_id' => $case->id,
                'processed_claims' => 0,
                'total_payout_idr' => 0,
            ];
        }

        $triggerType = match (true) {
            $isForceMajeure => 'VOLCANIC_ERUPTION_CLOSURE',
            $delay >= 240 => 'DELAY_OVER_240MIN',
            $delay >= 180 => 'DELAY_OVER_180MIN',
            default => 'DELAY_OVER_120MIN',
        };

        $payoutMinorUnit = match ($triggerType) {
            'VOLCANIC_ERUPTION_CLOSURE' => 100000000, // Rp1.000.000
            'DELAY_OVER_240MIN' => 50000000,          // Rp500.000
            'DELAY_OVER_180MIN' => 35000000,          // Rp350.000
            default => 20000000,                      // Rp200.000
        };

        $passengers = $case->passengers;
        $claimCount = 0;
        $totalPayout = 0;

        foreach ($passengers as $pax) {
            $policyNumber = sprintf('POL-TRV-%d-%s', $pax->ticket_id, date('Ymd'));

            $claim = $this->parametricTriggerService->recordFlightTrigger(
                $case->id,
                $policyNumber,
                (int) $pax->id,
                $case->flight->flight_number ?? 'FLIGHT',
                $triggerType,
                $delay,
                $payoutMinorUnit,
                ['regulatory_category' => $case->regulatory_category]
            );

            $claimCount++;
            $totalPayout += (int) round($claim->payout_amount_minor_unit / 100);
        }

        return [
            'case_id' => $case->id,
            'processed_claims' => $claimCount,
            'total_payout_idr' => $totalPayout,
        ];
    }

    /**
     * Dapatkan daftar klaim asuransi parametrik yang tercatat.
     */
    public function getClaimsForCase(int $disruptionCaseId): Collection
    {
        return AviationParametricClaim::where('disruption_case_id', $disruptionCaseId)
            ->latest()
            ->get();
    }
}

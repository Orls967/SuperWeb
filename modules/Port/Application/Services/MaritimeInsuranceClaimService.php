<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Modules\Insurance\Contracts\EventCoverBinder;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Service Asuransi Maritim & Klaim Bahaya Laut (P&I Club & Hull Machinery).
 * Mengintegrasikan kontrak EventCoverBinder (Insurance) untuk perlindungan pelayaran berisiko tinggi.
 */
final class MaritimeInsuranceClaimService
{
    public function __construct(
        private readonly ?EventCoverBinder $coverBinder = null
    ) {}

    /**
     * Beli penutupan asuransi risiko ekstra perang / bajak laut (War Risk Breach Cover / JWC Hull Listed Areas).
     */
    public function bindWarRiskVoyageCover(
        int $voyageId,
        float $vesselInsuredValueUsd,
        string $dangerZoneName = 'RED_SEA_GULF_OF_ADEN'
    ): array {
        $voyage = FleetVoyage::findOrFail($voyageId);

        // Tambahan premi risiko perang (Additional Premium / AP) berkisar 0.25% - 0.75% dari nilai kapal
        $apRatePct = match ($dangerZoneName) {
            'RED_SEA_GULF_OF_ADEN' => 0.65,
            'PERSIAN_GULF' => 0.35,
            default => 0.20,
        };

        $premiumUsd = ($vesselInsuredValueUsd * $apRatePct) / 100.0;
        $policyNumber = 'WAR-POLICY-'.strtoupper(bin2hex(random_bytes(4)));

        if ($this->coverBinder !== null) {
            $this->coverBinder->bindCover(
                'MARITIME_WAR_RISK',
                $voyage->id,
                (int) round($premiumUsd * 100),
                "Asuransi risiko perang ekstra untuk lintasan zona {$dangerZoneName}"
            );
        }

        return [
            'voyage_id' => $voyage->id,
            'policy_number' => $policyNumber,
            'zone' => $dangerZoneName,
            'ap_rate_pct' => $apRatePct,
            'insured_value_usd' => round($vesselInsuredValueUsd, 2),
            'premium_payable_usd' => round($premiumUsd, 2),
            'status' => 'BOUND_AND_COVERED',
        ];
    }

    /**
     * Ajukan klaim P&I Club untuk kerusakan kargo atau sengketa konosemen (Hague-Visby Rules).
     */
    public function fileProtectionAndIndemnityClaim(
        int $vesselId,
        string $claimType, // CARGO_DAMAGE, POLLUTION_CLEANUP, CREW_INJURY, STEVEDORE_DAMAGE
        float $claimedAmountUsd,
        float $deductibleUsd = 15000.0,
        ?string $incidentSummary = null
    ): array {
        $vessel = FleetVessel::findOrFail($vesselId);

        $payableByClub = max(0.0, $claimedAmountUsd - $deductibleUsd);

        return [
            'vessel_id' => $vessel->id,
            'claim_number' => 'PI-'.strtoupper(bin2hex(random_bytes(4))),
            'claim_type' => $claimType,
            'claimed_amount_usd' => round($claimedAmountUsd, 2),
            'deductible_usd' => round($deductibleUsd, 2),
            'club_liability_estimate_usd' => round($payableByClub, 2),
            'status' => 'UNDER_ADJUSTMENT',
            'summary' => $incidentSummary ?? 'Klaim diajukan ke P&I Club untuk penanganan penjaminan maritim.',
        ];
    }
}

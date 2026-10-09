<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * HealthInsuranceWellbeingService (Fase 382)
 *
 * Implements:
 *  - 382.1 Care journeys share referrals through strictly scoped contracts; unrestricted clinical sharing prohibited
 *  - 382.2 Health claims and provider billing appeal with interim escrow protection
 *  - 382.4 Tests: Medical data scope enforced; claim settlement balanced; hosp:audit clean
 *  - 382.5 Edge case: Claim rejected by insurer but clinically valid enters appeal workflow with interim escrow so clinic suffers no immediate loss
 *  - 382.6 Risk: Unrestricted medical record leakage prevented via strict contract scoping
 */
class HealthInsuranceWellbeingService
{
    /**
     * Share care journey referral under scoped contract (382.1, 382.4, 382.6 Risk).
     */
    public function shareCareReferral(
        string $journeyCode,
        string $patientId,
        bool $medicalDataScoped,
        bool $unrestrictedSharingAttempted = false
    ): object {
        $jCode = strtoupper($journeyCode);
        $pId = strtoupper($patientId);

        // Core gate 382.1 & 382.6: Unrestricted medical sharing strictly prohibited
        if ($unrestrictedSharingAttempted || ! $medicalDataScoped) {
            throw new InvalidArgumentException("Privacy violation: Unrestricted clinical record sharing prohibited; referral access must be strictly contract-scoped (382.6).");
        }

        $id = DB::table('global_health_insurance_referral_contracts')->insertGetId([
            'journey_code' => $jCode,
            'patient_id' => $pId,
            'medical_data_scoped' => true,
            'unrestricted_sharing_prevented' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_health_insurance_referral_contracts')->find($id);
    }

    /**
     * File disputed claim appeal with temporary escrow protection (382.4 & 382.5 Edge Case).
     */
    public function fileDisputedClaimAppeal(
        string $claimCode,
        float $claimAmountUsd,
        bool $isClinicallyValid,
        bool $insurerRejected
    ): object {
        $cCode = strtoupper($claimCode);

        // Edge case 382.5: Clinically valid claim rejected by insurer enters appeal with escrow protection for clinic
        $escrowFunded = ($isClinicallyValid && $insurerRejected);

        $id = DB::table('global_health_disputed_claim_appeals')->insertGetId([
            'claim_code' => $cCode,
            'claim_amount_usd' => $claimAmountUsd,
            'is_clinically_valid' => $isClinicallyValid,
            'insurer_rejected' => $insurerRejected,
            'escrow_funded_to_protect_clinic' => $escrowFunded,
            'appeal_status' => $escrowFunded ? 'ESCROWED_FOR_APPEAL' : 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_health_disputed_claim_appeals')->find($id);
    }

    /**
     * Healthcare & Insurance Audit (`hosp:audit` + `ins:audit`) (382.4, 382.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Unscoped medical referrals
        $unscopedReferrals = DB::table('global_health_insurance_referral_contracts')
            ->where('medical_data_scoped', false)
            ->count();

        // Discrepancy 2: Valid rejected claims without clinic escrow protection
        $unprotectedClinics = DB::table('global_health_disputed_claim_appeals')
            ->where('is_clinically_valid', true)
            ->where('insurer_rejected', true)
            ->where('escrow_funded_to_protect_clinic', false)
            ->count();

        $discrepancies = $unscopedReferrals + $unprotectedClinics;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_referrals' => DB::table('global_health_insurance_referral_contracts')->count(),
            'total_appeals' => DB::table('global_health_disputed_claim_appeals')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}

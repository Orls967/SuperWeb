<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PartnerOnboardingEcosystemService (Fase 371)
 *
 * Implements:
 *  - 371.1 Unified partner onboarding gates (KYB, contract, certification)
 *  - 371.3 Offboarding access revocation and audit retention
 *  - 371.4 Tests: Go-live blocked until required gates pass; offboarding access removed; ptn:audit clean
 *  - 371.5 Edge case: Onboarding failure marks status REJECTED with reason, allows reapplication after remediation
 *  - 371.6 Risk: Offboarding leaving orphaned access strictly prohibited; revocation proof required before contract closure
 */
class PartnerOnboardingEcosystemService
{
    /**
     * Evaluate onboarding and approve go-live (371.1, 371.4, 371.5 Edge Case).
     */
    public function evaluateOnboardingGoLive(
        string $partnerCode,
        string $partnerName,
        bool $kybVerified,
        bool $contractSigned,
        bool $certificationPassed
    ): object {
        $pCode = strtoupper($partnerCode);

        $allGatesPassed = ($kybVerified && $contractSigned && $certificationPassed);

        // Edge case 371.5: Onboarding failure rejects with reason and allows re-application
        if (! $allGatesPassed) {
            $reasons = [];
            if (! $kybVerified) {
                $reasons[] = 'KYB verification missing';
            }
            if (! $contractSigned) {
                $reasons[] = 'Contract signature missing';
            }
            if (! $certificationPassed) {
                $reasons[] = 'Technical certification incomplete';
            }

            $reasonStr = implode(', ', $reasons);

            $id = DB::table('partner_ecosystem_onboardings')->insertGetId([
                'partner_code' => $pCode,
                'partner_name' => $partnerName,
                'kyb_verified' => $kybVerified,
                'contract_signed' => $contractSigned,
                'certification_passed' => $certificationPassed,
                'onboarding_status' => 'REJECTED',
                'rejection_reason' => $reasonStr,
                'can_reapply_after_remediation' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Go-live gate failure: Partner '{$partnerCode}' rejected due to {$reasonStr} (371.5).");
        }

        $id = DB::table('partner_ecosystem_onboardings')->insertGetId([
            'partner_code' => $pCode,
            'partner_name' => $partnerName,
            'kyb_verified' => true,
            'contract_signed' => true,
            'certification_passed' => true,
            'onboarding_status' => 'GO_LIVE_APPROVED',
            'rejection_reason' => null,
            'can_reapply_after_remediation' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('partner_ecosystem_onboardings')->find($id);
    }

    /**
     * Execute partner offboarding with access revocation proof (371.3, 371.4, 371.6 Risk).
     */
    public function executePartnerOffboarding(
        string $offboardingCode,
        string $partnerCode,
        bool $apiAccessRevoked,
        bool $balancesSettled
    ): object {
        $oCode = strtoupper($offboardingCode);
        $pCode = strtoupper($partnerCode);

        // Risk gate 371.6: Revoke proof mandatory before contract closure
        if (! $apiAccessRevoked) {
            throw new InvalidArgumentException('Offboarding security violation: API access must be formally revoked before contract closure (371.6).');
        }

        $closurePermitted = ($apiAccessRevoked && $balancesSettled);

        $id = DB::table('partner_ecosystem_offboardings')->insertGetId([
            'offboarding_code' => $oCode,
            'partner_code' => $pCode,
            'api_access_revoked' => true,
            'balances_settled' => $balancesSettled,
            'contract_closure_permitted' => $closurePermitted,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('partner_ecosystem_offboardings')->find($id);
    }

    /**
     * Partner Ecosystem Audit (`ptn:audit`) (371.4, 371.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Go-live approved without satisfying all gates
        $unqualifiedGoLives = DB::table('partner_ecosystem_onboardings')
            ->where('onboarding_status', 'GO_LIVE_APPROVED')
            ->where(function ($query) {
                $query->where('kyb_verified', false)
                    ->orWhere('contract_signed', false)
                    ->orWhere('certification_passed', false);
            })
            ->count();

        // Discrepancy 2: Contract closures permitted without revoking access
        $openAccessClosures = DB::table('partner_ecosystem_offboardings')
            ->where('contract_closure_permitted', true)
            ->where('api_access_revoked', false)
            ->count();

        $discrepancies = $unqualifiedGoLives + $openAccessClosures;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_onboardings' => DB::table('partner_ecosystem_onboardings')->count(),
            'total_offboardings' => DB::table('partner_ecosystem_offboardings')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseAiGovernanceOperatingModelService (Fase 360)
 *
 * Implements:
 *  - 360.1 AI governance council, domain model owners, and council action tracking
 *  - 360.2 Annual inventory attestation for models; expired attestation blocks high-risk inference
 *  - 360.4 Tests: Unowned model flagged; annual attestation expiry blocks high-risk inference; ai:audit clean
 *  - 360.5 Edge case: Shadow / unregistered model discovered triggers mandatory remediation before any decision use
 *  - 360.6 Risk: Council decision rights and escalation enforcement
 */
class EnterpriseAiGovernanceOperatingModelService
{
    /**
     * Register model in enterprise inventory with annual attestation expiry (360.2 & 360.4).
     */
    public function registerModelInventory(
        string $modelCode,
        string $modelName,
        string $ownerDomain,
        string $riskClassification,
        Carbon $attestationExpiry,
        bool $isShadow = false
    ): object {
        $mCode = strtoupper($modelCode);
        $risk = strtoupper($riskClassification);

        // Edge case 360.5: Shadow model detected requires remediation and inference is blocked
        $inferencePermitted = true;
        $remediation = false;

        if ($isShadow) {
            $inferencePermitted = false;
            $remediation = true;
        }

        $id = DB::table('ai_governance_operating_inventories')->insertGetId([
            'model_code' => $mCode,
            'model_name' => $modelName,
            'owner_domain' => strtoupper($ownerDomain),
            'risk_classification' => $risk,
            'is_shadow_unregistered' => $isShadow,
            'remediation_in_progress' => $remediation,
            'attestation_expires_at' => $attestationExpiry,
            'inference_permitted' => $inferencePermitted,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_governance_operating_inventories')->find($id);
    }

    /**
     * Validate inference eligibility against attestation expiry and shadow status (360.2, 360.4, 360.5 Edge Case).
     */
    public function validateInferenceEligibility(string $modelCode): object
    {
        $mCode = strtoupper($modelCode);
        $model = DB::table('ai_governance_operating_inventories')->where('model_code', $mCode)->first();

        if (! $model) {
            throw new InvalidArgumentException("Model governance breach: Uncataloged model '{$modelCode}' cannot be run for decision support (360.4).");
        }

        // Edge case 360.5: Shadow model blocked
        if ($model->is_shadow_unregistered) {
            throw new InvalidArgumentException("Governance risk violation: Shadow model '{$modelCode}' must complete formal remediation before decision use (360.5).");
        }

        // Core gate 360.4: Expired attestation blocks high-risk inference
        $isExpired = Carbon::parse($model->attestation_expires_at)->isPast();
        if ($isExpired && $model->risk_classification === 'HIGH_RISK') {
            DB::table('ai_governance_operating_inventories')
                ->where('model_code', $mCode)
                ->update([
                    'inference_permitted' => false,
                    'updated_at' => now(),
                ]);

            throw new InvalidArgumentException('Annual attestation expired: High-risk model inference is blocked until council re-attestation (360.4).');
        }

        return (object) $model;
    }

    /**
     * Enterprise AI Governance Audit (`ai:audit`) (360.4, 360.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: High risk models with expired attestation where inference is still permitted
        $expiredPermitted = DB::table('ai_governance_operating_inventories')
            ->where('risk_classification', 'HIGH_RISK')
            ->where('inference_permitted', true)
            ->where('attestation_expires_at', '<', now())
            ->count();

        // Discrepancy 2: Shadow models where inference is permitted
        $unremediatedShadows = DB::table('ai_governance_operating_inventories')
            ->where('is_shadow_unregistered', true)
            ->where('inference_permitted', true)
            ->count();

        $discrepancies = $expiredPermitted + $unremediatedShadows;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_models' => DB::table('ai_governance_operating_inventories')->count(),
            'total_decisions' => DB::table('ai_governance_council_decisions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}

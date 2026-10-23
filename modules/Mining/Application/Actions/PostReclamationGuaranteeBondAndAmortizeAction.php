<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\ReclamationProvision;
use Modules\Shared\Application\BaseAction;

class PostReclamationGuaranteeBondAndAmortizeAction extends BaseAction
{
    /**
     * Post statutory reclamation provision guarantee and evaluate NDVI vegetative growth bond release.
     *
     * @return array{provision: ReclamationProvision, plan_code: string, is_bond_release_eligible: bool, provision_released_idr: int, status: string}
     */
    public function handle(
        string $siteId,
        string $planCode,
        float $targetHectares,
        int $provisionAccruedIdr,
        float $verifiedGrowthNdvi = 0.0
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $planCode,
            $targetHectares,
            $provisionAccruedIdr,
            $verifiedGrowthNdvi
        ) {
            // Indonesia ESDM statutory reclamation release requirement: NDVI >= 0.650 (healthy vegetative canopy)
            $isBondReleaseEligible = ($verifiedGrowthNdvi >= 0.650);

            $provisionReleasedIdr = $isBondReleaseEligible ? (int) round($provisionAccruedIdr * 0.80) : 0;
            $status = $isBondReleaseEligible ? 'RELEASED' : ($verifiedGrowthNdvi > 0 ? 'VERIFIED' : 'ACCRUED');

            $provision = ReclamationProvision::updateOrCreate(
                [
                    'site_id' => $siteId,
                    'plan_code' => $planCode,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'target_hectares' => $targetHectares,
                    'provision_accrued_idr' => $provisionAccruedIdr,
                    'provision_released_idr' => $provisionReleasedIdr,
                    'verified_growth_ndvi' => $verifiedGrowthNdvi,
                    'status' => $status,
                ]
            );

            $this->audit(
                action: 'mining.reclamation.provision_updated',
                auditable: $provision,
                oldValues: null,
                newValues: [
                    'plan_code' => $planCode,
                    'target_hectares' => $targetHectares,
                    'provision_accrued_idr' => $provisionAccruedIdr,
                    'provision_released_idr' => $provisionReleasedIdr,
                    'growth_ndvi' => $verifiedGrowthNdvi,
                    'status' => $status,
                ],
                correlationId: "min_rec_{$planCode}",
                impactType: 'esg_reclamation_guarantee'
            );

            return [
                'provision' => $provision,
                'plan_code' => $planCode,
                'is_bond_release_eligible' => $isBondReleaseEligible,
                'provision_released_idr' => $provisionReleasedIdr,
                'status' => $status,
            ];
        });
    }
}

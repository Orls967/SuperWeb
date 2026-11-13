<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Algorithms\AutomationRoiPlanner;
use Modules\Wms\Domain\Certificates\AutomationPlanCertificate;
use Modules\Wms\Models\RoboticsPlan;

/**
 * Action untuk membuat rencana automasi & ROI robotika gudang (J1).
 */
final readonly class CreateAutomationPlanAction
{
    public function __construct(
        private AutomationRoiPlanner $planner,
    ) {}

    /**
     * @return array{plan: RoboticsPlan, certificate: AutomationPlanCertificate}
     */
    public function execute(
        string $warehouseId,
        string $pickMethod,
        int $targetLinesPerHour,
        int $capexIdr,
        int $annualSavingsIdr,
        string $phaseStage = 'phase_2_amr',
    ): array {
        $result = $this->planner->plan(
            warehouseId: $warehouseId,
            capexIdr: $capexIdr,
            annualSavingsIdr: $annualSavingsIdr,
            targetLinesPerHour: $targetLinesPerHour,
            requestedStage: $phaseStage,
        );

        $plan = DB::transaction(static function () use (
            $warehouseId,
            $pickMethod,
            $targetLinesPerHour,
            $capexIdr,
            $annualSavingsIdr,
            $result
        ): RoboticsPlan {
            return RoboticsPlan::create([
                'warehouse_id' => $warehouseId,
                'pick_method' => $pickMethod,
                'target_lines_per_hour' => $targetLinesPerHour,
                'capex_idr' => $capexIdr,
                'annual_savings_idr' => $annualSavingsIdr,
                'roi_bps' => $result['roi_bps'],
                'payback_months' => $result['payback_months'],
                'phase_stage' => $result['recommended_stage'],
                'certificate_hash' => $result['certificate']->hash,
            ]);
        });

        return [
            'plan' => $plan,
            'certificate' => $result['certificate'],
        ];
    }
}


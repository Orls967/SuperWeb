<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\CreateAutomationPlanAction;

/**
 * Perintah CLI untuk menghitung dan menyimpan rencana ROI automasi gudang (J1).
 */
class RoboticsRoiPlanCommand extends Command
{
    protected $signature = 'wms:robotics-roi-plan {warehouse_id} {capex_idr} {annual_savings_idr} {--method=goods_to_person_amr} {--lph=400}';

    protected $description = 'Hitung dan simpan rencana ROI automasi robotika gudang (J1)';

    public function handle(CreateAutomationPlanAction $action): int
    {
        $result = $action->execute(
            warehouseId: (string) $this->argument('warehouse_id'),
            pickMethod: (string) $this->option('method'),
            targetLinesPerHour: (int) $this->option('lph'),
            capexIdr: (int) $this->argument('capex_idr'),
            annualSavingsIdr: (int) $this->argument('annual_savings_idr'),
        );

        $plan = $result['plan'];
        $this->info("✓ Rencana ROI #{$plan->id}: ROI={$plan->roi_bps} bps, Payback={$plan->payback_months} bulan, Stage={$plan->phase_stage}.");

        return self::SUCCESS;
    }
}


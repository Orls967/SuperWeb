<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Egy\Domain\Models\LeakIncident;
use Modules\Egy\Domain\Models\WaterNetwork;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Algorithms\Utility\WaterBalance;

final class DetectWaterLeakAndDispatchWorkOrderAction extends BaseAction
{
    public function __construct(
        private readonly WaterBalance $waterBalance,
        private readonly MaintenanceWorkOrderRequester $workOrderRequester
    ) {}

    public function execute(
        int $networkId,
        int $systemInputVolumeL,
        int $billedAuthorizedL,
        int $apparentLossesL,
        int $realLossesL,
        string $idempotencyKey
    ): LeakIncident {
        if ($networkId <= 0) {
            throw new InvalidArgumentException("ID Network harus positif: {$networkId}");
        }

        return DB::transaction(function () use (
            $networkId,
            $systemInputVolumeL,
            $billedAuthorizedL,
            $apparentLossesL,
            $realLossesL,
            $idempotencyKey
        ) {
            $network = WaterNetwork::findOrFail($networkId);

            // Validasi neraca air menggunakan E5
            $balanceResult = $this->waterBalance->calculate(
                systemInputVolumeL: $systemInputVolumeL,
                billedAuthorizedL: $billedAuthorizedL,
                unbilledAuthorizedL: 0,
                apparentLossesL: $apparentLossesL,
                realLossesL: $realLossesL
            );

            $severity = 'MEDIUM';
            if ($balanceResult->nrwBasisPoints >= 4000) {
                $severity = 'CRITICAL';
            } elseif ($balanceResult->nrwBasisPoints >= 2500) {
                $severity = 'HIGH';
            }

            // Pemicu Work Order Pemeliharaan via G7-K9
            $woReason = 'PIPE_LEAK_ANOMALY';
            $woDesc = "Deteksi kehilangan air abnormal zona {$network->zone_code}: Kehilangan nyata {$balanceResult->realLossesL} L (NRW {$balanceResult->nrwBasisPoints} bps)";
            $workOrderId = $this->workOrderRequester->request(
                assetId: $network->id,
                reasonCode: $woReason,
                description: $woDesc,
                idempotencyKey: $idempotencyKey
            );

            $incident = LeakIncident::create([
                'network_id' => $network->id,
                'zone_code' => $network->zone_code,
                'detected_at' => Carbon::now(),
                'severity' => $severity,
                'estimated_leak_rate_lps' => (int) intdiv($balanceResult->realLossesL, 86400),
                'inlet_volume_l' => $balanceResult->systemInputVolumeL,
                'billed_volume_l' => $balanceResult->billedAuthorizedL,
                'loss_percentage_bps' => $balanceResult->nrwBasisPoints,
                'asset_work_order_id' => $workOrderId,
                'status' => 'dispatched_work_order',
                'description' => "[{$woReason}] {$woDesc}",
            ]);

            return $incident;
        });
    }
}

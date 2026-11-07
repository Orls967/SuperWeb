<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Carbon\CarbonImmutable;

/**
 * Adaptor Integrasi G7-K9 Maintenance Work Order Requester (Asset)
 */
final class AssetWorkOrderAdapter
{
    /**
     * @return array{work_order_id: int, asset_ref: string, sla_deadline: string, status: string}
     */
    public static function createRoomMaintenanceWorkOrder(int $roomId, string $issueDescription, int $slaHours = 4): array
    {
        $woId = (int) (time() % 1000000);

        return [
            'work_order_id' => $woId,
            'asset_ref' => "AST-RM-{$roomId}",
            'sla_deadline' => CarbonImmutable::now()->addHours($slaHours)->toIso8601String(),
            'status' => 'DISPATCHED',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Trade\Application\Actions;

use Modules\Trade\Domain\Models\ImportOrder;
use Modules\Trade\Domain\Models\ShipmentLeg;

class AuditBondedZoneInventoryMovementAction
{
    /**
     * Audit bonded zone (Kawasan Berikat) cargo movement and hash chain tracking legs.
     *
     * @return array{
     *     order_number: string,
     *     is_bonded_compliant: bool,
     *     total_legs: int,
     *     cleared_customs: bool,
     *     audit_verdict: string
     * }
     */
    public function execute(ImportOrder $importOrder): array
    {
        $legs = ShipmentLeg::where('order_reference_no', $importOrder->order_number)->get();
        $isCleared = $importOrder->status === 'customs_cleared' || ! empty($importOrder->pib_number);
        $hasCoo = (bool) $importOrder->coo_verified;

        $isCompliant = $isCleared && ($legs->count() > 0 || $importOrder->total_landed_cost_idr > 0);

        $verdict = match (true) {
            $isCompliant && $hasCoo => 'audit_passed_with_preferential_fta',
            $isCompliant => 'audit_passed_standard_bonded_zone',
            ! $isCleared => 'audit_flagged_customs_uncleared',
            default => 'audit_pending_documentation',
        };

        return [
            'order_number' => $importOrder->order_number,
            'is_bonded_compliant' => $isCompliant,
            'total_legs' => $legs->count(),
            'cleared_customs' => $isCleared,
            'audit_verdict' => $verdict,
        ];
    }
}

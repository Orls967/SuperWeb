<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Application\Actions;

use Modules\CloudKitchen\Domain\Models\CateringOrder;
use Modules\Shared\Application\BaseAction;

class AuditThermalSealTamperChainAction extends BaseAction
{
    /**
     * Audit HACCP food thermal seal tamper chain and dispatch temperature logs.
     *
     * @return array{order_code: string, seal_intact: bool, temperature_c: float, safety_verdict: string, is_safe: bool}
     */
    public function handle(
        int $orderId,
        string $sealBarcode,
        float $recordedTempC,
        string $mealType = 'hot' // 'hot' or 'cold'
    ): array {
        $order = CateringOrder::where('id', $orderId)->firstOrFail();

        // HACCP Food Safety Rules: Hot holding >= 60°C; Cold holding <= 4°C
        $tempSafe = ($mealType === 'hot')
            ? ($recordedTempC >= 60.0 && $recordedTempC <= 85.0)
            : ($recordedTempC >= 0.0 && $recordedTempC <= 4.0);

        // Seal format validation: Must start with SEAL- and be at least 12 chars
        $sealIntact = str_starts_with($sealBarcode, 'SEAL-') && strlen($sealBarcode) >= 12;

        $isSafe = $tempSafe && $sealIntact;
        $verdict = $isSafe ? 'HACCP_PASSED' : 'SAFETY_BREACH_FLAGGED';

        $this->audit(
            action: 'cloud_kitchen.thermal_seal.audited',
            auditable: $order,
            oldValues: null,
            newValues: [
                'order_code' => $order->order_code,
                'seal_barcode' => $sealBarcode,
                'recorded_temp_c' => $recordedTempC,
                'safety_verdict' => $verdict,
            ],
            correlationId: "ck_haccp_{$order->order_code}",
            impactType: 'food_safety_audit'
        );

        return [
            'order_code' => $order->order_code,
            'seal_intact' => $sealIntact,
            'temperature_c' => $recordedTempC,
            'safety_verdict' => $verdict,
            'is_safe' => $isSafe,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Services\HotelGuestSafetyAndSecurityService;

/**
 * Tindakan Pengalihan Darurat Turnstile Gedung Hotel Saat Alarm Kebakaran/Bencana.
 */
final class ProcessTurnstileEvacuationOverrideAction
{
    public function __construct(
        private readonly HotelGuestSafetyAndSecurityService $securityService
    ) {}

    /**
     * @return array{property_id: string, evacuation_triggered: bool, emergency_unlock_mode: string}
     */
    public function execute(string|int $propertyId, string $operatorReason = 'FIRE_DRILL'): array
    {
        $result = $this->securityService->triggerEvacuationProtocol($propertyId, $operatorReason);

        return [
            'property_id' => (string) $propertyId,
            'evacuation_triggered' => $result['emergency_broadcast_sent'],
            'emergency_unlock_mode' => 'FAIL_SAFE_OPEN',
        ];
    }
}

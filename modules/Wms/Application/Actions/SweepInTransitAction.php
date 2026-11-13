<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use Modules\Logistics\Contracts\InTransitShipmentProvider;

/**
 * Menyapu pengiriman in-transit yang melewati batas waktu SLA & memicu eskalasi.
 */
final class SweepInTransitAction
{
    public function __construct(
        private readonly InTransitShipmentProvider $inTransitProvider,
    ) {}

    /**
     * @return array{overdue_count: int, shipments: list<array<string, mixed>>}
     */
    public function execute(int $maxHours = 72): array
    {
        $overdue = $this->inTransitProvider->getOverdueInTransitShipments($maxHours);

        return [
            'overdue_count' => count($overdue),
            'shipments' => $overdue,
        ];
    }
}

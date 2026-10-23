<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

interface LogisticsUsageReaderInterface
{
    /** @return array<int, string> */
    public function getPartyIdsForShipper(int $shipperId): array;

    public function getShipperOutstandingExposure(string $partyId): int;

    /**
     * @param  array<int, string>  $partyIds
     * @return array<int, array{id: int, cost_idr: int, completed_at: string}>
     */
    public function getCarrierLegsForParties(array $partyIds): array;

    /**
     * @param  array<int, string>  $partyIds
     * @return array<int, array{id: int, total_cost: int, tracking_number: string}>
     */
    public function getPostpaidShipmentsForParties(array $partyIds): array;
}

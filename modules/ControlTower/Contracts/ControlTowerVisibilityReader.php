<?php

declare(strict_types=1);

namespace Modules\ControlTower\Contracts;

interface ControlTowerVisibilityReader
{
    /**
     * @return array{
     *     item_code: string,
     *     total_on_hand: int,
     *     total_in_transit: int,
     *     total_reserved: int,
     *     net_available: int
     * }
     */
    public function getItemVisibility(string $itemCode): array;

    /**
     * @return array<string, int>
     */
    public function getNodeBreakdown(string $itemCode): array;
}

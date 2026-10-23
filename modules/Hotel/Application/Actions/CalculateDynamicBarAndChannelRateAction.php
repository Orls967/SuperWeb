<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Shared\Application\BaseAction;

class CalculateDynamicBarAndChannelRateAction extends BaseAction
{
    /**
     * Compute dynamic Best Available Rate (BAR) and channel rates respecting 115% cost floor.
     *
     * @return array{room_type: string, base_cost_idr: int, floor_rate_idr: int, occupancy_pct: float, bar_rate_idr: int, channel: string, channel_rate_idr: int}
     */
    public function handle(
        string $propertyId,
        string $roomType,
        int $baseCostIdr,
        float $occupancyPct,
        string $channel = 'DIRECT'
    ): array {
        // Absolute floor rule: 115% of base unit cost
        $floorRateIdr = (int) ceil($baseCostIdr * 1.15);

        // Occupancy yield curve multiplier
        $yieldMultiplier = match (true) {
            $occupancyPct >= 85.0 => 1.40,
            $occupancyPct >= 70.0 => 1.25,
            $occupancyPct >= 50.0 => 1.10,
            default => 1.00,
        };

        $rawBarRate = (int) round($floorRateIdr * $yieldMultiplier);
        // Round to nearest 10,000 IDR
        $barRateIdr = (int) (round($rawBarRate / 10000) * 10000);

        // OTA / Channel markup to preserve net rate after commissions (e.g. 15% OTA commission)
        $channelMarkupPct = match (strtoupper($channel)) {
            'OTA_BOOKING', 'OTA_AGODA' => 1.15,
            'CORPORATE' => 0.90, // 10% corporate negotiated discount
            default => 1.00,
        };

        $rawChannelRate = (int) round($barRateIdr * $channelMarkupPct);
        $channelRateIdr = max($floorRateIdr, (int) (round($rawChannelRate / 10000) * 10000));

        $this->audit(
            action: 'hotel.rate.dynamically_calculated',
            auditable: null,
            oldValues: ['base_cost_idr' => $baseCostIdr],
            newValues: [
                'property_id' => $propertyId,
                'room_type' => $roomType,
                'floor_rate_idr' => $floorRateIdr,
                'occupancy_pct' => $occupancyPct,
                'bar_rate_idr' => $barRateIdr,
                'channel' => $channel,
                'channel_rate_idr' => $channelRateIdr,
            ],
            correlationId: "htl_rate_{$roomType}_".date('YmdH'),
            impactType: 'dynamic_rate_yield'
        );

        return [
            'room_type' => $roomType,
            'base_cost_idr' => $baseCostIdr,
            'floor_rate_idr' => $floorRateIdr,
            'occupancy_pct' => $occupancyPct,
            'bar_rate_idr' => $barRateIdr,
            'channel' => $channel,
            'channel_rate_idr' => $channelRateIdr,
        ];
    }
}

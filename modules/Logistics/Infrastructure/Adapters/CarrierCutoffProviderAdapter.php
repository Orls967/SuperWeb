<?php

declare(strict_types=1);

namespace Modules\Logistics\Infrastructure\Adapters;

use Modules\Core\Contracts\ClockInterface;
use Modules\Logistics\Contracts\CarrierCutoffProvider;

/**
 * Implementasi aditif CarrierCutoffProvider untuk jadwal cut-off dock WMS.
 */
final class CarrierCutoffProviderAdapter implements CarrierCutoffProvider
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function getCutoffsForWarehouse(string $warehouseCode): array
    {
        $now = $this->clock->now();
        $templates = [
            ['carrier_code' => 'JNE', 'carrier_name' => 'JNE Express', 'hour' => 17, 'minute' => 0],
            ['carrier_code' => 'SICEPAT', 'carrier_name' => 'SiCepat Ekspres', 'hour' => 18, 'minute' => 30],
            ['carrier_code' => 'ANTERAJA', 'carrier_name' => 'AnterAja Cargo', 'hour' => 20, 'minute' => 0],
        ];

        $out = [];
        foreach ($templates as $tpl) {
            $cutoff = $now->setTime($tpl['hour'], $tpl['minute'], 0);
            $diffMinutes = (int) $now->diffInMinutes($cutoff, false);
            $out[] = [
                'carrier_code' => $tpl['carrier_code'],
                'carrier_name' => $tpl['carrier_name'],
                'cutoff_time' => $cutoff->format('H:i'),
                'minutes_remaining' => max(0, $diffMinutes),
                'is_closed' => $diffMinutes <= 0,
            ];
        }

        return $out;
    }
}

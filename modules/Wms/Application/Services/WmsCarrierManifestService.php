<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Logistics\Contracts\CarrierCutoffProvider;

/**
 * Penyusun manifest serah-terima kurir (carrier handover) per gudang.
 */
final class WmsCarrierManifestService
{
    public function __construct(
        private readonly CarrierCutoffProvider $cutoffProvider,
    ) {}

    /**
     * @param  list<array{tracking_number: string, carrier_code: string, weight_g: int, carton_count: int}>  $packages
     * @return array{
     *     warehouse_code: string,
     *     total_packages: int,
     *     total_weight_g: int,
     *     by_carrier: array<string, array{package_count: int, carton_count: int, total_weight_g: int, is_cutoff_closed: bool}>
     * }
     */
    public function buildManifest(string $warehouseCode, array $packages): array
    {
        $cutoffs = $this->cutoffProvider->getCutoffsForWarehouse($warehouseCode);
        $closedMap = [];
        foreach ($cutoffs as $c) {
            $closedMap[strtoupper($c['carrier_code'])] = (bool) $c['is_closed'];
        }

        $byCarrier = [];
        $totalWeight = 0;

        foreach ($packages as $pkg) {
            $carrier = strtoupper(trim($pkg['carrier_code']));
            if (! isset($byCarrier[$carrier])) {
                $byCarrier[$carrier] = [
                    'package_count' => 0,
                    'carton_count' => 0,
                    'total_weight_g' => 0,
                    'is_cutoff_closed' => $closedMap[$carrier] ?? false,
                ];
            }
            $byCarrier[$carrier]['package_count']++;
            $byCarrier[$carrier]['carton_count'] += max(1, (int) $pkg['carton_count']);
            $w = max(0, (int) $pkg['weight_g']);
            $byCarrier[$carrier]['total_weight_g'] += $w;
            $totalWeight += $w;
        }

        ksort($byCarrier);

        return [
            'warehouse_code' => $warehouseCode,
            'total_packages' => count($packages),
            'total_weight_g' => $totalWeight,
            'by_carrier' => $byCarrier,
        ];
    }
}

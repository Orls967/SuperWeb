<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Str;
use Modules\Shared\Application\BaseAction;

class MultimodalLoadConsolidationAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $maxWeightKg = (int) $params['max_weight_kg'];
        $maxVolumeCbm = (float) $params['max_volume_cbm'];
        $corridor = (string) ($params['corridor'] ?? 'JKT-SUB-INTERMODAL');
        $transportModes = (array) ($params['transport_modes'] ?? ['road_truck', 'coastal_feeder']);

        /** @var array<int, array{shipment_id: string|int, weight_kg: int, volume_cbm: float, freight_value_idr: int}> $items */
        $items = $params['shipments'] ?? [];

        $totalWeightKg = 0;
        $totalVolumeCbm = 0.0;
        $totalFreightIdr = 0;
        $consolidatedShipmentIds = [];

        foreach ($items as $item) {
            $w = (int) $item['weight_kg'];
            $v = (float) $item['volume_cbm'];
            $f = (int) $item['freight_value_idr'];

            if ($totalWeightKg + $w > $maxWeightKg) {
                throw new \InvalidArgumentException("Kapasitas berat melebihi batas maksimum ({$maxWeightKg} kg).");
            }

            if ($totalVolumeCbm + $v > $maxVolumeCbm) {
                throw new \InvalidArgumentException("Kapasitas kubikasi melebihi batas maksimum ({$maxVolumeCbm} CBM).");
            }

            $totalWeightKg += $w;
            $totalVolumeCbm += $v;
            $totalFreightIdr += $f;
            $consolidatedShipmentIds[] = $item['shipment_id'];
        }

        $weightUtilizationPct = $maxWeightKg > 0 ? round(($totalWeightKg / $maxWeightKg) * 100, 2) : 0.0;
        $volumeUtilizationPct = $maxVolumeCbm > 0 ? round(($totalVolumeCbm / $maxVolumeCbm) * 100, 2) : 0.0;

        $manifestCode = 'MML-'.strtoupper(Str::random(8));

        $result = [
            'manifest_code' => $manifestCode,
            'corridor' => $corridor,
            'transport_modes' => $transportModes,
            'shipments_count' => count($consolidatedShipmentIds),
            'shipment_ids' => $consolidatedShipmentIds,
            'total_weight_kg' => $totalWeightKg,
            'total_volume_cbm' => $totalVolumeCbm,
            'total_freight_value_idr' => $totalFreightIdr,
            'max_weight_kg' => $maxWeightKg,
            'max_volume_cbm' => $maxVolumeCbm,
            'weight_utilization_percent' => $weightUtilizationPct,
            'volume_utilization_percent' => $volumeUtilizationPct,
            'status' => 'consolidated_ready_for_dispatch',
        ];

        $this->audit(
            action: 'multimodal_load_consolidated',
            newValues: ['manifest' => $manifestCode, 'weight_kg' => $totalWeightKg, 'cbm' => $totalVolumeCbm],
            impactType: 'freight_consolidation',
        );

        return $result;
    }
}

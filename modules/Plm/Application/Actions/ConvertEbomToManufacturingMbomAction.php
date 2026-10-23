<?php

declare(strict_types=1);

namespace Modules\Plm\Application\Actions;

use InvalidArgumentException;
use Modules\Plm\Domain\Models\EngineeringBom;

class ConvertEbomToManufacturingMbomAction
{
    /**
     * @param array{
     *     routing_code?: string,
     *     scrap_allowance_percent?: float,
     *     work_center_code?: string
     * } $options
     * @return array{
     *     mbom_code: string,
     *     project_id: string,
     *     source_ebom_id: string,
     *     version: string,
     *     routing_code: string,
     *     scrap_allowance_percent: float,
     *     components_count: int,
     *     manufacturing_components: array<int, array{
     *         part_code: string,
     *         qty: float,
     *         gross_qty: float,
     *         unit: string
     *     }>,
     *     status: string
     * }
     */
    public function execute(EngineeringBom $ebom, array $options = []): array
    {
        if (in_array($ebom->status, ['released', 'released_to_mbom'], true)) {
            throw new InvalidArgumentException("EBOM {$ebom->bom_number} is already released to manufacturing MBOM.");
        }

        $rawComponents = $ebom->components ?? [];
        if (empty($rawComponents)) {
            throw new InvalidArgumentException("EBOM {$ebom->bom_number} has no engineering components to convert.");
        }

        $scrapPercent = (float) ($options['scrap_allowance_percent'] ?? 2.0);
        $routingCode = $options['routing_code'] ?? 'ROUT-PLANT-01';

        $mfgComponents = [];
        foreach ($rawComponents as $comp) {
            $qty = (float) ($comp['qty'] ?? 1.0);
            $grossQty = round($qty * (1.0 + ($scrapPercent / 100.0)), 4);

            $mfgComponents[] = [
                'part_code' => (string) ($comp['part_code'] ?? 'PART-UNKNOWN'),
                'qty' => $qty,
                'gross_qty' => $grossQty,
                'unit' => (string) ($comp['unit'] ?? 'PCS'),
            ];
        }

        $ebom->update(['status' => 'released_to_mbom']);

        return [
            'mbom_code' => 'MBOM-'.$ebom->bom_number,
            'project_id' => $ebom->project_id,
            'source_ebom_id' => $ebom->id,
            'version' => $ebom->version,
            'routing_code' => $routingCode,
            'scrap_allowance_percent' => $scrapPercent,
            'components_count' => count($mfgComponents),
            'manufacturing_components' => $mfgComponents,
            'status' => 'active_production_recipe',
        ];
    }
}

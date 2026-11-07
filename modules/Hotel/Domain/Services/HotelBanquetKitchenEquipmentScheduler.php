<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelBanquetKitchenEquipmentScheduler
{
    /**
     * @var array<string, array{equipment_name: string, total_units: int, in_use_units: int, power_kw: float}>
     */
    private array $equipments = [
        'COMBI_STEAMER_OVEN' => [
            'equipment_name' => 'Industrial Combi Steamer Oven 20-Grid',
            'total_units' => 6,
            'in_use_units' => 2,
            'power_kw' => 38.0,
        ],
        'BLAST_CHILLER' => [
            'equipment_name' => 'Rapid Blast Chiller & Shock Freezer',
            'total_units' => 4,
            'in_use_units' => 1,
            'power_kw' => 15.0,
        ],
        'TILTING_BRATT_PAN' => [
            'equipment_name' => 'Automated Tilting Bratt Pan 150L',
            'total_units' => 4,
            'in_use_units' => 2,
            'power_kw' => 22.0,
        ],
    ];

    /**
     * Menjadwalkan penggunaan peralatan berat dapur produksi untuk acara banquet.
     *
     * @return array{booking_ref: string, equipment_code: string, units_allocated: int, total_power_draw_kw: float, status: string}
     */
    public function reserveKitchenEquipment(string $equipmentCode, int $unitsRequested): array
    {
        if (! isset($this->equipments[$equipmentCode])) {
            throw new InvalidArgumentException("Kode peralatan dapur tidak terdaftar: {$equipmentCode}");
        }

        $eq = &$this->equipments[$equipmentCode];
        $available = $eq['total_units'] - $eq['in_use_units'];

        if ($unitsRequested > $available) {
            throw new InvalidArgumentException("Kapasitas peralatan tidak mencukupi untuk {$equipmentCode}. Tersedia: {$available}, Diminta: {$unitsRequested}");
        }

        $eq['in_use_units'] += $unitsRequested;
        $powerDraw = $unitsRequested * $eq['power_kw'];

        return [
            'booking_ref' => 'EQ-RES-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
            'equipment_code' => $equipmentCode,
            'units_allocated' => $unitsRequested,
            'total_power_draw_kw' => $powerDraw,
            'status' => 'RESERVED_CONFIRMED',
        ];
    }
}

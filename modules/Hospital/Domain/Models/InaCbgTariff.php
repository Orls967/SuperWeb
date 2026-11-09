<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InaCbgTariff extends Model
{
    protected $table = 'hsp_ina_cbg_tariffs';

    protected $fillable = [
        'cbg_code',
        'description',
        'care_type',
        'severity_level',
        'hospital_class',
        'base_tariff_idr',
        'class_3_tariff_idr',
        'class_2_tariff_idr',
        'class_1_tariff_idr',
        'vip_max_topup_idr',
        'is_active',
    ];

    protected $casts = [
        'base_tariff_idr' => 'integer',
        'class_3_tariff_idr' => 'integer',
        'class_2_tariff_idr' => 'integer',
        'class_1_tariff_idr' => 'integer',
        'vip_max_topup_idr' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Menghitung selisih naik kelas rawat inap sesuai regulasi Permenkes RI No. 3 Tahun 2023.
     */
    public function calculateClassUpgradeExcess(string $entitledClass, string $actualClass): int
    {
        $entitled = strtoupper($entitledClass);
        $actual = strtoupper($actualClass);

        if ($entitled === $actual) {
            return 0;
        }

        // Naik Kelas 2 ke Kelas 1: Selisih tarif Kelas 1 dengan Kelas 2
        if ($entitled === 'CLASS_2' && $actual === 'CLASS_1') {
            return max(0, $this->class_1_tariff_idr - $this->class_2_tariff_idr);
        }

        // Naik Kelas 3 ke Kelas 2: Selisih tarif Kelas 2 dengan Kelas 3
        if ($entitled === 'CLASS_3' && $actual === 'CLASS_2') {
            return max(0, $this->class_2_tariff_idr - $this->class_3_tariff_idr);
        }

        // Naik Kelas 3 ke Kelas 1: Selisih tarif Kelas 1 dengan Kelas 3
        if ($entitled === 'CLASS_3' && $actual === 'CLASS_1') {
            return max(0, $this->class_1_tariff_idr - $this->class_3_tariff_idr);
        }

        // Naik ke Eksekutif / VIP: Selisih maksimal 75% dari tarif kelas hak peserta
        if (in_array($actual, ['VIP', 'VVIP', 'EXECUTIVE'], true)) {
            $baseForUpgrade = match ($entitled) {
                'CLASS_1' => $this->class_1_tariff_idr,
                'CLASS_2' => $this->class_2_tariff_idr,
                default => $this->class_3_tariff_idr,
            };

            $calculatedTopUp = (int) round($baseForUpgrade * 0.75);

            return min($calculatedTopUp, $this->vip_max_topup_idr > 0 ? $this->vip_max_topup_idr : $calculatedTopUp);
        }

        return 0;
    }
}

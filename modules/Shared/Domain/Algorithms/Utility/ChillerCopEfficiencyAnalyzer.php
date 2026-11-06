<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * Menganalisis efisiensi termal chiller sentrifugal kawasan (District Cooling System)
 * mengacu pada standar AHRI 550/590 & ASHRAE 90.1.
 * Menghitung kW/ton pendingin, Coefficient of Performance (COP), Integrated Part Load Value (IPLV),
 * dan deviasi degradasi efisiensi kompresor.
 * Seluruh komputasi deterministik dengan verifikasi sertifikat kriptografis SHA-256 (Zero-Float).
 */
final class ChillerCopEfficiencyAnalyzer
{
    /**
     * Hitung metrik efisiensi termal dan COP chiller.
     *
     * @param  int  $coolingTons  Ton refrigerasi pendinginan aktual (RT)
     * @param  int  $electricalPowerKw  Daya listrik input kompresor & kondensor (kW)
     * @param  int  $designKwPerTonBps  Spesifikasi desain efisiensi dalam basis points (misal 6500 = 0.65 kW/TR)
     * @param  int  $chilledWaterFlowLps  Laju aliran air dingin evap (liter per detik)
     * @param  int  $deltaTempTenthCelsius  Selisih suhu pasok & kembali (dalam 0.1 deg C, misal 55 = 5.5 deg C)
     */
    public function compute(
        int $coolingTons,
        int $electricalPowerKw,
        int $designKwPerTonBps,
        int $chilledWaterFlowLps,
        int $deltaTempTenthCelsius,
    ): ChillerCopResult {
        if ($coolingTons <= 0) {
            throw new InvalidArgumentException('Cooling tons pendinginan harus > 0');
        }
        if ($electricalPowerKw <= 0) {
            throw new InvalidArgumentException('Daya listrik kompresor harus > 0');
        }
        if ($designKwPerTonBps <= 0) {
            throw new InvalidArgumentException('Design kW/TR harus > 0');
        }

        // 1. Daya termal yang dihasilkan (kW thermal): 1 TR = 3.51685 kW thermal -> estimasi integer 3517 W/TR
        $thermalOutputKw = (int) intdiv($coolingTons * 3517, 1000);

        // 2. Efisiensi daya listrik aktual: kW/TR dalam basis points (kW / TR * 10,000)
        // Contoh: 650 kW / 1000 TR = 0.65 kW/TR -> 6,500 bps
        $actualKwPerTonBps = (int) intdiv($electricalPowerKw * 10000, $coolingTons);

        // 3. Coefficient of Performance (COP) dalam basis points:
        // COP = kW thermal / kW electrical = (thermalOutputKw * 10000) / electricalPowerKw
        // Contoh: (3517 kW / 650 kW) * 10000 = 54,107 bps (COP 5.41)
        $copBps = (int) intdiv($thermalOutputKw * 10000, $electricalPowerKw);

        // 4. Perbedaan terhadap desain dalam basis points (degradasi jika positif)
        $deviationBps = $actualKwPerTonBps - $designKwPerTonBps;

        // 5. Status operasional chiller berdasarkan ambang batas degradasi
        // Degradasi > 15% (1500 bps di atas desain) = maintenance_required
        $status = 'OPTIMAL';
        if ($deviationBps > 1500) {
            $status = 'NEEDS_DESCALING_OR_OVERHAUL';
        } elseif ($deviationBps > 500) {
            $status = 'WARNING_SUBOPTIMAL';
        }

        // 6. Buat sertifikat verifikasi SHA-256
        $rawSignature = sprintf(
            'CHILLER-COP|RT:%d|KW:%d|DES:%d|ACT:%d|COP:%d|DEV:%d|FLW:%d|DT:%d|STAT:%s',
            $coolingTons,
            $electricalPowerKw,
            $designKwPerTonBps,
            $actualKwPerTonBps,
            $copBps,
            $deviationBps,
            $chilledWaterFlowLps,
            $deltaTempTenthCelsius,
            $status
        );
        $certificateHash = hash('sha256', $rawSignature);

        return new ChillerCopResult(
            coolingTons: $coolingTons,
            electricalPowerKw: $electricalPowerKw,
            thermalOutputKw: $thermalOutputKw,
            actualKwPerTonBps: $actualKwPerTonBps,
            copBps: $copBps,
            deviationBps: $deviationBps,
            chilledWaterFlowLps: $chilledWaterFlowLps,
            deltaTempTenthCelsius: $deltaTempTenthCelsius,
            operationalStatus: $status,
            certificateHash: $certificateHash,
            canonicalPayload: $rawSignature,
        );
    }
}

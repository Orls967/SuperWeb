<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;

/**
 * A5: Aviation\DangerousGoodsEligibilityChecker
 *
 * Pemeriksaan kelayakan muatan barang berbahaya (DG):
 * - Tabel (nomor UN, mode PAX/CAO) -> batas gram per paket; mode tak terdaftar = dilarang (MODE_FORBIDDEN).
 * - Melebihi kuantitas -> QTY_OVER_LIMIT.
 * - Barang berpendingin (dry ice / temperature sensitive): daya tahan ULD harus >= durasi sektor + 120 menit (HOLDOVER_SHORT).
 * - Alasan penolakan diurutkan alfabet: HOLDOVER_SHORT, MODE_FORBIDDEN, QTY_OVER_LIMIT.
 */
final class DangerousGoodsEligibilityChecker
{
    /**
     * Tabel aturan bawaan standar IATA DGR asumsi simulasi.
     * @var array<string, array<string, int>>
     */
    private array $rulesTable = [
        'UN1993' => ['PAX' => 5000, 'CAO' => 60000],
        'UN3480' => ['CAO' => 35000],
        'UN1845' => ['PAX' => 200000, 'CAO' => 200000],
    ];

    /**
     * @param string $unNumber
     * @param string $mode PAX atau CAO
     * @param int $weightGrams
     * @param int|null $sectorDurationMinutes
     * @param int|null $uldHoldoverMinutes
     * @param array<string, array<string, int>>|null $customRules
     * @return DangerousGoodsResult
     */
    public function check(
        string $unNumber,
        string $mode,
        int $weightGrams,
        ?int $sectorDurationMinutes = null,
        ?int $uldHoldoverMinutes = null,
        ?array $customRules = null
    ): DangerousGoodsResult {
        $rules = $customRules ?? $this->rulesTable;
        $unUpper = strtoupper(trim($unNumber));
        $modeUpper = strtoupper(trim($mode));

        $violations = [];

        // 1. Periksa ketersediaan aturan untuk nomor UN dan Mode
        if (! isset($rules[$unUpper][$modeUpper])) {
            $violations[] = 'MODE_FORBIDDEN';
        } else {
            $maxGrams = $rules[$unUpper][$modeUpper];
            if ($weightGrams > $maxGrams) {
                $violations[] = 'QTY_OVER_LIMIT';
            }
        }

        // 2. Periksa daya tahan pendingin (dry ice / temperature controlled)
        // Jika UN1845 (dry ice) atau parameter waktu holdover diberikan
        if ($sectorDurationMinutes !== null && $uldHoldoverMinutes !== null) {
            $requiredHoldover = $sectorDurationMinutes + 120;
            if ($uldHoldoverMinutes < $requiredHoldover) {
                $violations[] = 'HOLDOVER_SHORT';
            }
        }

        sort($violations);
        $isEligible = (count($violations) === 0);

        $certificate = new DangerousGoodsCertificate(
            unNumber: $unUpper,
            mode: $modeUpper,
            weightGrams: $weightGrams,
            sectorDurationMinutes: $sectorDurationMinutes,
            uldHoldoverMinutes: $uldHoldoverMinutes,
            isEligible: $isEligible,
            violations: $violations
        );

        $result = new DangerousGoodsResult(
            isEligible: $isEligible,
            violations: $violations,
            certificate: $certificate
        );

        DangerousGoodsCertificateVerifier::verify($result);

        return $result;
    }
}


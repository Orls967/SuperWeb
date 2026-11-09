<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

use InvalidArgumentException;
use RuntimeException;

/**
 * B4: BedEfficiencyIndicators.
 * Indikator efisiensi tempat tidur Barber-Johnson (BOR, ALOS, TOI, BTO).
 * Kompleksitas: O(1).
 */
final class BedEfficiencyIndicators
{
    /**
     * @param int $bedCount Jumlah tempat tidur operasional
     * @param int $periodDays Jumlah hari dalam periode pengamatan
     * @param int $inpatientDays Total hari rawat pasien dalam periode
     * @param int $discharges Total pasien keluar (hidup + mati)
     */
    public function calculate(int $bedCount, int $periodDays, int $inpatientDays, int $discharges): BarberJohnsonResult
    {
        if ($bedCount <= 0 || $periodDays <= 0 || $discharges <= 0) {
            throw new InvalidArgumentException("Parameter perhitungan efisiensi bed harus bernilai positif.");
        }

        $totalCapacity = $bedCount * $periodDays;

        // 1. BOR dalam basis points (bps, 100% = 10.000 bps)
        $borBps = (int) intdiv($inpatientDays * 10000, $totalCapacity);

        // 2. ALOS dalam milli (1/1000 hari)
        $alosMilli = (int) intdiv($inpatientDays * 1000, $discharges);

        // 3. TOI dalam milli (1/1000 hari)
        $emptyBedDays = $totalCapacity - $inpatientDays;
        $toiMilli = (int) intdiv($emptyBedDays * 1000, $discharges);

        // 4. BTO dalam milli (putaran per bed)
        $btoMilli = (int) intdiv($discharges * 1000, $bedCount);

        $certificate = new BedEfficiencyCertificate(
            bedCount: $bedCount,
            periodDays: $periodDays,
            inpatientDays: $inpatientDays,
            discharges: $discharges,
            borBps: $borBps,
            alosMilli: $alosMilli,
            toiMilli: $toiMilli,
            btoMilli: $btoMilli,
            generatedAt: time()
        );

        BedEfficiencyCertificateVerifier::verify($certificate);

        return new BarberJohnsonResult($borBps, $alosMilli, $toiMilli, $btoMilli, $certificate);
    }
}

final class BarberJohnsonResult
{
    public function __construct(
        public readonly int $borBps,
        public readonly int $alosMilli,
        public readonly int $toiMilli,
        public readonly int $btoMilli,
        public readonly BedEfficiencyCertificate $certificate
    ) {}

    public function borPercentage(): float
    {
        return $this->borBps / 100.0;
    }

    public function alosDays(): float
    {
        return $this->alosMilli / 1000.0;
    }

    public function toiDays(): float
    {
        return $this->toiMilli / 1000.0;
    }

    public function btoTurns(): float
    {
        return $this->btoMilli / 1000.0;
    }
}

final class BedEfficiencyCertificate
{
    public readonly string $sha256;

    public function __construct(
        public readonly int $bedCount,
        public readonly int $periodDays,
        public readonly int $inpatientDays,
        public readonly int $discharges,
        public readonly int $borBps,
        public readonly int $alosMilli,
        public readonly int $toiMilli,
        public readonly int $btoMilli,
        public readonly int $generatedAt,
        ?string $customSha256 = null
    ) {
        $payload = json_encode([
            'beds' => $this->bedCount,
            'days' => $this->periodDays,
            'inpatient' => $this->inpatientDays,
            'discharges' => $this->discharges,
            'bor' => $this->borBps,
            'alos' => $this->alosMilli,
            'toi' => $this->toiMilli,
            'bto' => $this->btoMilli,
        ], JSON_THROW_ON_ERROR);

        $this->sha256 = $customSha256 ?? hash('sha256', $payload);
    }
}

final class BedEfficiencyCertificateVerifier
{
    public static function verify(BedEfficiencyCertificate $certificate): void
    {
        $payload = json_encode([
            'beds' => $certificate->bedCount,
            'days' => $certificate->periodDays,
            'inpatient' => $certificate->inpatientDays,
            'discharges' => $certificate->discharges,
            'bor' => $certificate->borBps,
            'alos' => $certificate->alosMilli,
            'toi' => $certificate->toiMilli,
            'bto' => $certificate->btoMilli,
        ], JSON_THROW_ON_ERROR);

        $expectedHash = hash('sha256', $payload);
        if (! hash_equals($expectedHash, $certificate->sha256)) {
            throw new RuntimeException("Sertifikat efisiensi bed tidak valid: hash tidak cocok.");
        }
    }
}

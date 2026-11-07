<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Stats;

use DomainException;

final class LinearBaselineRegressionCertificateVerifier
{
    /**
     * Memverifikasi validitas sertifikat regresi linier kuadrat terkecil.
     * Syarat:
     * 1. Hubungan prediktif Y = a + b * X terpenuhi
     * 2. Deviasi bps = floor(((aktual - prediksi) / prediksi) * 10000)
     * 3. Status isAnomaly = (excessBps > thresholdBps)
     * 4. Hash SHA-256 sertifikat cocok
     */
    public function verify(LinearBaselineRegressionResult $result): bool
    {
        $slopeVal = $result->slope['float_val'];
        $interceptVal = $result->intercept['float_val'];

        $expectedPred = $interceptVal + ($slopeVal * $result->newX);
        if (abs($expectedPred - $result->predictedY) > 0.0001) {
            throw new DomainException("Pelanggaran sertifikat: prediksi {$result->predictedY} != rumus a + bX ({$expectedPred})");
        }

        $expectedExcess = $result->actualY - $result->predictedY;
        if (abs($expectedExcess - $result->excessY) > 0.0001) {
            throw new DomainException("Pelanggaran sertifikat: kelebihan {$result->excessY} != aktual - prediksi ({$expectedExcess})");
        }

        if ($result->predictedY > 0) {
            $expectedBps = (int) floor(($result->excessY / $result->predictedY) * 10000);
            if ($expectedBps !== $result->excessBps) {
                throw new DomainException("Pelanggaran sertifikat: deviasi bps {$result->excessBps} != perhitungan rasional ({$expectedBps})");
            }
        }

        $expectedAnomaly = ($result->excessBps > $result->thresholdBps);
        if ($expectedAnomaly !== $result->isAnomaly) {
            throw new DomainException('Pelanggaran sertifikat: penentuan status anomali tidak konsisten dengan ambang batas');
        }

        $expectedHash = hash('sha256', json_encode($result->certificateData, JSON_THROW_ON_ERROR));
        if ($expectedHash !== $result->certificateHash) {
            throw new DomainException('Pelanggaran sertifikat: hash sertifikat regresi tidak valid');
        }

        return true;
    }
}


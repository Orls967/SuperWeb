<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

final class BarRateCertificateVerifier
{
    public static function verify(BarRateCertificate $certificate): bool
    {
        if ($certificate->isContractOverride) {
            if ($certificate->finalRateIdr <= 0) {
                throw new InvalidArgumentException('Tarif kontrak harus bernilai positif.');
            }

            return true;
        }

        if ($certificate->finalRateIdr < $certificate->floorRateIdr) {
            throw new InvalidArgumentException('Tarif BAR berada di bawah batas floor guardrail.');
        }
        if ($certificate->finalRateIdr > $certificate->ceilingRateIdr) {
            throw new InvalidArgumentException('Tarif BAR melampaui batas ceiling guardrail.');
        }
        if ($certificate->finalRateIdr % 1000 !== 0) {
            throw new InvalidArgumentException('Tarif BAR harus merupakan kelipatan Rp 1.000.');
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Certificates;

final class ForecastAccuracyCertificateVerifier
{
    public function verify(ForecastAccuracyCertificate $cert): bool
    {
        if ($cert->itemCode === '' || $cert->history === [] || $cert->forecastQty < 0 || $cert->mapeBps < 0 || $cert->madUnits < 0) {
            return false;
        }

        $expected = hash('sha256', implode('|', [
            $cert->itemCode,
            $cert->model,
            implode(',', $cert->history),
            (string) $cert->forecastQty,
            (string) $cert->mapeBps,
            (string) $cert->madUnits,
            (string) $cert->trackingSignalBps,
        ]));

        return hash_equals($expected, $cert->digest);
    }
}

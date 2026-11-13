<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Certificates;

final class OrderPromiseCertificateVerifier
{
    public function verify(OrderPromiseCertificate $cert): bool
    {
        if ($cert->atpConfirmedQty < 0 || $cert->ctpManufacturingQty < 0) {
            return false;
        }

        if ($cert->atpConfirmedQty > $cert->availableAtpQty || $cert->ctpManufacturingQty > $cert->availableCtpQty) {
            return false;
        }

        if (($cert->atpConfirmedQty + $cert->ctpManufacturingQty) > $cert->requestedQty) {
            return false;
        }

        $expected = hash('sha256', implode('|', [
            $cert->itemCode,
            (string) $cert->availableAtpQty,
            (string) $cert->availableCtpQty,
            (string) $cert->requestedQty,
            (string) $cert->atpConfirmedQty,
            (string) $cert->ctpManufacturingQty,
            $cert->promiseStatus,
        ]));

        return hash_equals($expected, $cert->digest);
    }
}

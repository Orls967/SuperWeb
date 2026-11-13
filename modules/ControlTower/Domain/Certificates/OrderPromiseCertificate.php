<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Certificates;

final readonly class OrderPromiseCertificate
{
    public function __construct(
        public string $itemCode,
        public int $availableAtpQty,
        public int $availableCtpQty,
        public int $requestedQty,
        public int $atpConfirmedQty,
        public int $ctpManufacturingQty,
        public string $promiseStatus,
        public string $digest,
    ) {}
}

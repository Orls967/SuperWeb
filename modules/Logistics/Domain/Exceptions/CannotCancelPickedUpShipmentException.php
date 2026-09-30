<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use DomainException;
use Modules\Logistics\Domain\Enums\ShipmentStatus;

class CannotCancelPickedUpShipmentException extends DomainException
{
    public static function forStatus(ShipmentStatus $status, string $trackingNumber): self
    {
        return new self("Pengiriman #{$trackingNumber} berstatus '{$status->label()}' dan tidak dapat dibatalkan. Hanya pengiriman sebelum di-pickup yang dapat dibatalkan; gunakan prosedur Return to Sender untuk pengembalian.");
    }
}

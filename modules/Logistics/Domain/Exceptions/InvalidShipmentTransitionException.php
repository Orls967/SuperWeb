<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use DomainException;
use Modules\Logistics\Domain\Enums\ShipmentStatus;

class InvalidShipmentTransitionException extends DomainException
{
    public static function fromStatus(ShipmentStatus $from, ShipmentStatus $to): self
    {
        return new self("Tidak dapat mengubah status shipment dari '{$from->value}' ({$from->label()}) ke '{$to->value}' ({$to->label()}).");
    }
}

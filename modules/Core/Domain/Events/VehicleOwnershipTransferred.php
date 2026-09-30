<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Domain\Models\Vehicle;

class VehicleOwnershipTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public int $fromUserId,
        public int $toUserId,
    ) {}
}

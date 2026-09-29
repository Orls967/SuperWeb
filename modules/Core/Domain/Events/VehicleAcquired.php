<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Domain\Models\Vehicle;

class VehicleAcquired implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
        public ?int $actorId = null,
        public string $method = 'manual',
    ) {}
}

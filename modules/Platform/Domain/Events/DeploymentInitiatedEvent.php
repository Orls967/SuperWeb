<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Modules\Platform\Domain\Models\ChangeDeployment;

final class DeploymentInitiatedEvent
{
    public function __construct(
        public readonly ChangeDeployment $deployment
    ) {}
}

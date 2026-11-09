<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Modules\Platform\Domain\Models\ReleaseTrain;

final class ReleaseTrainFrozenEvent
{
    public function __construct(
        public readonly ReleaseTrain $train
    ) {}
}

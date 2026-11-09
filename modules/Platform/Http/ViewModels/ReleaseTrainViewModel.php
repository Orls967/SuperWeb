<?php

declare(strict_types=1);

namespace Modules\Platform\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Platform\Domain\Models\ReleaseTrain;

final class ReleaseTrainViewModel
{
    /**
     * @param  Collection<int, ReleaseTrain>  $trains
     */
    public function __construct(
        public readonly Collection $trains
    ) {}

    public function activeTrain(): ?ReleaseTrain
    {
        return $this->trains->firstWhere('status', 'deploying') ?? $this->trains->firstWhere('status', 'active');
    }

    public function totalTrains(): int
    {
        return $this->trains->count();
    }
}

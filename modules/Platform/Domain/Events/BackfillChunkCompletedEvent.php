<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Modules\Platform\Domain\Models\DataBackfillChunk;

final class BackfillChunkCompletedEvent
{
    public function __construct(
        public readonly DataBackfillChunk $chunk,
        public readonly int $processedRecords
    ) {}
}

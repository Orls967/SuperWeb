<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Venue\Domain\Models\DynamicBundlePackage;

final class DynamicBundlePackageCreatedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly DynamicBundlePackage $package,
        public readonly int $totalBasePriceIdr,
        public readonly int $bundlePriceIdr,
        public readonly int $discountBps,
        public readonly \DateTimeImmutable $occurredAt,
    ) {}
}

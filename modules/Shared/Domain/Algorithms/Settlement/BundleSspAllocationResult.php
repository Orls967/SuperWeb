<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Settlement;

final readonly class BundleSspAllocationResult
{
    /**
     * @param  array<int, array{name: string, vendor_id: string, ssp_idr: int, allocated_amount_idr: int, platform_fee_idr: int, vendor_net_idr: int}>  $items
     */
    public function __construct(
        public int $bundlePriceIdr,
        public int $totalSspIdr,
        public array $items,
        public BundleSspCertificate $certificate,
    ) {}
}

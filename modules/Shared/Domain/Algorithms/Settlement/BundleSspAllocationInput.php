<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Settlement;

final readonly class BundleSspAllocationInput
{
    /**
     * @param  int  $bundlePriceIdr  Harga total paket dalam Rupiah
     * @param  int  $platformFeeBps  Basis poin potongan platform (misal 500 = 5%)
     * @param  array<int, array{name: string, ssp_idr: int, vendor_id: string}>  $components
     */
    public function __construct(
        public int $bundlePriceIdr,
        public int $platformFeeBps,
        public array $components,
    ) {}
}

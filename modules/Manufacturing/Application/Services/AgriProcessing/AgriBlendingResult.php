<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Services\AgriProcessing;

use Modules\Shared\Domain\Algorithms\Lp\LpStatus;

class AgriBlendingResult
{
    /**
     * @param  array<string, int>  $allocations  lot_id => kg
     * @param  array<string, mixed>|null  $farkasCertificate
     */
    public function __construct(
        public readonly LpStatus $status,
        public readonly array $allocations,
        public readonly int $totalCostIdr,
        public readonly bool $isCertificateValid,
        public readonly ?array $farkasCertificate = null,
    ) {}
}

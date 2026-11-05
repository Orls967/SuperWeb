<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

final readonly class RepaymentWaterfallResult
{
    /**
     * @param  array{payload: array<string, mixed>, hash: string, created_at: string}  $certificate
     */
    public function __construct(
        public int $allocatedMargin,
        public int $allocatedPrincipal,
        public int $allocatedTazir,
        public int $excessDeposit,
        public int $unpaidMargin,
        public int $unpaidPrincipal,
        public int $unpaidTazir,
        public bool $isFullySettled,
        public array $certificate
    ) {}
}


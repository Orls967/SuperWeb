<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

final readonly class TawarruqFxResult
{
    /**
     * @param  array<string, mixed>  $auditMetadata
     */
    public function __construct(
        public string $orderNumber,
        public string $sourceCurrency,
        public int $sourcePrincipalCents,
        public string $targetCurrency,
        public int $targetReceivedCents,
        public int $spotRateBps,
        public int $tenorDays,
        public int $markupRateBps,
        public int $markupProfitCents,
        public int $brokerFeeCents,
        public int $totalDeferredPriceCents,
        public int $effectiveRateBps,
        public string $commodityLotSerial,
        public string $exchangeName,
        public array $auditMetadata,
        public string $evaluatedAt,
        public string $certificateHash,
    ) {}
}


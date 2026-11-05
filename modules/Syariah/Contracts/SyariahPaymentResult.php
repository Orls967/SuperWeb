<?php

declare(strict_types=1);

namespace Modules\Syariah\Contracts;

final readonly class SyariahPaymentResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $success,
        public string $transactionRef,
        public string $orderReference,
        public string $customerAccountRef,
        public string $merchantCode,
        public string $businessLineCode,
        public int $grossAmountMinor,
        public int $adminFeeMinor,
        public int $netMerchantAmountMinor,
        public bool $isHalalVerified,
        public ?string $ledgerJournalRef = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}
}

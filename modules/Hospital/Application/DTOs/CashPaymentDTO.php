<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class CashPaymentDTO
{
    public function __construct(
        public int $invoiceId,
        public int $cashReceivedIdr,
        public string $idempotencyKey,
        public int $cashierUserId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Modules\Intercompany\Contracts;

interface IntercompanyBillingPort
{
    /**
     * Bill intercompany service deterministically with mirrored transaction pair.
     *
     * @param array{
     *     seller_entity: string,
     *     buyer_entity: string,
     *     amount_minor: int,
     *     description: string,
     *     idempotency_key: string,
     *     invoice_ref?: string,
     *     seller_ar_account?: string,
     *     seller_revenue_account?: string,
     *     buyer_expense_account?: string,
     *     buyer_ap_account?: string,
     * } $payload
     * @return array<string, mixed>
     */
    public function bill(array $payload): array;
}

<?php

declare(strict_types=1);

namespace Modules\Intercompany\Contracts;

interface IntercompanyPortInterface
{
    /**
     * Create a bidirectional mirrored intercompany transaction pair deterministically.
     *
     * @param array{
     *     selling_entity: string,
     *     buying_entity: string,
     *     amount_idr: int,
     *     description: string,
     *     idempotency_key: string,
     *     currency?: string,
     *     sales_invoice_ref?: string,
     *     purchase_bill_ref?: string,
     *     post_to_ledger?: bool,
     *     producer_ar_account?: string,
     *     producer_rev_account?: string,
     *     offtaker_exp_account?: string,
     *     offtaker_ap_account?: string,
     *     metadata?: array<string, mixed>
     * } $params
     * @return array{
     *     mirrored_pair_id: string,
     *     transaction_code: string,
     *     producer_tx_code: string,
     *     offtaker_tx_code: string,
     *     selling_entity: string,
     *     buying_entity: string,
     *     amount_idr: int,
     *     status: string
     * }
     */
    public function createMirroredPair(array $params): array;
}

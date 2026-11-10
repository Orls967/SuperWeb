<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Psr\Clock\ClockInterface;

class CreateMirroredIntercompanyTransactionAction
{
    public function __construct(
        protected readonly IntercompanyLedgerPoster $ledgerPoster,
        protected readonly ?ClockInterface $clock = null
    ) {}

    /**
     * @param array{
     *     selling_entity: string,
     *     buying_entity: string,
     *     description: string,
     *     amount_idr: int,
     *     currency?: string,
     *     sales_invoice_ref?: string,
     *     purchase_bill_ref?: string,
     *     transaction_code?: string,
     *     tp_rule_id?: int|null,
     *     created_by?: int|null,
     *     post_to_ledger?: bool
     * } $data
     */
    public function execute(array $data): IntercompanyTransaction
    {
        $selling = $data['selling_entity'];
        $buying = $data['buying_entity'];
        $amountIdr = (int) $data['amount_idr'];
        $currency = strtoupper($data['currency'] ?? 'IDR');

        if ($selling === $buying) {
            throw new InvalidArgumentException('Transaksi intercompany tidak boleh antara entitas yang sama: Selling entity and buying entity cannot be the same entity.');
        }

        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Intercompany transaction amount must be greater than zero.');
        }

        $now = $this->clock !== null ? $this->clock->now() : new \DateTimeImmutable;
        $dateStr = $now->format('Ymd');
        $hashSeed = hash('sha256', "{$selling}:{$buying}:{$amountIdr}:{$currency}:".$now->format('U'));

        $code = $data['transaction_code'] ?? 'IC-'.$dateStr.'-'.strtoupper(substr($hashSeed, 0, 8));
        $salesRef = $data['sales_invoice_ref'] ?? 'INV-IC-'.strtoupper(substr($hashSeed, 8, 6));
        $billRef = $data['purchase_bill_ref'] ?? 'BILL-IC-'.strtoupper(substr($hashSeed, 14, 6));

        $sellerEntity = IntercompanyEntity::where('entity_code', $selling)->first();
        $buyerEntity = IntercompanyEntity::where('entity_code', $buying)->first();

        $tx = IntercompanyTransaction::create([
            'transaction_code' => $code,
            'selling_entity' => $selling,
            'buying_entity' => $buying,
            'selling_entity_id' => $sellerEntity?->id,
            'buying_entity_id' => $buyerEntity?->id,
            'description' => $data['description'],
            'currency' => $currency,
            'amount_idr' => $amountIdr,
            'amount_minor' => $amountIdr,
            'amount_functional_idr' => $amountIdr,
            'sales_invoice_ref' => $salesRef,
            'purchase_bill_ref' => $billRef,
            'tp_rule_id' => $data['tp_rule_id'] ?? null,
            'status' => $data['status'] ?? 'matched',
            'created_by' => $data['created_by'] ?? null,
        ]);

        $postToLedger = $data['post_to_ledger'] ?? true;
        if ($postToLedger) {
            $ledgerResult = $this->ledgerPoster->postMirroredTransaction(
                sellingEntity: $selling,
                buyingEntity: $buying,
                amountMinor: $amountIdr,
                currency: $currency,
                description: $data['description'],
                idempotencyKey: "IC:TX:{$code}",
                referenceNo: $code,
                userId: $data['created_by'] ?? null
            );

            $tx->ledger_transaction_id = $ledgerResult['seller_tx']->id;
            $tx->save();
        }

        return $tx;
    }
}

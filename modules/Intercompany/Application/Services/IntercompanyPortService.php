<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Exceptions\IdempotencyConflictException;
use Modules\Intercompany\Contracts\IntercompanyBillingPort;
use Modules\Intercompany\Contracts\IntercompanyPortInterface;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;

class IntercompanyPortService implements IntercompanyBillingPort, IntercompanyPortInterface
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * {@inheritdoc}
     */
    public function bill(array $payload): array
    {
        $seller = $payload['seller_entity'] ?? '';
        $buyer = $payload['buyer_entity'] ?? '';

        return $this->createMirroredPair([
            'selling_entity' => $seller,
            'buying_entity' => $buyer,
            'amount_idr' => (int) ($payload['amount_minor'] ?? 0),
            'description' => $payload['description'] ?? 'Intercompany billing',
            'idempotency_key' => $payload['idempotency_key'] ?? '',
            'sales_invoice_ref' => $payload['invoice_ref'] ?? null,
            'producer_ar_account' => $payload['seller_ar_account'] ?? 'tlx:intercompany_receivable:IDR',
            'producer_rev_account' => $payload['seller_revenue_account'] ?? 'tlx:iot_service_revenue:IDR',
            'offtaker_exp_account' => $payload['buyer_expense_account'] ?? 'tlx:intercompany_telecom_expense:IDR',
            'offtaker_ap_account' => $payload['buyer_ap_account'] ?? 'tlx:intercompany_payable:IDR',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function createMirroredPair(array $params): array
    {
        $sellingEntity = $params['selling_entity'] ?? '';
        $buyingEntity = $params['buying_entity'] ?? '';
        $amountIdr = (int) ($params['amount_idr'] ?? 0);
        $description = $params['description'] ?? 'Intercompany transaction';
        $idempotencyKey = $params['idempotency_key'] ?? '';
        $currency = strtoupper($params['currency'] ?? 'IDR');

        if ($sellingEntity === '' || $buyingEntity === '') {
            throw new InvalidArgumentException('Selling entity and buying entity must be specified.');
        }

        if ($sellingEntity === $buyingEntity) {
            throw new InvalidArgumentException('Selling entity and buying entity cannot be the same entity.');
        }

        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Intercompany transaction amount must be greater than zero.');
        }

        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Idempotency key is required for intercompany transaction.');
        }

        $hash = hash('sha256', $idempotencyKey);
        $baseCode = 'IC-'.strtoupper(substr($hash, 0, 16));
        $producerTxCode = 'IC-PROD-'.strtoupper(substr($hash, 16, 12));
        $offtakerTxCode = 'IC-OFFT-'.strtoupper(substr($hash, 28, 12));

        // Check existing record by deterministic transaction_code
        $existing = IntercompanyTransaction::where('transaction_code', $baseCode)->first();
        if ($existing) {
            if ((int) $existing->amount_idr !== $amountIdr) {
                throw new IdempotencyConflictException(
                    "Idempotency conflict for key [{$idempotencyKey}]: expected amount {$existing->amount_idr}, got {$amountIdr}."
                );
            }

            return [
                'mirrored_pair_id' => (string) $existing->id,
                'transaction_code' => $existing->transaction_code,
                'producer_tx_code' => $producerTxCode,
                'offtaker_tx_code' => $offtakerTxCode,
                'selling_entity' => $existing->selling_entity,
                'buying_entity' => $existing->buying_entity,
                'amount_idr' => (int) $existing->amount_idr,
                'status' => strtoupper($existing->status),
            ];
        }

        $salesRef = $params['sales_invoice_ref'] ?? 'INV-'.$baseCode;
        $billRef = $params['purchase_bill_ref'] ?? 'BILL-'.$baseCode;

        $icTx = IntercompanyTransaction::create([
            'transaction_code' => $baseCode,
            'selling_entity' => $sellingEntity,
            'buying_entity' => $buyingEntity,
            'description' => $description,
            'currency' => $currency,
            'amount_idr' => $amountIdr,
            'sales_invoice_ref' => $salesRef,
            'purchase_bill_ref' => $billRef,
            'status' => 'settled',
        ]);

        $postToLedger = $params['post_to_ledger'] ?? true;
        if ($postToLedger) {
            $prodAr = $params['producer_ar_account'] ?? 'ar:ic:producer:egy:IDR';
            $prodRev = $params['producer_rev_account'] ?? 'revenue:energy:ppa:IDR';
            $offtExp = $params['offtaker_exp_account'] ?? 'expense:energy:purchase:IDR';
            $offtAp = $params['offtaker_ap_account'] ?? 'ap:ic:egy:producer:IDR';

            $refType = $params['reference_type'] ?? 'IC_TRANSACTION';
            $refId = $params['reference_id'] ?? $baseCode;

            // 1. Producer book: AR (debit = -), Revenue (credit = +)
            $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_PPA_NET_METER_SETTLE',
                description: "IC Producer: {$description}",
                idempotencyKey: $producerTxCode,
                entries: Posting::lines()
                    ->debit($prodAr, $amountIdr)
                    ->credit($prodRev, $amountIdr)
                    ->toEntries($currency),
                referenceType: $refType,
                referenceId: $refId,
            ));

            // 2. Offtaker book: Expense (debit = -), AP (credit = +)
            $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_PPA_NET_METER_SETTLE',
                description: "IC Offtaker: {$description}",
                idempotencyKey: $offtakerTxCode,
                entries: Posting::lines()
                    ->debit($offtExp, $amountIdr)
                    ->credit($offtAp, $amountIdr)
                    ->toEntries($currency),
                referenceType: $refType,
                referenceId: $refId,
            ));
        }

        return [
            'mirrored_pair_id' => (string) $icTx->id,
            'transaction_code' => $icTx->transaction_code,
            'producer_tx_code' => $producerTxCode,
            'offtaker_tx_code' => $offtakerTxCode,
            'selling_entity' => $icTx->selling_entity,
            'buying_entity' => $icTx->buying_entity,
            'amount_idr' => (int) $icTx->amount_idr,
            'status' => strtoupper($icTx->status),
        ];
    }
}

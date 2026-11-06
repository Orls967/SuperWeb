<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Egy\Domain\StateMachines\UtilityInvoiceStateMachine;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Application\BaseAction;

class ProcessInvoicePaymentAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array{
     *     invoice_id: string,
     *     payment_amount_idr: int,
     *     idempotency_key?: string
     * }  $params
     */
    public function execute(array $params): UtilityConsolidatedInvoice
    {
        $invoiceId = (string) $params['invoice_id'];
        $amount = (int) $params['payment_amount_idr'];
        $idempotencyKey = $params['idempotency_key'] ?? "PAY-INV-{$invoiceId}-{$amount}";

        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $invoice = UtilityConsolidatedInvoice::findOrFail($invoiceId);
        $customerParty = (string) ($invoice->customer_party_id ?? 'CUST-DEFAULT');

        return DB::transaction(function () use ($invoice, $amount, $customerParty, $idempotencyKey) {
            // Ensure clearing account exists
            LedgerAccount::firstOrCreate(
                ['code' => 'clearing:external:IDR'],
                [
                    'name' => 'External Clearing Account',
                    'kind' => AccountKind::ASSET,
                    'asset_code' => 'IDR',
                    'allow_negative' => true,
                ]
            );

            $customerAr = EgyAccounts::getOrCreateCustomerAr($customerParty);

            // Double entry: Debit clearing:external:IDR / Credit ar:egy:customer:{party}:IDR
            $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_BILL_PAYMENT->value,
                description: "Payment for utility invoice {$invoice->invoice_number}",
                idempotencyKey: "POST-{$idempotencyKey}",
                entries: Posting::lines()
                    ->debit('clearing:external:IDR', $amount)
                    ->credit($customerAr->code, $amount)
                    ->toEntries('IDR'),
                referenceType: 'UTILITY_INVOICE_PAYMENT',
                referenceId: (string) $invoice->id
            ));

            $newStatus = ($amount >= $invoice->total_amount_idr)
                ? UtilityInvoiceStateMachine::STATUS_PAID
                : UtilityInvoiceStateMachine::STATUS_PARTIALLY_PAID;

            UtilityInvoiceStateMachine::transition(
                invoice: $invoice,
                newStatus: $newStatus,
                reason: "Payment processed: Rp {$amount}",
                triggeredBy: 'payment_gateway'
            );

            return $invoice;
        });
    }
}

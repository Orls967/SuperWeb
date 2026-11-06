<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Egy\Domain\Models\UtilityInvoiceLine;
use Modules\Egy\Domain\StateMachines\UtilityInvoiceStateMachine;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Application\BaseAction;

class AdjustUtilityInvoiceAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array{
     *     invoice_id: string,
     *     adjustment_amount_idr: int,
     *     reason: string,
     *     idempotency_key?: string
     * }  $params
     */
    public function execute(array $params): UtilityConsolidatedInvoice
    {
        $invoiceId = (string) $params['invoice_id'];
        $adjustmentAmount = (int) $params['adjustment_amount_idr'];
        $reason = (string) $params['reason'];
        $idempotencyKey = (string) ($params['idempotency_key'] ?? "ADJ-INV-{$invoiceId}-{$adjustmentAmount}");

        $invoice = UtilityConsolidatedInvoice::findOrFail($invoiceId);
        $customerParty = (string) ($invoice->customer_party_id ?? 'CUST-DEFAULT');

        return DB::transaction(function () use ($invoice, $adjustmentAmount, $reason, $customerParty, $idempotencyKey) {
            // Append reversal/adjustment line
            UtilityInvoiceLine::create([
                'invoice_id' => $invoice->id,
                'window_type' => 'REVERSAL_ADJUSTMENT',
                'wh_consumed' => 0,
                'rate_per_kwh_minor' => 0,
                'line_total_minor' => -$adjustmentAmount,
                'subtotal_minor' => -$adjustmentAmount,
            ]);

            $customerAr = EgyAccounts::getOrCreateCustomerAr($customerParty);

            // Reversal ledger posting: Credit AR / Debit Revenue
            $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: "Adjustment reversal for invoice {$invoice->invoice_number}: {$reason}",
                idempotencyKey: "POST-{$idempotencyKey}",
                entries: Posting::lines()
                    ->debit('egy:grid_electricity_revenue:IDR', $adjustmentAmount)
                    ->credit($customerAr->code, $adjustmentAmount)
                    ->toEntries('IDR'),
                referenceType: 'UTILITY_INVOICE_ADJUSTMENT',
                referenceId: (string) $invoice->id
            ));

            $newTotal = max(0, $invoice->total_amount_idr - $adjustmentAmount);
            $invoice->total_amount_idr = $newTotal;
            $invoice->total_consolidated_minor = $newTotal;
            $invoice->save();

            UtilityInvoiceStateMachine::transition(
                invoice: $invoice,
                newStatus: UtilityInvoiceStateMachine::STATUS_ADJUSTED,
                reason: $reason,
                triggeredBy: 'billing_officer'
            );

            return $invoice;
        });
    }
}

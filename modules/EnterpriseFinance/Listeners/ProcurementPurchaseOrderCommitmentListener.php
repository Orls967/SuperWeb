<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Listeners;

use Modules\EnterpriseFinance\Contracts\BudgetCommitmentPort;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetCommitmentRequest;

class ProcurementPurchaseOrderCommitmentListener
{
    public function __construct(
        protected readonly BudgetCommitmentPort $commitmentPort
    ) {}

    /**
     * @param array<string, mixed>|object $event
     */
    public function handle(array|object $event): void
    {
        $payload = is_array($event) ? $event : (array) $event;

        $budgetId = (int) ($payload['budget_id'] ?? 0);
        $poNumber = (string) ($payload['po_number'] ?? $payload['order_id'] ?? '');
        $amountMinor = (int) ($payload['amount_minor'] ?? $payload['total_amount_minor'] ?? 0);

        if ($budgetId > 0 && ! empty($poNumber) && $amountMinor > 0) {
            $this->commitmentPort->reserve(new BudgetCommitmentRequest(
                budgetId: $budgetId,
                commitmentCode: "PO-ENC-{$poNumber}",
                sourceModule: 'Procurement',
                referenceNumber: $poNumber,
                amountMinor: $amountMinor,
                reason: 'Otomatisasi komitmen pengadaan barang',
                payload: $payload
            ));
        }
    }
}


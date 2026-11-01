<?php

declare(strict_types=1);

namespace Modules\Fleet\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Fleet\Domain\Models\FleetInvoice;
use Modules\Fleet\Ledger\FleetAccounts;
use Modules\Fleet\Ledger\FleetTransactionTypes;

class BillMonthlyFleetLeaseAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function execute(FleetContract $contract, string $period): FleetInvoice
    {
        $idempotencyKey = "oto:fleet:bill:{$contract->contract_number}:{$period}";

        $existing = FleetInvoice::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $arAccount = FleetAccounts::ensurePartyAr((int) $contract->party_id);
        $amount = (int) $contract->monthly_rental_idr;

        // Debit AR (-amount), Kredit Lease Revenue (+amount)
        $lines = Posting::lines()
            ->debit($arAccount, $amount)
            ->credit(FleetAccounts::LEASE_REVENUE, $amount)
            ->toEntries('IDR');

        $this->ledger->post(new PostingDTO(
            type: FleetTransactionTypes::FLEET_BILL->value,
            description: "Tagihan Sewa Armada {$contract->contract_number} Periode {$period}",
            idempotencyKey: $idempotencyKey,
            entries: $lines,
            referenceType: 'FleetContract',
            referenceId: (string) $contract->id
        ));

        return FleetInvoice::create([
            'invoice_number' => "INV-FLT-{$contract->contract_number}-{$period}",
            'contract_id' => $contract->id,
            'billing_period' => $period,
            'rental_fee_idr' => $amount,
            'penalty_credit_idr' => 0,
            'excess_mileage_fee_idr' => 0,
            'total_billed_idr' => $amount,
            'idempotency_key' => $idempotencyKey,
            'status' => 'ISSUED',
        ]);
    }
}

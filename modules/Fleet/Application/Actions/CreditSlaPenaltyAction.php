<?php

declare(strict_types=1);

namespace Modules\Fleet\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Fleet\Domain\Models\FleetSlaBreach;
use Modules\Fleet\Ledger\FleetAccounts;
use Modules\Fleet\Ledger\FleetTransactionTypes;

class CreditSlaPenaltyAction
{
    public function __construct(
        private readonly Ledger $ledger
    ) {}

    public function execute(FleetSlaBreach $breach): FleetSlaBreach
    {
        if ($breach->status === 'CREDITED') {
            return $breach;
        }

        $contract = $breach->contract;
        $arAccount = FleetAccounts::ensurePartyAr((int) $contract->party_id);
        $amount = (int) $breach->penalty_amount_idr;
        $idempotencyKey = "oto:fleet:sla:{$breach->breach_uuid}";

        // Debit Expense (-amount), Kredit AR (+amount) untuk mengurangi tagihan penyewa
        $lines = Posting::lines()
            ->debit(FleetAccounts::SLA_CREDIT_EXPENSE, $amount)
            ->credit($arAccount, $amount)
            ->toEntries('IDR');

        $this->ledger->post(new PostingDTO(
            type: FleetTransactionTypes::FLEET_SLA_CREDIT->value,
            description: "Kredit Penalti SLA Armada {$breach->breach_uuid}",
            idempotencyKey: $idempotencyKey,
            entries: $lines,
            referenceType: 'FleetSlaBreach',
            referenceId: (string) $breach->id
        ));

        $breach->update(['status' => 'CREDITED']);

        return $breach->fresh();
    }
}

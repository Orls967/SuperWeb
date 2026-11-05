<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Enums\AccountCurrentStatus;
use Modules\Insurance\Domain\Models\ReinsuranceAccountCurrent;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class SettleAccountCurrentAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(ReinsuranceAccountCurrent $accountCurrent, string $idempotencyKey, ?int $actorId = null): ReinsuranceAccountCurrent
    {
        if (trim($idempotencyKey) === '') {
            throw new DomainException('Idempotency key wajib diisi untuk settlement.');
        }

        $accountCurrent->status->assertCanTransitionTo(AccountCurrentStatus::Settled);

        return DB::transaction(function () use ($accountCurrent, $idempotencyKey) {
            $partyId = (string) $accountCurrent->reinsurer_party_id;
            $payableAccount = InsuranceAccounts::reinsurerPayable($partyId);
            $recoverableAccount = InsuranceAccounts::reinsurerRecoverable($partyId);
            $bankClearing = InsuranceAccounts::BANK_CLEARING;

            // Saldo kewajiban neto:
            // Net payable to reinsurer = (premium_ceded + reinstatement) - commission
            $netPayable = ($accountCurrent->premium_ceded_idr + $accountCurrent->reinstatement_idr) - $accountCurrent->commission_idr;
            $recoverable = $accountCurrent->claims_recoverable_idr;
            $netDue = $accountCurrent->net_due_idr; // netPayable - recoverable

            $lines = Posting::lines();

            if ($netPayable > 0) {
                $lines->debit($payableAccount, $netPayable);
            } elseif ($netPayable < 0) {
                $lines->credit($payableAccount, abs($netPayable));
            }

            if ($recoverable > 0) {
                $lines->credit($recoverableAccount, $recoverable);
            } elseif ($recoverable < 0) {
                $lines->debit($recoverableAccount, abs($recoverable));
            }

            if ($netDue > 0) {
                // Insurer pays net cash to reinsurer
                $lines->credit($bankClearing, $netDue);
            } elseif ($netDue < 0) {
                // Insurer receives net cash from reinsurer
                $lines->debit($bankClearing, abs($netDue));
            }

            $tx = $this->ledger->post(new PostingDTO(
                type: InsuranceTransactionTypes::REINSURANCE_SETTLEMENT->value,
                description: "Settlement Account Current {$accountCurrent->number} Reinsurer {$partyId} Periode {$accountCurrent->period_ym}",
                idempotencyKey: "ins:rein:settle:{$accountCurrent->number}:{$idempotencyKey}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'ins_rein_account_current',
                referenceId: (string) $accountCurrent->id,
            ));

            $accountCurrent->forceFill([
                'status' => AccountCurrentStatus::Settled,
                'settlement_ledger_tx' => (string) $tx->id,
                'settled_at' => $this->clock->now(),
            ])->save();

            return $accountCurrent;
        });
    }
}


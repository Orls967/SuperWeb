<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Models\MarketCommission;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class SettleBrokerCommissionAction
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ?Ledger $ledger = null,
    ) {}

    public function execute(int $commissionId): MarketCommission
    {
        return DB::transaction(function () use ($commissionId) {
            $comm = MarketCommission::query()->lockForUpdate()->find($commissionId);
            if ($comm === null) {
                throw new DomainException("Komisi broker ID {$commissionId} tidak ditemukan.");
            }

            if ($comm->status === 'settled') {
                return $comm;
            }

            $now = CarbonImmutable::instance($this->clock->now());

            $txId = null;
            if ($this->ledger !== null) {
                $lines = Posting::lines();
                $lines->debit('ins:claim_clearing:IDR', $comm->commission_amount_idr);
                $lines->credit('ins:reserve_pool:IDR', $comm->commission_amount_idr);

                $tx = $this->ledger->post(new PostingDTO(
                    type: InsuranceTransactionTypes::INSURANCE_REIN_SETTLEMENT->value,
                    description: "Penyelesaian komisi broker #{$comm->broker_id} untuk polis #{$comm->embedded_policy_id}",
                    idempotencyKey: 'broker:comm:settle:'.$comm->id,
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'ins_broker_commission',
                    referenceId: (string) $comm->id,
                ));
                $txId = (int) $tx->id;
            }

            $comm->update([
                'status' => 'settled',
                'ledger_transaction_id' => $txId,
                'settled_at' => $now,
            ]);

            return $comm;
        });
    }
}

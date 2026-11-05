<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

final class SurrenderLifePolicyAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Memproses penebusan nilai tunai polis (Cash Surrender Value) dan unit link holding.
     */
    public function execute(
        string $policyNumber,
        string $reason,
        string $actorId,
        string $idempotencyKey,
    ): array {
        $policy = LifePolicy::with('unitHoldings.fund')->where('policy_number', $policyNumber)->firstOrFail();
        if ($policy->status !== 'active') {
            throw new InvalidArgumentException("Polis {$policyNumber} tidak aktif (status: {$policy->status}).");
        }

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:life:surrender:'.$policyNumber,
            payload: [
                'policy_number' => $policyNumber,
                'reason' => $reason,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($policy, $policyNumber) {
                return DB::transaction(function () use ($policy, $policyNumber) {
                    $savingSurrender = (int) intdiv($policy->saving_accumulated_idr * 90, 100); // 90% porsi tabungan

                    $unitLinkCashValue = 0;
                    foreach ($policy->unitHoldings as $holding) {
                        $fund = $holding->fund;
                        $fundNav = $fund->current_nav_per_unit_idr;
                        $val = (int) intdiv($holding->units_held * $fundNav, 1000);
                        $unitLinkCashValue += $val;

                        $fund->decrement('total_units', $holding->units_held);
                        $fund->decrement('total_aum_idr', $val);
                        $holding->update(['units_held' => 0, 'current_valuation_idr' => 0]);
                    }

                    $totalCashPayout = $savingSurrender + $unitLinkCashValue;

                    // Posting pembayaran surrender ke ledger
                    $lines = Posting::lines();
                    $lines->debit('ins:claim_expense:IDR', max(1, $totalCashPayout));
                    $lines->credit('ins:claim_clearing:IDR', max(1, $totalCashPayout));

                    $tx = $this->ledger->post(new PostingDTO(
                        type: InsuranceTransactionTypes::INSURANCE_CLAIM_PAYOUT->value,
                        description: "Surrender Value Payout for Policy {$policyNumber}",
                        idempotencyKey: 'surr:prem:'.$policyNumber,
                        entries: $lines->toEntries('IDR'),
                        referenceType: 'ins_lh_life_policy',
                        referenceId: $policyNumber,
                    ));

                    $policy->update([
                        'status' => 'surrendered',
                        'saving_accumulated_idr' => 0,
                    ]);

                    return [
                        'policy_number' => $policyNumber,
                        'total_cash_surrender_idr' => $totalCashPayout,
                        'saving_portion_idr' => $savingSurrender,
                        'unit_link_portion_idr' => $unitLinkCashValue,
                        'ledger_transaction_id' => $tx->id,
                    ];
                });
            }
        );
    }
}

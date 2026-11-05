<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Domain\DTOs\CessionPlan;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Exceptions\CessionException;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

/**
 * Menyimpan fakta cession (event + baris per reinsurer) dan memposting ledger dalam satu transaksi.
 * Idempoten di level state (event_key unik) maupun ledger (key = event_key).
 */
final class RecordCessionAction
{
    public function __construct(private readonly Ledger $ledger) {}

    public function execute(string $eventKey, CessionKind $kind, CessionPlan $plan, CarbonImmutable $at, ?int $policyId = null, ?int $claimId = null, ?int $treatyId = null): CessionEvent
    {
        return DB::transaction(function () use ($eventKey, $kind, $plan, $at, $policyId, $claimId, $treatyId): CessionEvent {
            $existing = CessionEvent::query()->where('event_key', $eventKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($plan->retainedIdr < 0 || $plan->cededIdr() + $plan->retainedIdr !== $plan->grossIdr) {
                throw new CessionException("Cession {$eventKey}: ceded + retensi harus sama dengan bruto.");
            }

            $transactionId = $this->post($eventKey, $kind, $plan);

            $event = CessionEvent::create([
                'event_key' => $eventKey,
                'kind' => $kind,
                'policy_id' => $policyId,
                'claim_id' => $claimId,
                'treaty_id' => $treatyId,
                'period_ym' => $at->format('Y-m'),
                'gross_idr' => $plan->grossIdr,
                'retained_idr' => $plan->retainedIdr,
                'ceded_idr' => $plan->cededIdr(),
                'commission_idr' => $plan->commissionIdr(),
                'reinstatement_idr' => $plan->reinstatementIdr(),
                'snapshot' => $plan->snapshot,
                'snapshot_hash' => $plan->snapshotHash(),
                'ledger_transaction_id' => $transactionId,
                'occurred_at' => $at,
            ]);

            foreach ($plan->lines as $line) {
                $event->lines()->create($line->toArray());
            }

            return $event;
        });
    }

    private function post(string $eventKey, CessionKind $kind, CessionPlan $plan): ?int
    {
        if ($plan->isEmpty()) {
            return null;
        }

        $byReinsurer = [];
        foreach ($plan->lines as $line) {
            $bucket = $byReinsurer[$line->reinsurerPartyId] ?? ['ceded' => 0, 'commission' => 0, 'reinstatement' => 0];
            $bucket['ceded'] += $line->cededIdr;
            $bucket['commission'] += $line->commissionIdr;
            $bucket['reinstatement'] += $line->reinstatementIdr;
            $byReinsurer[$line->reinsurerPartyId] = $bucket;
        }
        ksort($byReinsurer);

        $lines = Posting::lines();
        $type = match ($kind) {
            CessionKind::Premium => InsuranceTransactionTypes::INSURANCE_CEDE_PREMIUM,
            CessionKind::TreatyPremium => InsuranceTransactionTypes::INSURANCE_CEDE_TREATY_PREMIUM,
            CessionKind::Claim => InsuranceTransactionTypes::INSURANCE_CEDE_CLAIM,
            CessionKind::Commutation => throw new CessionException('Komutasi diposting oleh CommuteTreatyAction.'),
        };

        if ($kind === CessionKind::Claim) {
            foreach ($byReinsurer as $partyId => $b) {
                if ($b['ceded'] > 0) {
                    $lines->debit(InsuranceAccounts::reinsurerRecoverable($partyId), $b['ceded']);
                }
                if ($b['reinstatement'] > 0) {
                    $lines->credit(InsuranceAccounts::reinsurerPayable($partyId), $b['reinstatement']);
                }
            }
            if ($plan->cededIdr() > 0) {
                $lines->credit(InsuranceAccounts::CLAIM_EXPENSE, $plan->cededIdr());
            }
            if ($plan->reinstatementIdr() > 0) {
                $lines->debit(InsuranceAccounts::REINSTATEMENT_EXPENSE, $plan->reinstatementIdr());
            }
        } else {
            $lines->debit(InsuranceAccounts::CEDED_PREMIUM_EXPENSE, $plan->cededIdr());
            foreach ($byReinsurer as $partyId => $b) {
                $net = $b['ceded'] - $b['commission'];
                if ($net > 0) {
                    $lines->credit(InsuranceAccounts::reinsurerPayable($partyId), $net);
                }
            }
            if ($plan->commissionIdr() > 0) {
                $lines->credit(InsuranceAccounts::COMMISSION_INCOME, $plan->commissionIdr());
            }
        }

        $transaction = $this->ledger->post(new PostingDTO(
            type: $type->value,
            description: "Cession {$kind->value} {$eventKey}",
            idempotencyKey: $eventKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ins_cession_event',
            referenceId: $eventKey,
        ));

        return (int) $transaction->id;
    }
}

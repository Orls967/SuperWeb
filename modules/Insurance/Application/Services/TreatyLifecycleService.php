<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Actions\RecordCessionAction;
use Modules\Insurance\Domain\Enums\AccountCurrentStatus;
use Modules\Insurance\Domain\Enums\CessionKind;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Exceptions\TreatyException;
use Modules\Insurance\Domain\Models\CessionEvent;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Insurance\Ledger\InsuranceAccounts;
use Modules\Insurance\Ledger\InsuranceTransactionTypes;

/**
 * Siklus hidup treaty: transisi status + riwayat append-only, pembukuan premi deposit saat aktif,
 * dan komutasi (nilai sekarang recoverable yang belum diselesaikan, D157-1 / perluasan 157).
 */
final class TreatyLifecycleService
{
    public function __construct(
        private readonly CessionEngine $engine,
        private readonly RecordCessionAction $record,
        private readonly Ledger $ledger,
        private readonly ClockInterface $clock,
        private readonly ParameterRepository $parameters,
    ) {}

    /**
     * Menerapkan transisi (sudah dikunci & disetujui bila perlu). Harus dipanggil di dalam transaksi.
     */
    public function apply(ReinsuranceTreaty $treaty, TreatyStatus $to, ?string $reason, ?int $actorId, ?int $approvalRef = null): ReinsuranceTreaty
    {
        $from = $treaty->status;
        $from->assertCanTransitionTo($to);

        if ($to === TreatyStatus::Bound && abs(array_sum($treaty->panel->pluck('share_bps')->all()) - 10000) !== 0) {
            throw new TreatyException("Panel treaty {$treaty->treaty_code} harus berjumlah 100% sebelum bound.");
        }

        if ($to === TreatyStatus::Commuted) {
            $this->commute($treaty);
        }

        $treaty->forceFill(['status' => $to, 'pending_status' => null, 'approval_ref' => null])->save();
        $treaty->history()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reason' => $reason,
            'actor_user_id' => $actorId,
            'approval_ref' => $approvalRef,
            'occurred_at' => $this->clock->now(),
        ]);

        if ($to === TreatyStatus::Active) {
            $this->bookDepositPremium($treaty);
        }

        return $treaty;
    }

    private function bookDepositPremium(ReinsuranceTreaty $treaty): void
    {
        if ($treaty->type->isProportional() || $treaty->layer_premium_idr <= 0) {
            return;
        }

        $plan = $this->engine->planTreatyPremium($treaty);
        $this->record->execute(
            eventKey: "ins:cede:tpremium:{$treaty->id}",
            kind: CessionKind::TreatyPremium,
            plan: $plan,
            at: CarbonImmutable::instance($this->clock->now()),
            treatyId: $treaty->id,
        );

        $treaty->forceFill(['premium_booked_idr' => $plan->cededIdr()])->save();
    }

    /**
     * Komutasi: melunasi seluruh recoverable/utang treaty yang belum diselesaikan dengan nilai terdiskonto.
     * Diblokir bila masih ada account current yang sudah diterbitkan tetapi belum diselesaikan.
     */
    private function commute(ReinsuranceTreaty $treaty): void
    {
        $blocking = DB::table('ins_rein_account_currents as ac')
            ->join('ins_cession_lines as l', function ($join) use ($treaty): void {
                $join->on('l.reinsurer_party_id', '=', 'ac.reinsurer_party_id')->where('l.treaty_id', '=', $treaty->id);
            })
            ->join('ins_cession_events as e', function ($join): void {
                $join->on('e.id', '=', 'l.event_id')->on('e.period_ym', '=', 'ac.period_ym');
            })
            ->whereIn('ac.status', [AccountCurrentStatus::Issued->value, AccountCurrentStatus::Disputed->value])
            ->exists();

        if ($blocking) {
            throw new TreatyException("Treaty {$treaty->treaty_code} masih memiliki account current yang belum diselesaikan; selesaikan dulu sebelum komutasi.");
        }

        $discountBps = (int) $this->parameters->value('reins.commutation')['discount_bps'];
        $outstanding = $this->outstandingByReinsurer($treaty->id);

        $recoverableTotal = 0;
        $discountedTotal = 0;
        $lines = Posting::lines();
        $snapshot = [];

        foreach ($outstanding as $partyId => $row) {
            $discounted = intdiv($row['recoverable'] * (10000 - $discountBps) + 5000, 10000);
            $discountLoss = $row['recoverable'] - $discounted;
            $cash = $discounted - $row['payable'];

            if ($row['payable'] > 0) {
                $lines->debit(InsuranceAccounts::reinsurerPayable($partyId), $row['payable']);
            }
            if ($row['recoverable'] > 0) {
                $lines->credit(InsuranceAccounts::reinsurerRecoverable($partyId), $row['recoverable']);
            }
            if ($discountLoss > 0) {
                $lines->debit(InsuranceAccounts::COMMUTATION_LOSS, $discountLoss);
            }
            if ($cash > 0) {
                $lines->debit(InsuranceAccounts::BANK_CLEARING, $cash);
            } elseif ($cash < 0) {
                $lines->credit(InsuranceAccounts::BANK_CLEARING, -$cash);
            }

            $recoverableTotal += $row['recoverable'];
            $discountedTotal += $discounted;
            $snapshot[$partyId] = $row + ['discounted' => $discounted, 'cash' => $cash];
        }

        $transactionId = null;
        if ($outstanding !== []) {
            $transactionId = (int) $this->ledger->post(new PostingDTO(
                type: InsuranceTransactionTypes::INSURANCE_REIN_COMMUTATION->value,
                description: "Komutasi treaty {$treaty->treaty_code}",
                idempotencyKey: "ins:commute:{$treaty->id}",
                entries: $lines->toEntries('IDR'),
                referenceType: 'ins_rein_treaty',
                referenceId: (string) $treaty->id,
            ))->id;
        }

        CessionEvent::create([
            'event_key' => "ins:commute:{$treaty->id}",
            'kind' => CessionKind::Commutation,
            'treaty_id' => $treaty->id,
            'period_ym' => $this->clock->now()->format('Y-m'),
            'gross_idr' => $recoverableTotal,
            'retained_idr' => $recoverableTotal - $discountedTotal,
            'ceded_idr' => $discountedTotal,
            'snapshot' => ['discount_bps' => $discountBps, 'per_reinsurer' => $snapshot],
            'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'ledger_transaction_id' => $transactionId,
            'occurred_at' => $this->clock->now(),
        ]);
    }

    /**
     * Saldo recoverable & utang treaty per reinsurer pada periode yang belum diselesaikan lewat account current.
     *
     * @return array<string, array{recoverable: int, payable: int}>
     */
    public function outstandingByReinsurer(int $treatyId): array
    {
        $rows = DB::table('ins_cession_lines as l')
            ->join('ins_cession_events as e', 'e.id', '=', 'l.event_id')
            ->leftJoin('ins_rein_account_currents as ac', function ($join): void {
                $join->on('ac.reinsurer_party_id', '=', 'l.reinsurer_party_id')
                    ->on('ac.period_ym', '=', 'e.period_ym')
                    ->where('ac.status', '=', AccountCurrentStatus::Settled->value);
            })
            ->where('l.treaty_id', $treatyId)
            ->whereNull('ac.id')
            ->whereIn('e.kind', [CessionKind::Premium->value, CessionKind::TreatyPremium->value, CessionKind::Claim->value])
            ->groupBy('l.reinsurer_party_id')
            ->orderBy('l.reinsurer_party_id')
            ->selectRaw("l.reinsurer_party_id as party,
                SUM(CASE WHEN e.kind = 'claim' THEN l.ceded_idr ELSE 0 END) as recoverable,
                SUM(CASE WHEN e.kind != 'claim' THEN l.ceded_idr - l.commission_idr ELSE 0 END) + SUM(l.reinstatement_idr) as payable")
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->party] = ['recoverable' => (int) $row->recoverable, 'payable' => (int) $row->payable];
        }

        return $result;
    }

    public function isProportional(TreatyType $type): bool
    {
        return $type->isProportional();
    }
}

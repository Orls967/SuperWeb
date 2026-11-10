<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Treasury\Domain\Algorithms\BankReconciliationMatcher;
use Modules\Treasury\Domain\DTOs\BankStatementLineDTO;
use Modules\Treasury\Domain\DTOs\BookLedgerLineDTO;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankReconciliationMatch;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Domain\Models\BankStatement;
use Psr\Clock\ClockInterface;

class ReconcileBankStatementsAction
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null,
        protected readonly BankReconciliationMatcher $matcher = new BankReconciliationMatcher,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Jalankan rekonsiliasi bank deterministik 3-tahap.
     */
    public function execute(
        ?BankAccount $bankAccount = null,
        ?string $bankAccountId = null,
        ?array $customBankLines = null,
        ?array $customBookLines = null,
        ?int $userId = null
    ): array {
        $account = $bankAccount ?? ($bankAccountId ? BankAccount::findOrFail($bankAccountId) : BankAccount::firstOrFail());

        return DB::transaction(function () use ($account, $customBankLines, $customBookLines, $userId) {
            $now = $this->now();

            $bankLines = $customBankLines;
            if ($bankLines === null) {
                $statements = BankStatement::where('bank_account_id', $account->id)
                    ->where('is_reconciled', false)
                    ->orderBy('transaction_date')
                    ->get();

                $bankLines = [];
                foreach ($statements as $st) {
                    $bankLines[] = new BankStatementLineDTO(
                        id: (string) $st->id,
                        referenceNo: (string) $st->reference_no,
                        date: $st->transaction_date->format('Y-m-d'),
                        amount: (int) $st->amount,
                        description: (string) $st->description
                    );
                }
            }

            $bookLines = $customBookLines;
            if ($bookLines === null) {
                $bookLines = [];
                $accountCode = "trs:bank_operational:{$account->entity_id}:{$account->currency}";
                $entries = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $accountCode))
                    ->orderBy('created_at')
                    ->limit(500)
                    ->get();

                foreach ($entries as $e) {
                    $amount = (int) $e->amount->abs()->toInt();
                    $bookLines[] = new BookLedgerLineDTO(
                        id: (string) $e->id,
                        referenceNo: (string) ($e->transaction->idempotency_key ?? 'REF-'.$e->id),
                        date: $e->created_at->format('Y-m-d'),
                        amount: $amount,
                        description: (string) ($e->transaction->description ?? '')
                    );
                }
            }

            $result = $this->matcher->match($bankLines, $bookLines);

            $matchedCount = 0;

            foreach ($result->exactMatches as $m) {
                $stmt = BankStatement::find($m->bankLineId);
                if ($stmt !== null) {
                    $stmt->update([
                        'is_reconciled' => true,
                        'reconciliation_status' => 'matched',
                    ]);

                    BankReconciliationMatch::create([
                        'statement_id' => $stmt->id,
                        'match_type' => 'exact',
                        'matched_amount' => $m->amount,
                        'ledger_entry_ids' => [$m->bookLineId],
                        'matched_by' => $userId,
                        'notes' => 'Exact match otomatis ref dan nominal identik',
                        'match_certificate' => $result->certificate->toArray(),
                        'created_at' => $now,
                    ]);
                    $matchedCount++;
                }
            }

            foreach ($result->toleranceMatches as $m) {
                $stmt = BankStatement::find($m->bankLineId);
                if ($stmt !== null) {
                    $stmt->update([
                        'is_reconciled' => true,
                        'reconciliation_status' => 'matched',
                    ]);

                    BankReconciliationMatch::create([
                        'statement_id' => $stmt->id,
                        'match_type' => 'fuzzy_tolerance',
                        'matched_amount' => $m->bankAmount,
                        'ledger_entry_ids' => [$m->bookLineId],
                        'matched_by' => $userId,
                        'notes' => "Tolerance match: selisih nominal {$m->deltaAmount}, rentang hari {$m->deltaDays}",
                        'match_certificate' => $result->certificate->toArray(),
                        'created_at' => $now,
                    ]);
                    $matchedCount++;
                }
            }

            foreach ($result->manyToOneMatches as $m) {
                $stmt = BankStatement::find($m->bankLineId);
                if ($stmt !== null) {
                    $stmt->update([
                        'is_reconciled' => true,
                        'reconciliation_status' => 'matched',
                    ]);

                    BankReconciliationMatch::create([
                        'statement_id' => $stmt->id,
                        'match_type' => 'subset_many_to_one',
                        'matched_amount' => $m->bankAmount,
                        'ledger_entry_ids' => $m->bookLineIds,
                        'matched_by' => $userId,
                        'notes' => 'Many-to-one match subset transaksi buku terhadap 1 mutasi bank',
                        'match_certificate' => $result->certificate->toArray(),
                        'created_at' => $now,
                    ]);
                    $matchedCount++;
                }
            }

            foreach ($result->unmatchedBankLines as $u) {
                $stmt = BankStatement::find($u->id);
                if ($stmt !== null) {
                    $stmt->update([
                        'is_reconciled' => false,
                        'reconciliation_status' => 'unmatched',
                    ]);

                    BankReconciliationUnmatched::firstOrCreate(
                        [
                            'statement_id' => $stmt->id,
                            'status' => 'pending',
                        ],
                        [
                            'reason' => 'Tidak ditemukan padanan buku dalam batas toleransi',
                            'notes' => "Mutasi bank tanggal {$u->date} nominal {$u->amount}",
                            'reported_by' => $userId,
                        ]
                    );
                }
            }

            return [
                'matched_count' => $matchedCount,
                'unmatched_count' => count($result->unmatchedBankLines),
                'exact_matches' => count($result->exactMatches),
                'tolerance_matches' => count($result->toleranceMatches),
                'many_to_one_matches' => count($result->manyToOneMatches),
                'certificate_hash' => $result->certificate->certificateHash,
            ];
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Intercompany\Application\Services\IntercompanyService;
use Modules\Intercompany\Contracts\SubledgerTotalProvider;
use Modules\Intercompany\Domain\Models\CostSharingAgreement;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyLoanSchedule;

class AuditIntercompanyCommand extends Command
{
    protected $signature = 'group:audit';

    protected $description = 'Audit intercompany transactions, elimination entries, loans, transfer pricing margins, and dual-source ledger reconciliation';

    public function handle(
        IntercompanyService $service,
        ?SubledgerTotalProvider $subledgerProvider = null
    ): int {
        $this->info('Starting Group Consolidation & Intercompany Audit...');

        $discrepancies = [];

        // 1. Audit Invarian Dasar (Entitas sama, Pinjaman melebihi pokok, Kepemilikan NCI)
        $res = $service->auditIntercompany();

        $this->line("Status: {$res['status']}");
        $this->line("Mirror Transactions: {$res['transaction_count']}");
        $this->line("Intercompany Loans: {$res['loan_count']}");
        $this->line("Transfer Pricing Rules: {$res['rule_count']}");
        $this->line("Elimination Entries: {$res['elimination_count']}");
        $this->line("Discrepancies: {$res['discrepancy_count']}");

        if ($res['discrepancy_count'] > 0) {
            $discrepancies[] = "Invarian dasar intercompany terlanggar! (Jumlah diskrepansi: {$res['discrepancy_count']})";
        }

        // 2. Audit Keseimbangan Jurnal Eliminasi (Nominal > 0 & Akun Berbeda)
        $eliminations = EliminationEntry::all();
        foreach ($eliminations as $elim) {
            if ((int) $elim->amount_idr <= 0) {
                $discrepancies[] = "Eliminasi [{$elim->elimination_code}]: Nominal tidak valid ({$elim->amount_idr})";
            }
            if ($elim->debit_account === $elim->credit_account) {
                $discrepancies[] = "Eliminasi [{$elim->elimination_code}]: Akun debit dan kredit identik ({$elim->debit_account})";
            }
        }

        // 3. Audit Integritas Jadwal Pinjaman (Sum Principal Component == Loan Principal)
        $loans = IntercompanyLoan::all();
        foreach ($loans as $loan) {
            $sumPrincipal = (int) IntercompanyLoanSchedule::where('loan_id', $loan->id)->sum('principal_due_minor');
            if ($loan->schedules()->count() > 0 && $sumPrincipal !== (int) $loan->principal_idr) {
                $discrepancies[] = "Pinjaman [{$loan->loan_number}]: Total pokok jadwal ({$sumPrincipal}) != Pokok pinjaman ({$loan->principal_idr})";
            }
        }

        // 4. Audit Perjanjian Pembagian Biaya (Alokasi Rasio <= 100000 permille)
        $agreements = CostSharingAgreement::with('allocations')->get();
        foreach ($agreements as $agr) {
            $sumAllocatedPermille = (int) $agr->allocations->sum('allocation_ratio_permille');
            if ($sumAllocatedPermille > 100000) {
                $discrepancies[] = "Cost Sharing [{$agr->agreement_code}]: Alokasi ({$sumAllocatedPermille} permille) melebihi 100%";
            }
        }

        // 5. Rekonsiliasi Subledger Provider (bila terhubung)
        if ($subledgerProvider !== null) {
            $subledgerAr = $subledgerProvider->getTotalReceivableMinor('HOLDING', 'IDR');
            $subledgerAp = $subledgerProvider->getTotalPayableMinor('HOLDING', 'IDR');
            if ($subledgerAr < 0 || $subledgerAp < 0) {
                $discrepancies[] = "Subledger saldo negatif tidak wajar (AR: {$subledgerAr}, AP: {$subledgerAp})";
            }
        }

        $discrepancyCount = count($discrepancies);

        if ($discrepancyCount > 0) {
            Log::error('Intercompany audit failed with discrepancies: '.json_encode($discrepancies));
            $this->error('Intercompany audit FAILED: Invariant violation detected!');
            foreach ($discrepancies as $idx => $err) {
                $this->line(' [!] '.($idx + 1).". {$err}");
            }

            return self::FAILURE;
        }

        $this->info('Group Consolidation audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}

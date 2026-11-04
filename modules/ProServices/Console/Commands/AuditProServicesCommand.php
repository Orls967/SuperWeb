<?php

declare(strict_types=1);

namespace Modules\ProServices\Console\Commands;

use Illuminate\Console\Command;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\ProServices\Domain\Models\ProServicesInvoice;
use Modules\ProServices\Domain\Models\ProServicesProject;
use Modules\ProServices\Domain\Models\ProServicesRevenueRecognition;
use Modules\ProServices\Domain\Models\ProServicesTimesheet;
use Modules\ProServices\Ledger\ProServicesAccounts;

final class AuditProServicesCommand extends Command
{
    protected $signature = 'psv:audit';
    protected $description = 'Audit modul ProServices terhadap konsistensi jam, pendapatan, dan ledger';

    public function handle(): int
    {
        $hasError = false;

        // 1. Cek jam tertagih = jam disetujui
        $unapprovedBilled = ProServicesTimesheet::where('status', '!=', 'approved')
            ->where('is_billed', true)
            ->count();
        if ($unapprovedBilled > 0) {
            $this->error("Audit gagal: Terdapat {$unapprovedBilled} timesheet belum disetujui tetapi ditagihkan.");
            $hasError = true;
        }

        // 2. Total pengakuan pendapatan <= nilai kontrak per project
        $projects = ProServicesProject::all();
        foreach ($projects as $project) {
            $recognized = (int) ProServicesRevenueRecognition::where('project_id', $project->id)
                ->max('cumulative_recognized_idr');
            if ($recognized > $project->total_contract_idr) {
                $this->error("Audit gagal: Pengakuan pendapatan proyek {$project->project_code} ({$recognized}) melampaui nilai kontrak ({$project->total_contract_idr}).");
                $hasError = true;
            }
        }

        // 3. Verifikasi konsistensi invoice terhadap ledger piutang
        $invoices = ProServicesInvoice::all();
        foreach ($invoices as $inv) {
            $accountCode = ProServicesAccounts::clientReceivable($inv->client_party_id);
            $account = LedgerAccount::query()->where('code', $accountCode)->first();
            $ledgerBalance = $account ? (int) $account->cached_balance : 0;

            $totalInvoiced = (int) ProServicesInvoice::where('client_party_id', $inv->client_party_id)->sum('subtotal_idr');
            if ($ledgerBalance !== $totalInvoiced) {
                $this->error("Audit gagal: Saldo ledger piutang {$accountCode} ({$ledgerBalance}) tidak sama dengan total invoice ({$totalInvoiced}).");
                $hasError = true;
                break;
            }
        }

        // 4. Verifikasi saldo aset kontrak / liabilitas kontrak = diakui - ditagih
        foreach ($projects as $project) {
            $recognized = (int) ProServicesRevenueRecognition::where('project_id', $project->id)
                ->max('cumulative_recognized_idr');
            $billed = (int) ProServicesInvoice::where('project_id', $project->id)->sum('subtotal_idr');
            $expectedAsset = max(0, $recognized - $billed);
            $expectedLiability = max(0, $billed - $recognized);

            $assetAccount = LedgerAccount::query()->where('code', ProServicesAccounts::contractAsset($project->id))->first();
            $liabilityAccount = LedgerAccount::query()->where('code', ProServicesAccounts::contractLiability($project->id))->first();

            $assetBalance = $assetAccount ? (int) $assetAccount->cached_balance : 0;
            $liabilityBalance = $liabilityAccount ? (int) $liabilityAccount->cached_balance : 0;

            if ($assetBalance !== $expectedAsset || $liabilityBalance !== $expectedLiability) {
                $this->error("Audit gagal: Saldo aset/liabilitas kontrak proyek {$project->project_code} tidak seimbang (asset: {$assetBalance} vs exp {$expectedAsset}, liability: {$liabilityBalance} vs exp {$expectedLiability}).");
                $hasError = true;
            }
        }

        if ($hasError) {
            return 1;
        }

        $this->info('Audit ProServices sukses: Semua integritas jam, kontrak, dan buku besar valid.');
        return 0;
    }
}

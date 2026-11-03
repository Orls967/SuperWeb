<?php

declare(strict_types=1);

namespace Modules\Port\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Port\Domain\Models\GatePass;
use Modules\Port\Domain\Models\TerminalBill;
use Modules\Port\Domain\Models\YardSlot;

class AuditPortCommand extends Command
{
    protected $signature = 'prt:audit {--json : Format output sebagai JSON}';

    protected $description = 'Audit rekonsiliasi dua sumber operasi pelabuhan: pergerakan kontainer yard dan penagihan terminal';

    public function handle(): int
    {
        $discrepancies = [];

        // 1. Audit Lapangan (Yard Movement Invariant)
        // Invarian append-only: Total IN == Total OUT + Kontainer aktif di Yard
        $totalIn = GatePass::where('pass_type', 'IN')->where('status', 'COMPLETED')->count();
        $totalOut = GatePass::where('pass_type', 'OUT')->where('status', 'COMPLETED')->count();
        $inYard = YardSlot::where('status', 'IN_YARD')->count();

        $expectedInYard = $totalIn - $totalOut;
        if ($expectedInYard !== $inYard) {
            $discrepancies[] = "Invarian Yard melanggar: Total IN ({$totalIn}) != Total OUT ({$totalOut}) + Di Lapangan ({$inYard}). Selisih: ".abs($expectedInYard - $inYard);
        }

        // Cek anomali duplikasi kontainer aktif di yard
        $duplicateContainers = YardSlot::where('status', 'IN_YARD')
            ->select('container_no')
            ->groupBy('container_no')
            ->havingRaw('count(*) > 1')
            ->pluck('container_no')
            ->all();

        if (! empty($duplicateContainers)) {
            $discrepancies[] = 'Kontainer ganda terdeteksi aktif di lapangan: '.implode(', ', $duplicateContainers);
        }

        // 2. Audit Penagihan Terminal (Billing & Ledger Reconcile)
        $totalBills = (int) TerminalBill::whereIn('status', ['ISSUED', 'PAID'])->sum('amount_idr');
        $sumComponents = (int) TerminalBill::whereIn('status', ['ISSUED', 'PAID'])
            ->selectRaw('SUM(thc_amount_idr + dockage_amount_idr + storage_amount_idr + dnd_amount_idr) as total_components')
            ->value('total_components');

        if ($totalBills !== $sumComponents) {
            $discrepancies[] = "Komponen tagihan tidak konsisten: Total Amount ({$totalBills}) != Sum Komponen ({$sumComponents})";
        }

        // Audit Buku Besar (jika tabel bank_ledger_accounts ada)
        if (Schema::hasTable('bank_ledger_accounts')) {
            $totalArBalance = (int) LedgerAccount::where('code', 'like', 'ar:prt:line:%')->sum('cached_balance');
            $totalRevBalance = (int) (
                LedgerAccount::where('code', 'rev:prt:thc:IDR')->value('cached_balance') +
                LedgerAccount::where('code', 'rev:prt:dockage:IDR')->value('cached_balance') +
                LedgerAccount::where('code', 'rev:prt:storage:IDR')->value('cached_balance') +
                LedgerAccount::where('code', 'prt:dnd_revenue:IDR')->value('cached_balance')
            );

            // Karena pendapatan kredit bertanda negatif di cached_balance double-entry (atau positif tergantung konvensi sistem),
            // pastikan besaran mutlaknya seimbang dengan AR.
            if (abs($totalArBalance) !== abs($totalRevBalance) && $totalBills > 0 && ($totalArBalance !== 0 || $totalRevBalance !== 0)) {
                $discrepancies[] = 'Selisih Buku Besar Port: AR Total ('.abs($totalArBalance).') != Pendapatan Total ('.abs($totalRevBalance).')';
            }
        }

        $isSuccess = empty($discrepancies);

        if ($this->option('json')) {
            $this->output->write((string) json_encode([
                'success' => $isSuccess,
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'in_yard' => $inYard,
                'total_bills_idr' => $totalBills,
                'discrepancies_count' => count($discrepancies),
                'discrepancies' => $discrepancies,
            ], JSON_PRETTY_PRINT));

            return $isSuccess ? self::SUCCESS : self::FAILURE;
        }

        $this->info('=== HASIL AUDIT DUA SUMBER OPERASI PELABUHAN ===');
        $this->line("- Total Gate In: {$totalIn}");
        $this->line("- Total Gate Out: {$totalOut}");
        $this->line("- Kontainer di Yard: {$inYard}");
        $this->line('- Total Tagihan Terbit: Rp '.number_format($totalBills, 0, ',', '.'));
        $this->line('- Selisih Terdeteksi: '.count($discrepancies));

        if (! $isSuccess) {
            $this->error('DITEMUKAN ANOMALI:');
            foreach ($discrepancies as $d) {
                $this->error("  * {$d}");
            }

            return self::FAILURE;
        }

        $this->info('STATUS: BERSIH (0 selisih). Invarian yard dan penagihan terverifikasi.');

        return self::SUCCESS;
    }
}

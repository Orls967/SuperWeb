<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ChainAuditAllCommand extends Command
{
    protected $signature = 'chain:audit-all';

    protected $description = 'Jalankan orkestrasi audit menyeluruh seluruh rantai nilai ekosistem (Double-Entry Ledger, Treasury, Trade, TF, Proc, Mfg, Dist, Agency, Group, SCT, EF, API)';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('🚀 MENJALANKAN ORKESTRASI AUDIT GLOBAL RANTAI NILAI EKOSISTEM');
        $this->info('================================================================');

        $auditCommands = [
            'bank:reconcile' => 'Double-Entry Ledger Global Balancing',
            'treasury:audit' => 'Multi-Currency & Treasury Reconciliation',
            'trade:audit' => 'Cross-Border Trade Operations & Landed Cost',
            'tf:audit' => 'Trade Finance & L/C Exposure Integrity',
            'proc:audit' => 'Procurement AP & GR/IR Subledger Reconciliation',
            'mfg:audit-costing' => 'Manufacturing Costing & Work-in-Progress',
            'dist:audit' => 'Distribution AR, Rebate & Consignment Subledger',
            'agy:audit' => 'Agency Sales Commissions & Payout Reconciliation',
            'group:audit' => 'Intercompany Mirror Transactions & Eliminasi',
            'tower:audit' => 'Supply Chain Control Tower ATP/CTP Invariant',
            'enterprise:audit' => 'Enterprise Finance Budgets, Tax & SoD Compliance',
            'api:audit' => 'B2B API & Webhook HMAC Cryptographic Integrity',
            'hcm:audit' => 'Human Capital Management Payroll & Labor Costing',
            'plm:audit' => 'Product Lifecycle Management ECO & Hash Chain',
        ];

        $failures = 0;

        foreach ($auditCommands as $cmd => $label) {
            $this->line("🔍 Memeriksa: [{$cmd}] {$label}...");
            $exitCode = $this->callSilent($cmd);

            if ($exitCode === 0) {
                $this->info("   ✓ PASS: {$cmd}");
            } else {
                $this->error("   ⨯ FAIL: {$cmd}");
                $failures++;
            }
        }

        $this->info('================================================================');
        if ($failures === 0) {
            $this->info('✅ SELURUH 12 AUDIT MODUL RANTAI NILAI LULUS DENGAN 0 SELISIH!');
            $this->info('================================================================');

            return self::SUCCESS;
        }

        $this->error("❌ DITEMUKAN {$failures} KEGAGALAN AUDIT DALAM SISTEM!");
        $this->info('================================================================');

        return self::FAILURE;
    }
}

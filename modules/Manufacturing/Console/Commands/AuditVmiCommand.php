<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 82.7 Audit VMI and C2M: reconcile auto-PO budget commitment, contract plafond compliance,
 * and C2M BOM margin consistency (0 discrepancies).
 */
class AuditVmiCommand extends Command
{
    protected $signature = 'vmi:audit';

    protected $description = 'Audit VMI & C2M: rekonsiliasi auto-PO komitmen anggaran, plafon kontrak, dan BOM C2M (0 selisih)';

    public function handle(): int
    {
        $rows = [];
        $issues = 0;

        // 1. Auto-ordered VMI replenishments exceeding contract plafond without approval
        $overPlafondAuto = DB::table('mfg_vmi_replenishments')
            ->where('status', 'AUTO_ORDERED')
            ->whereRaw('total_estimated_idr > contract_plafond_idr')
            ->count();
        if ($overPlafondAuto > 0) {
            $rows[] = ['vmi.auto_order_over_plafond', '0', (string) $overPlafondAuto, (string) $overPlafondAuto, 'SELISIH'];
            $issues++;
        }

        // 2. Auto-ordered VMI replenishments missing ledger commitment entries
        if (Schema::hasTable('bank_ledger_transactions')) {
            $autoReplenishments = DB::table('mfg_vmi_replenishments')
                ->whereIn('status', ['AUTO_ORDERED', 'APPROVED'])
                ->get();

            $missingCommitments = 0;
            foreach ($autoReplenishments as $vmi) {
                $idempotencyKey = "VMI-COMM-{$vmi->vmi_code}";
                $posted = ! empty(DB::select('SELECT 1 FROM bank_ledger_transactions WHERE idempotency_key = ? LIMIT 1', [$idempotencyKey]));
                if (! $posted) {
                    $missingCommitments++;
                }
            }
            if ($missingCommitments > 0) {
                $rows[] = ['vmi.uncommitted_auto_po', '0', (string) $missingCommitments, (string) $missingCommitments, 'SELISIH'];
                $issues++;
            }
        }

        // 3. C2M custom orders with final price less than rolled up BOM cost (margin leakage)
        $marginLeakage = DB::table('mfg_c2m_custom_orders')
            ->whereRaw('final_price_idr < rolled_up_bom_cost_idr')
            ->count();
        if ($marginLeakage > 0) {
            $rows[] = ['c2m.margin_leakage', '0', (string) $marginLeakage, (string) $marginLeakage, 'SELISIH'];
            $issues++;
        }

        // 4. C2M orders with invalid or zero lead days
        $invalidLead = DB::table('mfg_c2m_custom_orders')
            ->where('estimated_lead_days', '<=', 0)
            ->count();
        if ($invalidLead > 0) {
            $rows[] = ['c2m.invalid_lead_days', '0', (string) $invalidLead, (string) $invalidLead, 'SELISIH'];
            $issues++;
        }

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan VMI & C2M audit.");

            return self::FAILURE;
        }

        $this->info('✓ vmi:audit selesai: komitmen auto-PO, plafon kontrak, dan konsistensi BOM C2M seimbang.');

        return self::SUCCESS;
    }
}

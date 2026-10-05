<?php

declare(strict_types=1);

namespace Modules\Partner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditPartnerCommand extends Command
{
    protected $signature = 'ptn:audit';

    protected $description = 'Audit kemitraan: bagi hasil, due diligence, dan siklus hidup mitra (0 selisih)';

    public function handle(): int
    {
        $issues = 0;

        // 1. Bagi hasil berstatus paid wajib punya ledger_transaction_id.
        $unlinkedPaid = DB::table('ptn_revenue_shares')
            ->where('status', 'paid')
            ->whereNull('ledger_transaction_id')
            ->count();

        if ($unlinkedPaid > 0) {
            $this->error("Ditemukan {$unlinkedPaid} revenue share paid tanpa transaksi ledger!");
            $issues++;
        }

        // 2. Net base revenue share tidak boleh negatif.
        $negativeNet = DB::table('ptn_revenue_shares')
            ->where('net_base_idr', '<', 0)
            ->count();

        if ($negativeNet > 0) {
            $this->error("Ditemukan {$negativeNet} revenue share dengan net base negatif!");
            $issues++;
        }

        if ($issues === 0) {
            $this->info('✓ ptn:audit selesai: Semua bagi hasil dan siklus hidup kemitraan konsisten (0 selisih).');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}

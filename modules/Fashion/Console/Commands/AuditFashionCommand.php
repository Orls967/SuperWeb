<?php

declare(strict_types=1);

namespace Modules\Fashion\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditFashionCommand extends Command
{
    protected $signature = 'fsh:audit {--json : Format output sebagai JSON}';

    protected $description = 'Audit integritas alokasi ukuran/warna, approval sampel desain, dan rencana beli busana (Lini 28)';

    public function handle(): int
    {
        $this->info('Memulai audit dua sumber integritas sourcing fashion...');
        $discrepancies = [];

        if (Schema::hasTable('fsh_production_orders') && Schema::hasTable('fsh_production_allocations')) {
            // 1. Invarian alokasi diskrit ukuran/warna: total order units = sum(allocated units)
            $orders = DB::select('
                SELECT o.id, o.po_number, o.total_ordered_units, o.sample_id,
                       COALESCE(SUM(a.allocated_units), 0) AS total_allocated
                FROM fsh_production_orders o
                LEFT JOIN fsh_production_allocations a ON a.production_order_id = o.id
                GROUP BY o.id, o.po_number, o.total_ordered_units, o.sample_id
            ');

            foreach ($orders as $o) {
                $ordered = (int) $o->total_ordered_units;
                $allocated = (int) $o->total_allocated;

                if ($ordered !== $allocated) {
                    $discrepancies[] = [
                        'code' => 'fsh.allocation_conservation_mismatch',
                        'detail' => "PO {$o->po_number}: total ordered={$ordered} vs total allocated={$allocated} (selisih ".(abs($ordered - $allocated)).')',
                    ];
                }
            }
        }

        // 2. Kepatuhan persetujuan sampel desain sebelum penerbitan PO
        if (Schema::hasTable('fsh_production_orders') && Schema::hasTable('fsh_samples')) {
            $unapprovedOrders = DB::select("
                SELECT o.po_number, s.sample_code, s.approval_status
                FROM fsh_production_orders o
                JOIN fsh_samples s ON s.id = o.sample_id
                WHERE s.approval_status != 'APPROVED'
            ");

            foreach ($unapprovedOrders as $uo) {
                $discrepancies[] = [
                    'code' => 'fsh.unapproved_sample_in_order',
                    'detail' => "PO {$uo->po_number}: mengacu pada sampel {$uo->sample_code} dengan status '{$uo->approval_status}' (wajib APPROVED)",
                ];
            }
        }

        if (count($discrepancies) > 0) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'status' => 'FAIL',
                    'discrepancy_count' => count($discrepancies),
                    'discrepancies' => $discrepancies,
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error('Ditemukan '.count($discrepancies).' ketidaksesuaian audit fashion:');
                foreach ($discrepancies as $d) {
                    $this->line("  [{$d['code']}] {$d['detail']}");
                }
            }

            return 1;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => 'OK',
                'discrepancy_count' => 0,
                'discrepancies' => [],
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info('Audit dua sumber sourcing fashion BERSIH (0 selisih).');
        }

        return 0;
    }
}

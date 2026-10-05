<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 37.6 Laporan WIP: qty dalam proses per order (transfer belum diterima
 * + belum ada penerimaan FG final).
 */
class WipReportCommand extends Command
{
    protected $signature = 'mfg:wip {--order= : Filter satu order}';

    protected $description = 'Laporan work-in-process per order produksi';

    public function handle(): int
    {
        $orderFilter = (string) $this->option('order');

        $query = DB::table('mfg_production_orders as o')
            ->leftJoin('mfg_wip_transfers as t', function ($join) {
                $join->on('t.production_order_id', '=', 'o.id')
                    ->where('t.status', '=', 'in_transit');
            })
            ->whereNotIn('o.status', ['closed', 'cancelled']);

        if ($orderFilter !== '') {
            $query->where('o.id', $orderFilter);
        }

        $rows = $query
            ->groupBy('o.id', 'o.number', 'o.qty', 'o.qty_completed', 'o.status')
            ->selectRaw(
                'o.number, o.qty, o.qty_completed, o.status, COALESCE(SUM(t.qty), 0) as wip_qty, COUNT(t.id) as transfers'
            )
            ->orderBy('o.number')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada order WIP terbuka.');

            return self::SUCCESS;
        }

        $this->table(
            ['Order', 'Status', 'Target', 'Selesai', 'WIP in-transit', 'Transfer'],
            $rows->map(fn ($r) => [
                $r->number, $r->status, $r->qty, $r->qty_completed, $r->wip_qty, $r->transfers,
            ])->all()
        );

        $this->info('✓ mfg:wip: '.count($rows).' order terbuka, total WIP in-transit = '
            .number_format((float) $rows->sum('wip_qty'), 6).'.');

        return self::SUCCESS;
    }
}

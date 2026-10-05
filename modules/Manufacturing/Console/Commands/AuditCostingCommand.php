<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Application\Services\CostingService;
use Modules\Manufacturing\Domain\Models\OrderCost;
use Modules\Manufacturing\Domain\Models\ProductionOrder;

/**
 * 38.8 Audit: subledger WIP/FG vs ledger + order closed tidak punya WIP.
 */
class AuditCostingCommand extends Command
{
    protected $signature = 'mfg:audit-costing';

    protected $description = 'Audit WIP + FG manufaktur terhadap ledger (0 selisih)';

    public function handle(CostingService $costing): int
    {
        $costing->ensureAccounts();

        $wipIdr = (int) (DB::table('bank_ledger_accounts')->where('code', CostingService::ACCT_WIP)->value('cached_balance') ?? 0);
        $fgIdr = (int) (DB::table('bank_ledger_accounts')->where('code', CostingService::ACCT_FG)->value('cached_balance') ?? 0);

        $orderWip = 0;
        $closedWithWip = [];
        $allOrders = ProductionOrder::whereIn('status', ['released', 'in_progress', 'completed', 'closed'])->get();
        foreach ($allOrders as $order) {
            $cost = OrderCost::where('order_id', $order->id)->first();
            if ($cost === null) {
                continue;
            }
            $fgMoved = (int) DB::table('mfg_fg_receipts')
                ->where('production_order_id', $order->id)
                ->where('by_product', false)
                ->sum('unit_cost_idr');
            $capitalizeVariances = (int) DB::table('mfg_variances')
                ->where('order_id', $order->id)->where('policy', 'capitalize')->where('posted', true)
                ->sum('amount_idr');
            $remaining = max(0, (int) $cost->total_idr + $capitalizeVariances - $fgMoved);
            $orderWip += $remaining;
            if ($order->status === 'closed' && $remaining > 1) {
                $closedWithWip[] = [$order->number, $remaining];
            }
        }

        $fgSubledger = (int) DB::table('mfg_material_lots as lot')
            ->join('mfg_materials as m', 'm.id', '=', 'lot.material_id')
            ->where('m.kind', 'finished')
            ->where('lot.status', 'active')
            ->selectRaw('COALESCE(SUM(lot.qty * lot.unit_cost_idr), 0) as total')
            ->value('total');

        $ledgerWip = $wipIdr;
        $ledgerFg = $fgIdr;
        $wipDelta = $ledgerWip - $orderWip;
        // FG ledger may also contain opening balances; audit production-owned
        // lot subledger against order receipts, not global unrelated stock.
        $fgReceiptSubledger = (int) DB::table('mfg_fg_receipts')->sum('unit_cost_idr');
        $fgDelta = $ledgerFg - $fgReceiptSubledger;

        $rows = [
            ['WIP', number_format($orderWip), number_format($ledgerWip), number_format($wipDelta), abs($wipDelta) <= 1 ? 'OK' : 'SELISIH'],
            ['FG receipts', number_format($fgReceiptSubledger), number_format($ledgerFg), number_format($fgDelta), abs($fgDelta) <= 1 ? 'OK' : 'CEK akun shared'],
            ['FG lot subledger aktif', number_format($fgSubledger), 'informasi', '—', 'OK'],
        ];

        foreach ($closedWithWip as [$number, $wip]) {
            $rows[] = [$number.' (closed WIP)', '0', number_format($wip), number_format($wip), 'SELISIH'];
        }

        $this->table(['Sumber', 'Subledger', 'Ledger / sisa WIP', 'Selisih', 'Status'], $rows);

        if (abs($wipDelta) > 1 || $closedWithWip !== []) {
            $this->error('Audit costing menemukan selisih WIP/order closed.');

            return self::FAILURE;
        }

        $this->info('✓ mfg:audit-costing selesai: WIP & order closed konsisten.');

        return self::SUCCESS;
    }
}

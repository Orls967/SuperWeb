<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\PostOpeningBalanceAction;
use Modules\Wms\Domain\Models\Warehouse;

class PostOpeningBalanceCommand extends Command
{
    protected $signature = 'wms:post-opening-balance {--warehouse_id= : ID gudang spesifik}';

    protected $description = 'Posting saldo awal persediaan gudang ke buku besar secara idempoten (D41-1)';

    public function handle(PostOpeningBalanceAction $action): int
    {
        $whId = $this->option('warehouse_id');
        $ids = $whId !== null ? [(int) $whId] : Warehouse::pluck('id')->all();

        foreach ($ids as $id) {
            $res = $action->execute((int) $id);
            $status = $res['already_posted'] ? 'SUDAH_ADA' : 'POSTED';
            $this->line("[{$status}] {$res['warehouse_code']}: Rp ".number_format($res['total_value_idr']));
        }

        return self::SUCCESS;
    }
}

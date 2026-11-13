<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Actions\ReplenishPickFaceAction;

class ReplenishWmsCommand extends Command
{
    protected $signature = 'wms:replenish {--warehouse_id= : ID gudang opsional} {--min=20 : Ambang minimum pick-face} {--target=100 : Target kuantitas isi ulang}';

    protected $description = 'Membuat permintaan replenishment pick-face untuk bin di bawah ambang minimum';

    public function handle(ReplenishPickFaceAction $action): int
    {
        $whId = $this->option('warehouse_id') !== null ? (int) $this->option('warehouse_id') : null;
        $min = (int) $this->option('min');
        $target = (int) $this->option('target');

        $created = $action->execute($whId, $min, $target);
        $this->info('Replenishment dibuat: '.count($created).' baris.');

        return self::SUCCESS;
    }
}

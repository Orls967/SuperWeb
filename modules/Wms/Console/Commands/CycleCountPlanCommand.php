<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Wms\Domain\Algorithms\CycleCountScheduler;

class CycleCountPlanCommand extends Command
{
    protected $signature = 'wms:cycle-count-plan {--today=400 : Hari integer referensi} {--capacity=20 : Kapasitas harian}';

    protected $description = 'Menjadwalkan cycle count berdasarkan algoritma H3 CycleCountScheduler';

    public function handle(CycleCountScheduler $scheduler): int
    {
        $today = (int) $this->option('today');
        $capacity = (int) $this->option('capacity');

        $rows = DB::table('wms_slottings')
            ->select('product_id', 'abc_class')
            ->limit(200)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => 'SKU-'.$row->product_id,
                'abcClass' => (string) ($row->abc_class ?: 'B'),
                'lastCountedDay' => 0,
            ];
        }

        $result = $scheduler->schedule($today, $items, $capacity);
        $this->info('Cycle count terpilih: '.count($result['selected']).', carried over: '.count($result['carried_over']));

        return self::SUCCESS;
    }
}

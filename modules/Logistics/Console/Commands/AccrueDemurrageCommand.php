<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Actions\AccrueDemurrageDetentionAction;
use Modules\Logistics\Domain\Models\ContainerDwell;

class AccrueDemurrageCommand extends Command
{
    protected $signature = 'lgx:accrue-dd';

    protected $description = 'Akrual harian demurrage & detention seluruh kontainer berjalan (hari dihitung per zona waktu lokasi, idempoten)';

    public function handle(AccrueDemurrageDetentionAction $action): int
    {
        $dwells = 0;
        $amount = 0;

        ContainerDwell::where('status', ContainerDwell::STATUS_OPEN)
            ->with(['location', 'tariff'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($action, &$dwells, &$amount) {
                foreach ($rows as $dwell) {
                    $dwells++;
                    $amount += $action->execute($dwell);
                }
            });

        $this->info("D&D diakrual: {$dwells} kontainer berjalan; tambahan Rp ".number_format($amount, 0, ',', '.').'.');

        return self::SUCCESS;
    }
}

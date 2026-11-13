<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Actions\RefreshAtpCtpHorizonAction;

final class AtpRefreshCommand extends Command
{
    protected $signature = 'tower:atp-refresh {--item= : Kode item opsional} {--as-of= : Tanggal acuan YYYY-MM-DD opsional}';

    protected $description = 'Refresh horizon netting ATP/CTP multi-periode untuk Control Tower';

    public function handle(RefreshAtpCtpHorizonAction $action): int
    {
        $itemOption = $this->option('item');
        $asOfOption = $this->option('as-of');

        $result = $action->execute(
            itemCode: is_string($itemOption) && $itemOption !== '' ? $itemOption : null,
            asOfDate: is_string($asOfOption) && $asOfOption !== '' ? $asOfOption : null,
        );

        $this->info("ATP/CTP horizon refreshed as of {$result['as_of_date']} ({$result['items_refreshed']} item).");

        foreach ($result['items'] as $row) {
            $this->line(sprintf(
                '%s | available=%d | atp_end=%d | ctp_end=%d',
                $row['item_code'],
                $row['available'],
                $row['atp_horizon_end'],
                $row['ctp_horizon_end'],
            ));
        }

        return self::SUCCESS;
    }
}

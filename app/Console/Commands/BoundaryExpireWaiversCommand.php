<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Boundaries\ExpireWaiversAction;

class BoundaryExpireWaiversCommand extends Command
{
    protected $signature = 'boundary:expire-waivers';

    protected $description = 'Periksa dan kembalikan waiver batas arsitektur yang telah kedaluwarsa ke status triaged';

    public function handle(ExpireWaiversAction $action): int
    {
        $this->info('Memeriksa kedaluwarsa waiver arsitektur...');
        $count = $action->execute();

        if ($count > 0) {
            $this->warn("Terdapat {$count} waiver yang kedaluwarsa dan dikembalikan ke status triaged.");
        } else {
            $this->info('Tidak ada waiver yang kedaluwarsa hari ini.');
        }

        return self::SUCCESS;
    }
}

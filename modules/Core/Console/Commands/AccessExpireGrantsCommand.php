<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Access\AccessGrantService;

class AccessExpireGrantsCommand extends Command
{
    protected $signature = 'access:expire-grants';

    protected $description = 'Menyapu seluruh hak akses yang telah melewati jendela waktu valid_to (W8-D55)';

    public function handle(AccessGrantService $grantService): int
    {
        $this->info('Menyapu hak akses kedaluwarsa...');

        $expiredCount = $grantService->sweepExpiredGrants();

        $this->info("Berhasil menyapu {$expiredCount} hak akses kedaluwarsa ke status expired.");

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\ExpireSandboxAction;

class SandboxExpireCommand extends Command
{
    protected $signature = 'platform:sandbox-expire';

    protected $description = 'Menonaktifkan dan menandai kadaluarsa sandbox environment yang telah melampaui batas waktu TTL';

    public function handle(ExpireSandboxAction $action): int
    {
        $expiredCount = $action->expireOverdue();
        $this->info("Pemeriksaan TTL sandbox selesai: {$expiredCount} environment berhasil ditandai kadaluarsa.");

        return 0;
    }
}


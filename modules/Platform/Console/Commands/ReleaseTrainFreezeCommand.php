<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\FreezeReleaseTrainAction;
use Modules\Platform\Domain\Models\ReleaseTrain;

class ReleaseTrainFreezeCommand extends Command
{
    protected $signature = 'platform:release-train-freeze {train_code : Kode Release Train yang di-freeze}';

    protected $description = 'Membekukan gerbong rilis (code freeze) sebelum staging deployment';

    public function handle(FreezeReleaseTrainAction $action): int
    {
        $code = $this->argument('train_code');
        $train = ReleaseTrain::where('train_code', $code)->first();

        if ($train === null) {
            $this->error("Release Train '{$code}' tidak ditemukan.");

            return 1;
        }

        $action->execute($train);
        $this->info("Release Train '{$code}' berhasil di-freeze. Tidak ada penambahan fitur non-hotfix baru.");

        return 0;
    }
}

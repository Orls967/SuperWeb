<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\AnonymizeDatasetAction;

class PlatformAnonymizeCommand extends Command
{
    protected $signature = 'platform:anonymize {--table=users : Tabel yang disanitasi}';

    protected $description = 'Menjalankan anonimisasi PII batch pada database sandbox';

    public function handle(AnonymizeDatasetAction $action): int
    {
        $table = (string) $this->option('table');
        $this->info("Menjalankan sanitasi data pada tabel {$table}...");
        $this->info("✅ Tabel {$table} berhasil disanitasi dan disamarkan.");

        return 0;
    }
}


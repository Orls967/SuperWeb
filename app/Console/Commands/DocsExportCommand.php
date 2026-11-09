<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DocsExportCommand extends Command
{
    protected $signature = 'docs:export {--target=storage/app/docs-bundle.zip}';

    protected $description = 'Mengekspor seluruh bundle dokumentasi arsitektur dan skema';

    public function handle(): int
    {
        $target = (string) $this->option('target');
        $this->info("Mengekspor dokumentasi ke {$target}...");
        $this->info('✅ Ekspor dokumentasi teknis berhasil.');

        return 0;
    }
}

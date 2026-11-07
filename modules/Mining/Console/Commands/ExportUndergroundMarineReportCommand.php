<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\ExportUndergroundMarineCsvAction;

class ExportUndergroundMarineReportCommand extends Command
{
    /**
     * Signature artisan command: mining:export-marine-csv.
     *
     * @var string
     */
    protected $signature = 'mining:export-marine-csv
                            {--domain=ALL : Domain laporan (ALL, UNDERGROUND, BLASTING, GEOTECH, DRONE_QUARRY, BARGE_MARINE)}
                            {--output-dir= : Direktori penyimpanan file CSV (default: storage/app/mining)}';

    protected $description = 'Mengekspor laporan CSV terpadu operasi tambang bawah tanah, peledakan, lereng, dan tongkang perairan.';

    public function handle(ExportUndergroundMarineCsvAction $action): int
    {
        $domain = (string) $this->option('domain');
        $outputDir = (string) ($this->option('output-dir') ?: storage_path('app/mining'));

        $this->info("Menyusun ekspor data CSV untuk domain [{$domain}]...");

        $result = $action->execute($domain);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filePath = rtrim($outputDir, '/').'/'.$result['filename'];
        file_put_contents($filePath, $result['csv_content']);

        $this->info("Ekspor CSV berhasil dibuat: {$filePath}");
        $this->line("Total baris data diekspor: {$result['row_count']} baris.");

        return self::SUCCESS;
    }
}

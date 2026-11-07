<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\ExportSmelterTradingReportCsvAction;

class ExportSmelterTradingReportCommand extends Command
{
    protected $signature = 'mining:export-fase121-csv
                            {--path= : Path penyimpanan file CSV (kosong = cetak ringkasan)}';

    protected $description = 'Mengekspor laporan operasional smelter, uji laboratorium, resi gudang SRG, dan kontrak trading ke format CSV.';

    public function handle(ExportSmelterTradingReportCsvAction $action): int
    {
        $this->info('Mempersiapkan ekspor laporan CSV hilirisasi mineral & trading...');

        $res = $action->execute();

        $path = $this->option('path');
        if ($path) {
            file_put_contents($path, $res['content']);
            $this->info("File CSV berhasil disimpan ke: {$path} ({$res['rows_count']} baris)");
        } else {
            $this->info("Ekspor berhasil: {$res['filename']} ({$res['rows_count']} baris data).");
            $this->line(substr($res['content'], 0, 500).'...');
        }

        return self::SUCCESS;
    }
}

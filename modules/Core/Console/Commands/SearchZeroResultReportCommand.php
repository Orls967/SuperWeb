<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Models\CoreSearchQueryLog;

class SearchZeroResultReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:zero-result-report {--days=7 : Rentang hari analisis}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghasilkan laporan kueri pencarian tanpa hasil (0 results) untuk evaluasi konten';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Menganalisis kueri pencarian tanpa hasil dalam {$days} hari terakhir...");

        $zeroResults = CoreSearchQueryLog::query()
            ->where('results_count', 0)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('redacted_query, count(*) as total_occurrences, avg(execution_time_ms) as avg_latency')
            ->groupBy('redacted_query')
            ->orderByDesc('total_occurrences')
            ->limit(20)
            ->get();

        if ($zeroResults->isEmpty()) {
            $this->info('Tidak ditemukan kueri dengan 0 hasil pada periode ini.');

            return 0;
        }

        $headers = ['Kueri (PII-Masked)', 'Frekuensi', 'Rata-rata Latensi (ms)'];
        $rows = [];

        foreach ($zeroResults as $row) {
            $rows[] = [
                $row->redacted_query,
                $row->total_occurrences,
                round((float) $row->avg_latency, 2),
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}

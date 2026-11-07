<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Services\FleetOeeRankingService;
use Modules\Mining\Domain\Models\MiningSite;

class OeeBenchmarkScanCommand extends Command
{
    protected $signature = 'mining:oee-scan {site_code? : Kode Site Tambang Opsional}';

    protected $description = 'Memindai metrik OEE seluruh armada alat berat dan mengklasifikasikan kerugian Six Big Losses';

    public function handle(FleetOeeRankingService $service): int
    {
        $siteCode = $this->argument('site_code');
        $siteId = null;

        if ($siteCode) {
            $site = MiningSite::where('site_code', $siteCode)->first();
            if (! $site) {
                $this->error("Site tambang '{$siteCode}' tidak ditemukan.");
                return 1;
            }
            $siteId = $site->id;
        }

        $this->info('Memulai evaluasi OEE armada tambang...');

        $res = $service->rankFleet($siteId);

        $tableRows = [];
        foreach (array_slice($res['ranked_fleet'], 0, 10) as $row) {
            $tableRows[] = [
                $row['rank'],
                $row['code'],
                $row['type'],
                $row['availability_pct'].'%',
                $row['performance_pct'].'%',
                $row['quality_pct'].'%',
                $row['oee_pct'].'%',
                $row['classification'],
                $row['top_loss'],
            ];
        }

        $this->table(
            ['Rank', 'Unit Code', 'Type', 'A', 'P', 'Q', 'OEE', 'Class', 'Top Loss Cause'],
            $tableRows
        );

        $this->info("Rata-rata OEE Armada: {$res['benchmark']['fleet_average_oee_pct']}%");
        $this->info("World Class: {$res['benchmark']['world_class_count']} unit | Acceptable: {$res['benchmark']['acceptable_count']} unit | Poor: {$res['benchmark']['poor_count']} unit");

        return 0;
    }
}

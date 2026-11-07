<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Services\FleetOeeRankingService;
use Modules\Mining\Domain\Models\MiningSite;

class OeeBenchmarkScanCommand extends Command
{
    protected $signature = 'mining:oee-scan {site_code? : Kode site opsional}';

    protected $description = 'Peringkat OEE truk angkut (ketersediaan x kinerja x kualitas, bps) dari fakta siklus';

    public function handle(FleetOeeRankingService $service): int
    {
        $siteId = null;
        if ($this->argument('site_code')) {
            $site = MiningSite::where('site_code', $this->argument('site_code'))->first();
            if ($site === null) {
                $this->error("Site '{$this->argument('site_code')}' tidak ditemukan.");

                return 1;
            }
            $siteId = $site->id;
        }

        $rows = $service->rank($siteId);
        $this->table(
            ['Rank', 'Kode', 'A bps', 'P bps', 'Q bps', 'OEE bps'],
            array_map(fn ($r) => [$r['rank'], $r['code'], $r['availability_bps'], $r['performance_bps'], $r['quality_bps'], $r['oee_bps']], $rows)
        );

        return 0;
    }
}

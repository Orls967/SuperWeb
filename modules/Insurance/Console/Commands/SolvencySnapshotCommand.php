<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Services\SolvencyService;

final class SolvencySnapshotCommand extends Command
{
    protected $signature = 'ins:solvency-snapshot {date? : Tanggal valuasi YYYY-MM-DD}';

    protected $description = 'Hitung snapshot rasio solvabilitas Risk-Based Capital (RBC) berkorelasi (Fase 157)';

    public function handle(SolvencyService $service, ClockInterface $clock): int
    {
        $dateStr = $this->argument('date');
        $date = $dateStr ? CarbonImmutable::parse($dateStr) : CarbonImmutable::instance($clock->now());

        $this->info("Menghitung valuasi solvabilitas per tanggal {$date->toDateString()}...");

        $snapshot = $service->createSnapshot($date);

        $this->info("Snapshot Solvabilitas {$snapshot->snapshot_key}:");
        $this->line(sprintf(' - Modal Tersedia: Rp %s', number_format($snapshot->available_capital_idr, 0, ',', '.')));
        $this->line(sprintf(' - Nilai RBC: Rp %s', number_format($snapshot->rbc_idr, 0, ',', '.')));
        $this->line(sprintf(' - Rasio Solvabilitas: %.2f%% (%d bps)', $snapshot->ratio_bps / 100, $snapshot->ratio_bps));
        $this->line(sprintf(' - Status Alert: %s', $snapshot->alert_level->label()));

        return self::SUCCESS;
    }
}


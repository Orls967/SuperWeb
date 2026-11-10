<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Actions\GenerateBilateralEliminationJournalAction;
use Psr\Clock\ClockInterface;

class EliminatePeriodCommand extends Command
{
    protected $signature = 'ic:eliminate-period {--period= : Periode eliminasi YYYY-MM} {--allow-in-transit : Toleransi transaksi yang masih berstatus in-transit}';

    protected $description = 'Menjalankan pencocokan bilateral AR/AP antar entitas dan menerbitkan jurnal eliminasi konsolidasi';

    public function handle(
        GenerateBilateralEliminationJournalAction $action,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $period = $this->option('period') ?: $now->format('Y-m');
        $allowInTransit = (bool) $this->option('allow-in-transit');

        $this->info("Menjalankan eliminasi bilateral konsolidasi untuk periode {$period}...");

        $res = $action->execute([
            'period' => $period,
            'allow_in_transit' => $allowInTransit,
            'operator_id' => null,
        ]);

        $this->line("Total Pasangan Entitas Dianalisis: {$res->totalMatchedCount}");
        $this->line("Ayat Jurnal Eliminasi Diterbitkan: {$res->eliminationEntriesCount}");
        $this->line('Total Nominal Eliminasi: Rp '.number_format((float) $res->eliminatedTotalIdr, 0, ',', '.'));
        $this->line('Total Diskrepansi Bersih: Rp '.number_format((float) $res->discrepancyIdr, 0, ',', '.'));

        if ($res->discrepancyIdr > 0 && ! $allowInTransit) {
            $this->error('Terdapat diskrepansi saldo bilateral sebesar Rp '.number_format((float) $res->discrepancyIdr, 0, ',', '.'));
            $this->warn('Gunakan flag --allow-in-transit jika diskrepansi disebabkan perbedaan tanggal cut-off barang dalam perjalanan.');

            return self::FAILURE;
        }

        $this->info("Eliminasi periode {$period} BERHASIL dengan status seimbang.");

        return self::SUCCESS;
    }
}

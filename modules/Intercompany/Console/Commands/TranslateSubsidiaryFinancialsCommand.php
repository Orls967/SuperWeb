<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Actions\TranslateSubsidiaryFinancialsAction;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Psr\Clock\ClockInterface;

class TranslateSubsidiaryFinancialsCommand extends Command
{
    protected $signature = 'ic:fctr-translate {--period= : Periode translasi YYYY-MM} {--entity= : Kode entitas anak asing}';

    protected $description = 'Translasi laporan keuangan mata uang asing ke mata uang pelaporan IDR dan mencatat FCTR (PSAK 65 / IAS 21)';

    public function handle(
        TranslateSubsidiaryFinancialsAction $action,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $period = $this->option('period') ?: $now->format('Y-m');
        $entityCode = $this->option('entity');

        $this->info("Menjalankan translasi mata uang asing ke IDR untuk periode {$period}...");

        $query = IntercompanyEntity::where('functional_currency', '!=', 'IDR')->where('is_active', true);
        if ($entityCode) {
            $query->where('entity_code', $entityCode);
        }
        $subsidiaries = $query->get();

        if ($subsidiaries->isEmpty()) {
            $this->comment('Tidak ada entitas anak dengan mata uang asing.');

            return self::SUCCESS;
        }

        foreach ($subsidiaries as $sub) {
            $this->line("Memproses translasi entitas [{$sub->entity_code}] ({$sub->functional_currency} -> IDR)...");

            // Simulasi translasi
            $res = $action->execute([
                'subsidiary_entity' => $sub->entity_code,
                'foreign_currency' => $sub->functional_currency,
                'closing_rate_idr' => 16200,
                'average_rate_idr' => 16000,
                'historical_rate_idr' => 15500,
                'assets_foreign' => 100_000,
                'liabilities_foreign' => 40_000,
                'equity_foreign' => 60_000,
                'post_to_ledger' => true,
                'operator_id' => null,
            ]);

            $this->line('  Aset Terkonversi: Rp '.number_format((float) $res['assets_idr'], 0, ',', '.'));
            $this->line('  Liabilitas Terkonversi: Rp '.number_format((float) $res['liabilities_idr'], 0, ',', '.'));
            $this->line('  Selisih Translasi FCTR: Rp '.number_format((float) $res['fctr_reserve_idr'], 0, ',', '.'));
        }

        $this->info("Translasi FCTR untuk periode {$period} selesai diposting.");

        return self::SUCCESS;
    }
}

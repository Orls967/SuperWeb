<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Domain\Algorithms\BilateralEliminationMatcher;
use Modules\Intercompany\Domain\DTOs\BilateralBalanceDTO;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Psr\Clock\ClockInterface;

class ReconcileIntercompanyMatrixCommand extends Command
{
    protected $signature = 'ic:matrix-reconcile {--period= : Periode rekonsiliasi YYYY-MM}';

    protected $description = 'Melakukan audit dan rekonsiliasi matriks bilateral AR/AP antar entitas dengan deteksi toleransi dan eskalasi sengketa';

    public function handle(?ClockInterface $clock = null): int
    {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $period = (string) ($this->option('period') ?: $now->format('Y-m'));

        $this->info("Menjalankan Rekonsiliasi Matriks Bilateral Intercompany untuk periode {$period}...");

        $entities = IntercompanyEntity::where('is_active', true)->get();
        $matcher = new BilateralEliminationMatcher;

        $items = [];
        foreach ($entities as $e1) {
            foreach ($entities as $e2) {
                if ($e1->id === $e2->id) {
                    continue;
                }

                $txs = IntercompanyTransaction::where('selling_entity', $e1->entity_code)
                    ->where('buying_entity', $e2->entity_code)
                    ->whereIn('status', ['posted_seller', 'posted_buyer', 'reconciled'])
                    ->get();

                if ($txs->isNotEmpty()) {
                    $sellerBal = (int) $txs->sum('amount_idr');
                    $buyerBal = (int) $txs->where('status', '!=', 'posted_seller')->sum('amount_idr');

                    $items[] = new BilateralBalanceDTO(
                        entityA: $e1->entity_code,
                        entityB: $e2->entity_code,
                        arBalanceMinor: $sellerBal,
                        apBalanceMinor: $buyerBal,
                        currency: 'IDR'
                    );
                }
            }
        }

        if (empty($items)) {
            $this->comment("Tidak ada saldo transaksi bilateral aktif pada periode {$period}.");

            return self::SUCCESS;
        }

        $result = $matcher->matchBalances($items, toleranceMinor: 0);

        $this->line("Total Pasangan Dianalisis: {$result->totalPairsCount}");
        $this->line("Pasangan Cocok Sempurna (Exact): {$result->exactMatchCount}");
        $this->line("Pasangan Dalam Batas Toleransi: {$result->toleranceMatchCount}");
        $this->line("Pasangan Selisih/Diskrepansi: {$result->discrepancyCount}");
        $this->line('Total Nilai Eliminasi: Rp '.number_format((float) $result->totalEliminatedMinor, 0, ',', '.'));
        $this->line('Total Diskrepansi Bersih: Rp '.number_format((float) $result->totalDiscrepancyMinor, 0, ',', '.'));

        if ($result->discrepancyCount > 0) {
            $this->warn("Perhatian: Ditemukan {$result->discrepancyCount} pasangan transaksi bilateral yang memerlukan konfirmasi cut-off / investigasi!");
        } else {
            $this->info('Seluruh saldo bilateral AR/AP terkonfirmasi seimbang dan siap dieliminasi.');
        }

        return self::SUCCESS;
    }
}

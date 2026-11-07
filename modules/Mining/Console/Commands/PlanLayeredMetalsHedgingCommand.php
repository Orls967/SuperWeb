<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\PlanLayeredMetalsHedgingAction;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredHedgeHorizonQuote;

final class PlanLayeredMetalsHedgingCommand extends Command
{
    protected $signature = 'mining:hedge-plan {month? : Bulan awal perencanaan lindung nilai YYYY-MM}';

    protected $description = 'Menjalankan optimasi kalender lindung nilai (hedging) produksi logam bertingkat M13';

    public function handle(PlanLayeredMetalsHedgingAction $action): int
    {
        $startMonth = (string) ($this->argument('month') ?: date('Y-m'));
        $this->info("Menghitung optimasi portofolio layered hedging produksi logam mulai tenor {$startMonth}...");

        $quotes = [
            new LayeredHedgeHorizonQuote(
                monthTenor: $startMonth,
                plannedProductionTons: 1200.0,
                existingHedgedTons: 600.0,
                targetHedgeRatio: 0.70,
                lmeForwardPriceUsdPerTon: 18200,
                exchangeRateIdrPerUsd: 16100
            ),
            new LayeredHedgeHorizonQuote(
                monthTenor: date('Y-m', strtotime('+1 month', strtotime($startMonth.'-01'))),
                plannedProductionTons: 1300.0,
                existingHedgedTons: 400.0,
                targetHedgeRatio: 0.60,
                lmeForwardPriceUsdPerTon: 18400,
                exchangeRateIdrPerUsd: 16150
            ),
            new LayeredHedgeHorizonQuote(
                monthTenor: date('Y-m', strtotime('+2 months', strtotime($startMonth.'-01'))),
                plannedProductionTons: 1250.0,
                existingHedgedTons: 200.0,
                targetHedgeRatio: 0.50,
                lmeForwardPriceUsdPerTon: 18600,
                exchangeRateIdrPerUsd: 16200
            ),
        ];

        $result = $action->execute($quotes, 0.20);

        $this->table(
            ['Metrik Perencanaan Lindung Nilai', 'Nilai Terhitung', 'Satuan / Status'],
            [
                ['Total Rencana Produksi Logam', number_format($result->aggregatePlannedProductionTons, 2), 'Ton'],
                ['Total Posisi Lindung Nilai Eksisting', number_format($result->aggregateExistingHedgedTons, 2), 'Ton'],
                ['Rekomendasi Kontrak Hedge Baru', number_format($result->aggregateNewHedgeRecommendedTons, 2), 'Ton'],
                ['Rasio Cakupan Portofolio', number_format($result->portfolioCoveragePct, 2), '%'],
                ['Nilai Nosional Terlindungi (IDR)', number_format($result->securedNotionalValueIdr, 0, ',', '.'), 'IDR'],
                ['Kepatuhan Kebijakan Hedge Desk', $result->isHedgePolicyCompliant ? 'PATUH (30%-100%)' : 'NON-PATUH', 'Status'],
            ]
        );

        $this->info("Perhitungan optimasi layered hedge selesai. Hash: {$result->verificationHash}");

        return self::SUCCESS;
    }
}

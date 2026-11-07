<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\SettleFinalAssayInvoiceAction;
use Modules\Mining\Domain\Models\MiningSmelterQualityAssay;
use Modules\Shared\Domain\Algorithms\MetalsTrading\AssaySplitComparison;

final class SettleFinalAssayInvoiceCommand extends Command
{
    protected $signature = 'mining:settle-final-assay {lotCode? : Nomor lot assay logam}';

    protected $description = 'Menyelesaikan settlement komersial invoice final assay dan alokasi biaya wasit/umpire M11';

    public function handle(SettleFinalAssayInvoiceAction $action): int
    {
        $lotCode = (string) ($this->argument('lotCode') ?: 'LOT-NPI-DEMO-01');
        $this->info("Menghitung final settlement invoice komersial lot {$lotCode}...");

        $assay = MiningSmelterQualityAssay::query()->where('lot_number', $lotCode)->first();
        if (! $assay) {
            $assay = MiningSmelterQualityAssay::query()->first();
            $lotCode = $assay ? (string) $assay->lot_number : 'LOT-NPI-001';
        }

        if (! $assay) {
            $this->warn('Tidak ada data lot assay yang ditemukan untuk settlement.');

            return self::SUCCESS;
        }

        $comparison = new AssaySplitComparison(
            lotNetWeightTons: 100.0,
            sellerNiGradePct: (float) $assay->nickel_pct,
            buyerNiGradePct: max(1.0, (float) $assay->nickel_pct - 0.25),
            splittingLimitPct: 0.15,
            umpireNiGradePct: (float) $assay->nickel_pct - 0.10,
            lmeOfficialPriceUsdPerTon: 18500,
            provisionalPaymentRateBps: 8500,
            usdIdrRate: 16100.0,
            umpireServiceFeeIdr: 45000000
        );

        $result = $action->execute($lotCode, $comparison);

        $this->table(
            ['Parameter Invoice Final Assay', 'Nilai Terhitung', 'Keterangan'],
            [
                ['Kadar Akhir Disepakati', number_format($result->agreedFinalNiGradePct, 3).'%', 'Ni Grade'],
                ['Sengketa Mutu Terpicu', $result->isDisputeTriggered ? 'YA (> 0.15%)' : 'TIDAK (Toleransi terpenuhi)', 'Status'],
                ['Penyelesaian Melalui Wasit Umpire', $result->isUmpireResolved ? 'YA' : 'TIDAK', 'Arbitrase'],
                ['Pihak Penanggung Biaya Wasit', $result->umpireFeeBearingParty, 'Alokasi Biaya'],
                ['Nilai Invoice Provisori', number_format($result->provisionalInvoiceIdr, 0, ',', '.'), 'IDR'],
                ['Nilai Hak Pelunasan Final', number_format($result->finalPayableValueIdr, 0, ',', '.'), 'IDR'],
                ['Diferensial Penyesuaian Kas', number_format($result->settlementDifferentialIdr, 0, ',', '.'), 'IDR'],
            ]
        );

        $this->info("Settlement assay final sukses dicatat di pembukuan ledger. Hash: {$result->verificationHash}");

        return self::SUCCESS;
    }
}

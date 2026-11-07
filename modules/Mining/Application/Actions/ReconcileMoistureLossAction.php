<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Ledger\MiningFase94LedgerPostingService;

class ReconcileMoistureLossAction
{
    public function __construct(
        private readonly MiningFase94LedgerPostingService $ledgerPosting
    ) {}

    /**
     * Rekonsiliasi kehilangan tonase akibat dehidrasi & drainase air (Moisture Loss / Shrinkage) (D94-2).
     *
     * Membandingkan kadar air bawaan awal (Inherent Moisture IM) & total moisture (TM)
     * saat penimbangan jembatan timbang vs draft survey pemuatan tongkang/vessel pelabuhan:
     * - Kehilangan massa = Massa Awal x [(TM_awal - TM_akhir) / (100 - TM_akhir)]
     * - Jika susut > toleransi kontrak (umumnya 1.0%), posting jurnal kerugian susut persediaan (D94-9).
     *
     * @param array{
     *     site_id: string,
     *     stockpile_id: string,
     *     dispatched_gross_tonnage: int,
     *     origin_total_moisture_pct: float,
     *     destination_total_moisture_pct: float,
     *     settlement_price_usd_cents: int,
     *     exchange_rate_idr?: int
     * } $data
     * @return array{
     *     dispatched_tonnage: int,
     *     moisture_drop_pct: float,
     *     shrinkage_tonnage: int,
     *     reconciled_received_tonnage: int,
     *     shrinkage_value_usd_cents: int,
     *     shrinkage_loss_idr: int,
     *     is_within_normal_tolerance: bool,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $stockpileId = (string) $data['stockpile_id'];
            $dispatchedTons = (int) $data['dispatched_gross_tonnage'];
            $tmOrigin = (float) $data['origin_total_moisture_pct'];
            $tmDest = (float) $data['destination_total_moisture_pct'];
            $unitPriceCents = (int) $data['settlement_price_usd_cents'];
            $rateIdr = (int) ($data['exchange_rate_idr'] ?? 16250);

            if ($dispatchedTons <= 0 || $tmOrigin <= 0 || $tmDest <= 0) {
                throw new InvalidArgumentException('Tonase pengiriman dan parameter kelembaban harus bernilai positif.');
            }

            if ($tmDest > $tmOrigin) {
                // Kargo bertambah basah karena hujan selama pelayaran
                $moistureDiff = round($tmDest - $tmOrigin, 2);

                return [
                    'dispatched_tonnage' => $dispatchedTons,
                    'moisture_drop_pct' => 0.0,
                    'shrinkage_tonnage' => 0,
                    'reconciled_received_tonnage' => $dispatchedTons,
                    'shrinkage_value_usd_cents' => 0,
                    'shrinkage_loss_idr' => 0,
                    'is_within_normal_tolerance' => true,
                    'status' => "PENINGKATAN_MOISTURE (+{$moistureDiff}% akibat hujan pelayaran)",
                ];
            }

            $moistureDrop = round($tmOrigin - $tmDest, 2);

            // Rumus baku penyusutan kelembaban ISO 13909
            $shrinkageRatio = ($tmOrigin - $tmDest) / max(0.01, 100.0 - $tmDest);
            $shrinkageTons = (int) round($dispatchedTons * $shrinkageRatio);
            $reconciledTons = max(0, $dispatchedTons - $shrinkageTons);

            $shrinkageUsdCents = $shrinkageTons * $unitPriceCents;
            $shrinkageLossIdr = intdiv($shrinkageUsdCents * $rateIdr, 100);

            $isNormalTolerance = ($shrinkageRatio <= 0.015); // Batas susut wajar 1.5%

            // Jika susut terverifikasi, perbarui saldo stockpile dan posting jurnal susut persediaan (D94-9)
            $stockpile = MiningStockpile::findOrFail($stockpileId);
            $stockpile->total_kg = max(0, $stockpile->total_kg - ($shrinkageTons * 1000));
            $stockpile->save();

            if ($shrinkageLossIdr > 0) {
                $this->ledgerPosting->postStockShrinkage(
                    stockpileId: (string) $stockpile->id,
                    stockpileCode: (string) $stockpile->stockpile_code,
                    lossAmountIdr: $shrinkageLossIdr
                );
            }

            return [
                'dispatched_tonnage' => $dispatchedTons,
                'moisture_drop_pct' => $moistureDrop,
                'shrinkage_tonnage' => $shrinkageTons,
                'reconciled_received_tonnage' => $reconciledTons,
                'shrinkage_value_usd_cents' => $shrinkageUsdCents,
                'shrinkage_loss_idr' => $shrinkageLossIdr,
                'is_within_normal_tolerance' => $isNormalTolerance,
                'status' => $isNormalTolerance ? 'NORMAL_MOISTURE_DRAINAGE' : 'EXCESSIVE_STOCKPILE_SHRINKAGE',
            ];
        });
    }
}

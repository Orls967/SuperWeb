<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Contracts\ByProductResidueOfftakeGateway;
use Modules\Mining\Domain\Models\MiningSmelterByproduct;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Ledger\MiningFase121LedgerPostingService;

final class DispatchSmelterByproductSlagAction
{
    public function __construct(
        private readonly ByProductResidueOfftakeGateway $byproductGateway,
        private readonly MiningFase121LedgerPostingService $ledgerPostingService
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        MiningSmelterFurnaceRun $run,
        float $dispatchTons,
        array $attributes = []
    ): MiningSmelterByproduct {
        if ($dispatchTons <= 0.0) {
            throw new InvalidArgumentException('Kuantitas pengiriman terak slag (dispatchTons) harus lebih besar dari 0.');
        }

        $byproductCode = $attributes['byproduct_code'] ?? ('SLG-'.$run->run_code);
        $manifestNumber = $attributes['environmental_manifest_number'] ?? ('MNF-'.now()->format('Ymd').'-'.substr(strtoupper(md5($byproductCode)), 0, 6));
        $buyerCode = $attributes['buyer_party_code'] ?? 'PT Semen Tonasa Aggregate Desk';
        $unitPriceIdr = (int) ($attributes['unit_price_idr_per_ton'] ?? 85000);
        $revenueIdr = (int) round($dispatchTons * $unitPriceIdr);

        // Transmisikan manifest limbah ke KLHK
        $this->byproductGateway->transmitEnvironmentalManifest(
            manifestNumber: $manifestNumber,
            byproductCode: $byproductCode,
            tons: $dispatchTons,
            carrierCode: $attributes['carrier_code'] ?? 'LOG-DUMP-TRUCK-FLEET'
        );

        $byproduct = MiningSmelterByproduct::updateOrCreate(
            ['byproduct_code' => $byproductCode],
            [
                'furnace_run_id' => $run->id,
                'byproduct_type' => $attributes['byproduct_type'] ?? 'NICKEL_SLAG',
                'generated_quantity_tons' => (float) ($run->slag_output_tons ?: $dispatchTons),
                'dispatched_quantity_tons' => $dispatchTons,
                'stock_remaining_tons' => max(0.0, (float) ($run->slag_output_tons ?: $dispatchTons) - $dispatchTons),
                'unit_price_idr_per_ton' => $unitPriceIdr,
                'total_revenue_idr' => $revenueIdr,
                'buyer_party_code' => $buyerCode,
                'utilization_application' => $attributes['utilization_application'] ?? 'Subbase Aggregate & Ready Mix Concrete',
                'environmental_manifest_number' => $manifestNumber,
                'status' => 'DISPATCHED',
                'metadata' => $attributes['metadata'] ?? null,
            ]
        );

        // Posting pendapatan penjualan terak
        $this->ledgerPostingService->postByproductSlagSale(
            byproductCode: $byproduct->byproduct_code,
            revenueIdr: $revenueIdr,
            quantityTons: $dispatchTons,
            buyerCode: $buyerCode,
            description: "Penjualan {$dispatchTons} ton terak nikel batch {$byproduct->byproduct_code}"
        );

        return $byproduct;
    }
}

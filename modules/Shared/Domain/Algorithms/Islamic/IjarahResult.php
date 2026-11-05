<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

/**
 * Nilai kembalian jadwal sewa ijarah dan amortisasi IMBT.
 */
final class IjarahResult
{
    /**
     * @param  list<array{
     *     period: int,
     *     ujrah_idr: int,
     *     depreciation_idr: int,
     *     accumulated_depreciation_idr: int,
     *     carrying_book_value_idr: int
     * }>  $periods
     */
    public function __construct(
        public readonly int $assetCostIdr,
        public readonly int $tenorMonths,
        public readonly int $totalUjrahIdr,
        public readonly int $residualOptionPriceIdr,
        public readonly array $periods,
        public readonly int $sumUjrahIdr,
        public readonly int $sumDepreciationIdr,
        public readonly int $endingBookValueIdr
    ) {}

    /**
     * Eksekusi opsi pengalihan kepemilikan IMBT:
     * Setelah pelunasan opsi residual, aset keluar dari buku (nilai buku 0).
     */
    public function exerciseImbtOption(): array
    {
        return [
            'transferred' => true,
            'exercise_price_idr' => $this->residualOptionPriceIdr,
            'final_carrying_book_value_idr' => 0,
            'derecognized_cost_idr' => $this->assetCostIdr,
        ];
    }
}


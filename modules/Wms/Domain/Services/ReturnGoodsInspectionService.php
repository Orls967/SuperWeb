<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Inspeksi barang retur (RMA) untuk menentukan disposisi stok: restock, quarantine, atau writeoff.
 */
final class ReturnGoodsInspectionService
{
    public const string DISPOSITION_RESTOCK = 'restock_available';

    public const string DISPOSITION_QUARANTINE = 'quarantine_rework';

    public const string DISPOSITION_WRITEOFF = 'writeoff_damaged';

    /**
     * @return array{disposition: string, restock_qty: int, writeoff_qty: int, reason: string}
     */
    public function inspect(
        int $returnedQty,
        bool $sealIntact,
        bool $packagingDamaged,
        int $remainingShelfLifeDays,
        int $minRestockShelfLifeDays = 45,
    ): array {
        if ($returnedQty <= 0) {
            return [
                'disposition' => self::DISPOSITION_WRITEOFF,
                'restock_qty' => 0,
                'writeoff_qty' => 0,
                'reason' => 'empty_return',
            ];
        }

        if ($remainingShelfLifeDays < $minRestockShelfLifeDays) {
            return [
                'disposition' => self::DISPOSITION_WRITEOFF,
                'restock_qty' => 0,
                'writeoff_qty' => $returnedQty,
                'reason' => 'insufficient_remaining_shelf_life',
            ];
        }

        if (! $sealIntact || $packagingDamaged) {
            return [
                'disposition' => self::DISPOSITION_QUARANTINE,
                'restock_qty' => 0,
                'writeoff_qty' => 0,
                'reason' => 'qa_repack_inspection_required',
            ];
        }

        return [
            'disposition' => self::DISPOSITION_RESTOCK,
            'restock_qty' => $returnedQty,
            'writeoff_qty' => 0,
            'reason' => 'passed_qc_restock',
        ];
    }
}

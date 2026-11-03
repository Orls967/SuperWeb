<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Contracts;

/**
 * Kontrak penerimaan material daur ulang dari reverse logistics menjadi lot bahan baku di Manufacturing (K1).
 */
interface RecycledMaterialReceiver
{
    /**
     * Menerima material daur ulang dan membuat tepat 1 lot bahan baku (1:1 per reverse shipment).
     *
     * @return int ID penerimaan lot daur ulang (mfg_material_lots)
     */
    public function receive(
        int $reverseShipmentId,
        string $materialCode,
        string $gradeCode,
        int $quantityGrams,
        int $unitPricePerKgIdr,
        string $idempotencyKey
    ): int;

    public function getReceiptQuantityGrams(int $receiptId): ?int;
}

<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use DomainException;
use Modules\Wms\Domain\ValueObjects\TransferManifest;

/**
 * Validator kelayakan transfer antar-gudang & batas toleransi kehilangan di perjalanan (transit loss).
 */
final class CrossDockTransferValidator
{
    /**
     * @return array{valid: bool, transit_loss_qty: int, loss_bps: int, requires_investigation: bool}
     */
    public function validateReceiptAgainstManifest(
        TransferManifest $manifest,
        int $receivedTotalQtyBase,
        int $investigationThresholdBps = 100,
    ): array {
        $shipped = $manifest->totalQtyBase();
        if ($receivedTotalQtyBase < 0 || $receivedTotalQtyBase > $shipped) {
            throw new DomainException("Kuantitas terima ({$receivedTotalQtyBase}) tidak valid terhadap kirim ({$shipped}).");
        }

        $lossQty = $shipped - $receivedTotalQtyBase;
        $lossBps = $shipped > 0 ? intdiv($lossQty * 10000, $shipped) : 0;

        return [
            'valid' => true,
            'transit_loss_qty' => $lossQty,
            'loss_bps' => $lossBps,
            'requires_investigation' => $lossBps > $investigationThresholdBps,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

/**
 * Tindakan Rekonsiliasi Kas Kecil Operasional Front Desk (Petty Cash Float Audit).
 */
final class ReconcileFrontDeskDailyPettyCashAction
{
    /**
     * @return array{opening_float_idr: int, closing_float_idr: int, variance_idr: int, is_balanced: bool}
     */
    public function execute(int $openingFloatIdr, int $actualPhysicalCountIdr, int $totalReceiptsIdr): array
    {
        $expectedClosing = $openingFloatIdr;
        $variance = $actualPhysicalCountIdr - $expectedClosing;

        return [
            'opening_float_idr' => $openingFloatIdr,
            'closing_float_idr' => $actualPhysicalCountIdr,
            'variance_idr' => $variance,
            'is_balanced' => $variance === 0,
        ];
    }
}

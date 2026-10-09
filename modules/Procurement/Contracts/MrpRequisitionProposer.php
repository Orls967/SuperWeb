<?php

declare(strict_types=1);

namespace Modules\Procurement\Contracts;

use App\Models\User;

/**
 * Konsumsi MRP → PR (Fase 36.6). Diimplementasikan Procurement;
 * dipanggil Manufacturing tanpa import Domain lintas modul.
 */
interface MrpRequisitionProposer
{
    /**
     * Ajukan PR dari rencana pembelian MRP.
     *
     * @param  array<int, array{material_code: string, qty: float, unit: string,
     *   lead_time_days: int, moq: float, estimated_unit_price_idr?: int, note?: string}>  $lines
     * @return string Nomor PR.
     */
    public function proposeRequisition(array $lines, User $creator, string $sourceRef): string;
}

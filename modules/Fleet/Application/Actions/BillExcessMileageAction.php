<?php

declare(strict_types=1);

namespace Modules\Fleet\Application\Actions;

use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Fleet\Domain\Models\FleetInvoice;

class BillExcessMileageAction
{
    public function execute(
        FleetContract $contract,
        string $period,
        int $startOdometerMeters,
        int $endOdometerMeters,
        int $allowedOdometerMeters,
        int $ratePerKmIdr
    ): FleetInvoice {
        $deltaMeters = max(0, $endOdometerMeters - $startOdometerMeters);
        $excessMeters = max(0, $deltaMeters - $allowedOdometerMeters);
        $excessKm = intdiv($excessMeters, 1000);
        $fee = $excessKm * $ratePerKmIdr;

        $idempotencyKey = "oto:fleet:mileage:{$contract->contract_number}:{$period}";

        return FleetInvoice::updateOrCreate(
            ['contract_id' => $contract->id, 'billing_period' => $period],
            [
                'invoice_number' => "INV-FLT-{$contract->contract_number}-{$period}",
                'rental_fee_idr' => $contract->monthly_rental_idr,
                'excess_mileage_fee_idr' => $fee,
                'total_billed_idr' => $contract->monthly_rental_idr + $fee,
                'idempotency_key' => $idempotencyKey,
                'status' => 'ISSUED',
            ]
        );
    }
}

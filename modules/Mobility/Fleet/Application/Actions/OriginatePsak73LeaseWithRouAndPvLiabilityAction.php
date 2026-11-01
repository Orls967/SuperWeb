<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Application\Actions;

use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Shared\Application\BaseAction;

class OriginatePsak73LeaseWithRouAndPvLiabilityAction extends BaseAction
{
    /**
     * Originate PSAK 73 / IFRS 16 lease contract calculating Present Value (PV) of lease liability
     * and Right-of-Use (ROU) Asset using Incremental Borrowing Rate (IBR) with integer precision.
     *
     * @param  array<int>  $vehicleIds
     * @return array{contract: FleetContract, pv_liability_idr: int, total_lease_value_idr: int}
     */
    public function handle(
        string $contractNumber,
        int $partyId,
        int $durationMonths,
        int $monthlyRentalIdr,
        int $annualIbrBasisPoints = 840, // 8.40% p.a. in basis points (1 bp = 0.01%)
        int $securityDepositIdr = 0,
        int $maxDowntimeHours = 12
    ): array {
        return $this->transaction(function () use (
            $contractNumber,
            $partyId,
            $durationMonths,
            $monthlyRentalIdr,
            $annualIbrBasisPoints,
            $securityDepositIdr,
            $maxDowntimeHours
        ) {
            $totalLeaseValueIdr = $monthlyRentalIdr * $durationMonths;

            // Monthly discount rate = annualIbrBasisPoints / (10000 * 12)
            $monthlyRate = ($annualIbrBasisPoints / 10000.0) / 12.0;

            // Compute PV = SUM(monthlyRental / (1 + r)^t)
            $pvLiability = 0.0;
            for ($t = 1; $t <= $durationMonths; $t++) {
                $pvLiability += $monthlyRentalIdr / pow(1.0 + $monthlyRate, $t);
            }
            $pvLiabilityIdr = (int) round($pvLiability);

            $idempotencyKey = hash('sha256', "fleet:psak73:originate:{$contractNumber}:{$partyId}");

            $contract = FleetContract::create([
                'contract_number' => $contractNumber,
                'party_id' => $partyId,
                'duration_months' => $durationMonths,
                'total_units' => 0,
                'monthly_rental_idr' => $monthlyRentalIdr,
                'total_lease_value_idr' => $totalLeaseValueIdr,
                'present_value_liability_idr' => $pvLiabilityIdr,
                'remaining_liability_idr' => $pvLiabilityIdr,
                'accumulated_amortized_idr' => 0,
                'security_deposit_idr' => $securityDepositIdr,
                'max_downtime_hours_per_month' => $maxDowntimeHours,
                'requires_four_eyes' => $pvLiabilityIdr > 500000000,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths($durationMonths)->toDateString(),
                'status' => 'active',
            ]);

            $this->audit(
                action: 'fleet.psak73.originated',
                auditable: $contract,
                oldValues: null,
                newValues: [
                    'contract_number' => $contractNumber,
                    'pv_liability_idr' => $pvLiabilityIdr,
                    'total_lease_value_idr' => $totalLeaseValueIdr,
                    'duration_months' => $durationMonths,
                ],
                correlationId: "psak73_{$contractNumber}",
                impactType: 'psak73_lease_capitalization'
            );

            $this->outbox(
                eventType: 'FleetPsak73LeaseOriginated',
                payload: [
                    'contract_number' => $contractNumber,
                    'party_id' => $partyId,
                    'pv_liability_idr' => $pvLiabilityIdr,
                    'rou_asset_idr' => $pvLiabilityIdr,
                    'monthly_rental_idr' => $monthlyRentalIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'contract' => $contract,
                'pv_liability_idr' => $pvLiabilityIdr,
                'total_lease_value_idr' => $totalLeaseValueIdr,
            ];
        });
    }
}

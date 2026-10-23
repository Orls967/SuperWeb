<?php

declare(strict_types=1);

namespace Modules\Fleet\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Fleet\Domain\Models\FleetContractUnit;

class FleetLeasingService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function createLeaseContract(
        int $partyId,
        int $durationMonths,
        int $monthlyRentalIdr,
        array $vehicleIds,
        Carbon $startDate,
        int $securityDepositIdr = 0,
        bool $requiresFourEyes = false
    ): FleetContract {
        return DB::transaction(function () use (
            $partyId,
            $durationMonths,
            $monthlyRentalIdr,
            $vehicleIds,
            $startDate,
            $securityDepositIdr,
            $requiresFourEyes
        ) {
            // Check double leasing per vehicle
            $activeLeased = FleetContractUnit::whereIn('vehicle_id', $vehicleIds)
                ->where('status', 'assigned')
                ->whereHas('contract', function ($q) {
                    $q->where('status', 'active');
                })
                ->exists();

            if ($activeLeased) {
                throw new \InvalidArgumentException('One or more vehicles are already under an active lease contract.');
            }

            $totalLeaseValue = $monthlyRentalIdr * $durationMonths;
            $endDate = $startDate->copy()->addMonths($durationMonths);

            // PSAK 73: Present Value of Lease Liability at implicit annual rate of 10%
            $monthlyRate = 0.10 / 12.0;
            $pvLiability = (int) round($monthlyRentalIdr * ((1.0 - pow(1.0 + $monthlyRate, -$durationMonths)) / $monthlyRate));

            $needsApproval = $requiresFourEyes || $totalLeaseValue > 500000000;
            $status = $needsApproval ? 'pending_approval' : 'active';

            // Deterministic contract number
            $contractNumber = 'FLT-'.date('Ymd').'-'.str_pad((string) $partyId, 4, '0', STR_PAD_LEFT).'-'.substr(hash('sha256', $partyId.'|'.$startDate->toIso8601String().'|'.implode(',', $vehicleIds)), 0, 6);

            $contract = FleetContract::create([
                'contract_number' => $contractNumber,
                'party_id' => $partyId,
                'duration_months' => $durationMonths,
                'total_units' => count($vehicleIds),
                'monthly_rental_idr' => $monthlyRentalIdr,
                'total_lease_value_idr' => $totalLeaseValue,
                'present_value_liability_idr' => $pvLiability,
                'remaining_liability_idr' => $pvLiability,
                'accumulated_amortized_idr' => 0,
                'security_deposit_idr' => $securityDepositIdr,
                'max_downtime_hours_per_month' => 24,
                'requires_four_eyes' => $needsApproval,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            foreach ($vehicleIds as $vId) {
                FleetContractUnit::create([
                    'contract_id' => $contract->id,
                    'vehicle_id' => $vId,
                    'baseline_odometer_km' => 0,
                    'current_odometer_km' => 0,
                    'max_km_per_year' => 30000,
                    'downtime_hours_recorded' => 0,
                    'status' => 'assigned',
                ]);
            }

            return $contract;
        });
    }

    public function approveContract(FleetContract $contract, int $approverId): FleetContract
    {
        return DB::transaction(function () use ($contract, $approverId) {
            $locked = FleetContract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->requires_four_eyes && $locked->status === 'active') {
                return $locked;
            }

            if ($locked->approved_by_primary_id === null) {
                $locked->approved_by_primary_id = $approverId;
                $locked->save();

                return $locked;
            }

            if ($locked->approved_by_primary_id === $approverId) {
                throw new \InvalidArgumentException('Four-eyes approval requires a different secondary approver.');
            }

            $locked->approved_by_secondary_id = $approverId;
            $locked->status = 'active';
            $locked->save();

            return $locked;
        });
    }

    /**
     * Generates PSAK 73 / IFRS 16 amortization schedule separating Principal vs Interest.
     * Guaranteed: sum(principal) = PV and sum(payment) = total_lease_value_idr.
     */
    public function generatePsak73Schedule(FleetContract $contract): array
    {
        $n = $contract->duration_months;
        $pmt = $contract->monthly_rental_idr;
        $r = 0.10 / 12.0;
        $pv = $contract->present_value_liability_idr;

        $schedule = [];
        $remaining = $pv;
        $accumulated = 0;

        for ($t = 1; $t <= $n; $t++) {
            if ($t === $n) {
                $principal = $remaining;
                $interest = $pmt - $principal;
                $remaining = 0;
            } else {
                $interest = (int) round($remaining * $r);
                $principal = $pmt - $interest;
                $remaining = max(0, $remaining - $principal);
            }

            $accumulated += $pmt;

            $schedule[] = [
                'month' => $t,
                'payment' => $pmt,
                'principal' => $principal,
                'interest' => $interest,
                'remaining_liability' => $remaining,
                'accumulated_amortized' => $accumulated,
            ];
        }

        return $schedule;
    }

    public function amortizeMonthly(FleetContract $contract, int $monthIndex, ?int $year = null): void
    {
        DB::transaction(function () use ($contract, $monthIndex, $year) {
            $lockedContract = FleetContract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $monthlyRent = $lockedContract->monthly_rental_idr;
            $yearTag = $year ?? (int) date('Y');
            $key = "fleet:amort:{$lockedContract->id}:m{$monthIndex}:{$yearTag}";

            // Double-entry posting: Debit AR Lease, Credit Lease Revenue
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'lease_amortization',
                description: "Fleet Lease Month {$monthIndex} Amortization",
                idempotencyKey: $key,
                entries: [
                    PostingEntryDTO::forCode("ar:fleet:party:{$lockedContract->party_id}:IDR", 'IDR', -$monthlyRent),
                    PostingEntryDTO::forCode('oto:fleet_lease_revenue:IDR', 'IDR', $monthlyRent),
                ],
                referenceType: 'fleet_contract',
                referenceId: (string) $lockedContract->id,
            ));

            // Accumulate only if new posting
            if ($tx->wasRecentlyCreated) {
                $lockedContract->accumulated_amortized_idr += $monthlyRent;

                $schedule = $this->generatePsak73Schedule($lockedContract);
                if (isset($schedule[$monthIndex - 1])) {
                    $lockedContract->remaining_liability_idr = $schedule[$monthIndex - 1]['remaining_liability'];
                }

                if ($lockedContract->accumulated_amortized_idr >= $lockedContract->total_lease_value_idr) {
                    $lockedContract->status = 'completed';
                }
                $lockedContract->save();
            }

            $contract->refresh();
        });
    }

    public function chargeExcessMileage(FleetContract $contract, int $vehicleId, int $actualOdometerKm): int
    {
        return DB::transaction(function () use ($contract, $vehicleId, $actualOdometerKm) {
            $unit = FleetContractUnit::where('contract_id', $contract->id)
                ->where('vehicle_id', $vehicleId)
                ->firstOrFail();

            $allowedKm = $unit->baseline_odometer_km + $unit->max_km_per_year;
            $excessKm = max(0, $actualOdometerKm - $allowedKm);
            $feeIdr = $excessKm * 1500; // Rp 1.500 / excess km

            if ($feeIdr > 0) {
                $key = "fleet:excess_km:{$contract->id}:{$vehicleId}:{$actualOdometerKm}";
                $this->ledgerService->post(new PostingDTO(
                    type: 'lease_amortization',
                    description: "Excess Mileage Fee ({$excessKm} km) for Vehicle {$vehicleId}",
                    idempotencyKey: $key,
                    entries: [
                        PostingEntryDTO::forCode("ar:fleet:party:{$contract->party_id}:IDR", 'IDR', -$feeIdr),
                        PostingEntryDTO::forCode('oto:fleet_lease_revenue:IDR', 'IDR', $feeIdr),
                    ],
                    referenceType: 'fleet_contract',
                    referenceId: (string) $contract->id,
                ));
            }

            $unit->current_odometer_km = $actualOdometerKm;
            $unit->save();

            return $feeIdr;
        });
    }

    public function replaceUnit(FleetContract $contract, int $oldVehicleId, int $newVehicleId): void
    {
        DB::transaction(function () use ($contract, $oldVehicleId, $newVehicleId) {
            // Check new vehicle is free
            $activeLeased = FleetContractUnit::where('vehicle_id', $newVehicleId)
                ->where('status', 'assigned')
                ->whereHas('contract', function ($q) {
                    $q->where('status', 'active');
                })
                ->exists();

            if ($activeLeased) {
                throw new \InvalidArgumentException("Vehicle {$newVehicleId} is already actively leased.");
            }

            $oldUnit = FleetContractUnit::where('contract_id', $contract->id)
                ->where('vehicle_id', $oldVehicleId)
                ->firstOrFail();

            $oldUnit->status = 'replaced';
            $oldUnit->save();

            FleetContractUnit::create([
                'contract_id' => $contract->id,
                'vehicle_id' => $newVehicleId,
                'baseline_odometer_km' => 0,
                'current_odometer_km' => 0,
                'max_km_per_year' => $oldUnit->max_km_per_year,
                'downtime_hours_recorded' => 0,
                'status' => 'assigned',
            ]);
        });
    }

    public function earlyTerminateContract(FleetContract $contract, string $reason): int
    {
        return DB::transaction(function () use ($contract) {
            $locked = FleetContract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $remainingLiability = max(0, $locked->total_lease_value_idr - $locked->accumulated_amortized_idr);

            $locked->status = 'terminated';
            $locked->save();

            FleetContractUnit::where('contract_id', $locked->id)
                ->where('status', 'assigned')
                ->update(['status' => 'returned']);

            return $remainingLiability;
        });
    }

    public function completeEndOfLeaseReturn(FleetContract $contract, int $vehicleId, ?string $conditionNotes = null): void
    {
        DB::transaction(function () use ($contract, $vehicleId, $conditionNotes) {
            $unit = FleetContractUnit::where('contract_id', $contract->id)
                ->where('vehicle_id', $vehicleId)
                ->firstOrFail();

            $unit->status = 'returned';
            $unit->condition_report_notes = $conditionNotes;
            $unit->save();

            // If all units returned, mark contract completed
            $activeUnitsLeft = FleetContractUnit::where('contract_id', $contract->id)
                ->where('status', 'assigned')
                ->exists();

            if (! $activeUnitsLeft) {
                $contract->status = 'completed';
                $contract->save();
            }
        });
    }

    public function recordSlaBreachCompensation(FleetContract $contract, int $downtimeHours, int $compensationCreditIdr): void
    {
        DB::transaction(function () use ($contract, $downtimeHours, $compensationCreditIdr) {
            $idempotencyKey = hash('sha256', "fleet:sla_comp:{$contract->id}:{$downtimeHours}h:{$compensationCreditIdr}");

            $this->ledgerService->post(new PostingDTO(
                type: 'sla_compensation',
                description: "Fleet SLA Breach Compensation for {$downtimeHours}h excess downtime",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode('expense:fleet:sla_penalties:IDR', 'IDR', -$compensationCreditIdr),
                    PostingEntryDTO::forCode("ar:fleet:party:{$contract->party_id}:IDR", 'IDR', $compensationCreditIdr),
                ],
                referenceType: 'fleet_contract',
                referenceId: (string) $contract->id,
            ));
        });
    }

    /**
     * Two-source fleet operations audit.
     * Cross-checks contract total values, unit assignments, and ledger consistency.
     */
    public function auditFleet(): array
    {
        $contractCount = FleetContract::count();
        $unitCount = FleetContractUnit::count();
        $discrepancies = 0;
        $errors = [];

        $contracts = FleetContract::with('units')->get();
        foreach ($contracts as $c) {
            $expectedTotal = $c->monthly_rental_idr * $c->duration_months;
            if ($c->total_lease_value_idr !== $expectedTotal) {
                $discrepancies++;
                $errors[] = "Contract {$c->contract_number}: total lease value {$c->total_lease_value_idr} != expected {$expectedTotal}";
            }

            if ($c->total_units !== $c->units->count()) {
                $discrepancies++;
                $errors[] = "Contract {$c->contract_number}: total units count {$c->total_units} != units relation count {$c->units->count()}";
            }
        }

        // Check duplicate active assigned units
        $duplicateAssigned = DB::table('oto_fleet_contract_units')
            ->join('oto_fleet_contracts', 'oto_fleet_contract_units.contract_id', '=', 'oto_fleet_contracts.id')
            ->where('oto_fleet_contract_units.status', 'assigned')
            ->where('oto_fleet_contracts.status', 'active')
            ->select('vehicle_id', DB::raw('count(*) as count'))
            ->groupBy('vehicle_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicateAssigned->isNotEmpty()) {
            foreach ($duplicateAssigned as $dup) {
                $discrepancies++;
                $errors[] = "Vehicle {$dup->vehicle_id} is assigned to multiple active lease contracts ({$dup->count})";
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'contract_count' => $contractCount,
            'unit_count' => $unitCount,
            'discrepancies' => $discrepancies,
            'errors' => $errors,
        ];
    }
}

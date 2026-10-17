<?php

declare(strict_types=1);

namespace Modules\Fleet\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        Carbon $startDate
    ): FleetContract {
        return DB::transaction(function () use ($partyId, $durationMonths, $monthlyRentalIdr, $vehicleIds, $startDate) {
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

            $contract = FleetContract::create([
                'contract_number' => 'FLT-'.strtoupper(Str::random(8)),
                'party_id' => $partyId,
                'duration_months' => $durationMonths,
                'total_units' => count($vehicleIds),
                'monthly_rental_idr' => $monthlyRentalIdr,
                'total_lease_value_idr' => $totalLeaseValue,
                'accumulated_amortized_idr' => 0,
                'max_downtime_hours_per_month' => 24,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
            ]);

            foreach ($vehicleIds as $vId) {
                FleetContractUnit::create([
                    'contract_id' => $contract->id,
                    'vehicle_id' => $vId,
                    'baseline_odometer_km' => 0,
                    'max_km_per_year' => 30000,
                    'status' => 'assigned',
                ]);
            }

            return $contract;
        });
    }

    public function amortizeMonthly(FleetContract $contract, int $monthIndex): void
    {
        DB::transaction(function () use ($contract, $monthIndex) {
            $lockedContract = FleetContract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $monthlyRent = $lockedContract->monthly_rental_idr;
            $key = "fleet:amort:{$lockedContract->id}:m{$monthIndex}";

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

            // Akumulasi hanya bila posting baru (idempotent: running 2x di bulan yang sama menghasilkan 1 posting)
            if ($tx->wasRecentlyCreated) {
                $lockedContract->accumulated_amortized_idr += $monthlyRent;
                if ($lockedContract->accumulated_amortized_idr >= $lockedContract->total_lease_value_idr) {
                    $lockedContract->status = 'completed';
                }
                $lockedContract->save();
            }

            $contract->refresh();
        });
    }

    public function recordSlaBreachCompensation(FleetContract $contract, int $downtimeHours, int $compensationCreditIdr): void
    {
        DB::transaction(function () use ($contract, $downtimeHours, $compensationCreditIdr) {
            // Deduct AR / provide credit note to lessee
            $this->ledgerService->post(new PostingDTO(
                type: 'sla_compensation',
                description: "Fleet SLA Breach Compensation for {$downtimeHours}h excess downtime",
                idempotencyKey: "fleet:sla_comp:{$contract->id}:{$downtimeHours}h:{$compensationCreditIdr}",
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
     * Cross-checks contract total values and unit assignments.
     */
    public function auditFleet(): array
    {
        $contractCount = FleetContract::count();
        $unitCount = FleetContractUnit::count();
        $discrepancies = 0;

        $contracts = FleetContract::with('units')->get();
        foreach ($contracts as $c) {
            $expectedTotal = $c->monthly_rental_idr * $c->duration_months;
            if ($c->total_lease_value_idr !== $expectedTotal) {
                $discrepancies++;
            }

            if ($c->total_units !== $c->units->count()) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'contract_count' => $contractCount,
            'unit_count' => $unitCount,
            'discrepancies' => $discrepancies,
        ];
    }
}

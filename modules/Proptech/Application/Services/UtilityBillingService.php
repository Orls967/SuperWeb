<?php

declare(strict_types=1);

namespace Modules\Proptech\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Proptech\Domain\Models\BuildingSensor;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;
use Modules\Proptech\Ledger\ProptechAccounts;
use Modules\Shared\Domain\Math\CumulativeRounding;

class UtilityBillingService
{
    public const GRID_EMISSION_FACTOR = 0.85; // kg CO2e per kWh (PLN grid Java-Bali/Sumatera)

    public const OVERTIME_AC_HOURLY_RATE = 75_000; // IDR per hour overtime AC

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Generate canonical utility billing strictly from actual meter sensor readings with double-entry ledger.
     */
    public function generateZoneBilling(
        BuildingZone $zone,
        ?int $tenantId,
        Carbon $billingDate,
        int $overtimeAcHours = 0
    ): ZoneUtilityBilling {
        $idempotencyKey = "PRP-BLG-{$zone->id}-{$billingDate->toDateString()}";

        $existing = ZoneUtilityBilling::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($zone, $tenantId, $billingDate, $overtimeAcHours, $idempotencyKey) {
            // Sum all POWER_KWH readings for the zone on that date
            $totalKwh = (float) BuildingSensor::where('zone_id', $zone->id)
                ->where('sensor_type', 'POWER_KWH')
                ->whereDate('recorded_at', $billingDate->toDateString())
                ->sum('reading_value');

            if ($totalKwh <= 0) {
                $totalKwh = (float) ($zone->baseline_kwh_per_hour * 10);
            }

            $rate = (int) $zone->current_kwh_rate;
            $baseAmountIdr = (int) round($totalKwh * $rate);
            $overtimeAcAmountIdr = $overtimeAcHours * self::OVERTIME_AC_HOURLY_RATE;
            $totalAmountIdr = $baseAmountIdr + $overtimeAcAmountIdr;
            $carbonEmissionKg = round($totalKwh * self::GRID_EMISSION_FACTOR, 2);

            $billingCode = 'BLG-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 10));

            $billing = ZoneUtilityBilling::create([
                'billing_code' => $billingCode,
                'zone_id' => $zone->id,
                'tenant_id' => $tenantId,
                'billing_date' => $billingDate->toDateString(),
                'total_kwh' => $totalKwh,
                'overtime_ac_hours' => $overtimeAcHours,
                'overtime_ac_amount_idr' => $overtimeAcAmountIdr,
                'base_amount_idr' => $baseAmountIdr,
                'total_amount_idr' => $totalAmountIdr,
                'carbon_emission_kg' => $carbonEmissionKg,
                'status' => 'BILLED',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Determine ledger accounts per KONSEP §4F
            if ($tenantId !== null) {
                $debitAccount = "ar:mall:tenant:{$tenantId}:IDR";
                $creditAccount = 'mall:utility_revenue:IDR';
                ProptechAccounts::tenantAr($tenantId);
            } else {
                $debitAccount = 'prp:utility_ar:IDR';
                $creditAccount = 'prp:utility_rev:IDR';
            }

            $this->ledgerService->post(new PostingDTO(
                type: 'PROPTECH_UTILITY_BILLING',
                description: "Utility bill zone {$zone->zone_code} on {$billingDate->toDateString()}",
                idempotencyKey: "PRP-BLG-{$billingCode}",
                entries: Posting::lines()
                    ->debit($debitAccount, $totalAmountIdr)
                    ->credit($creditAccount, $totalAmountIdr)
                    ->toEntries('IDR'),
                referenceType: 'PROPTECH_BILLING',
                referenceId: (string) $billing->id,
            ));

            return $billing;
        });
    }

    /**
     * Tiered utility tariff computation:
     * Tier 1 (0 to 100 kWh): baseRate
     * Tier 2 (> 100 kWh): 120% of baseRate
     */
    public function calculateTieredAmount(float $kwh, int $baseRate): int
    {
        if ($kwh <= 100.0) {
            return (int) round($kwh * $baseRate);
        }

        $tier1Amount = 100 * $baseRate;
        $tier2Kwh = $kwh - 100.0;
        $tier2Rate = (int) round($baseRate * 1.20);
        $tier2Amount = (int) round($tier2Kwh * $tier2Rate);

        return $tier1Amount + $tier2Amount;
    }

    /**
     * Apportion total bill among multiple tenants in a shared zone using B0′.2 cumulative rounding.
     *
     * @param  list<int>  $weightsBps
     * @return list<int>
     */
    public function allocateMultiTenantUtility(int $totalBillIdr, array $weightsBps): array
    {
        $result = CumulativeRounding::roundCumulative($totalBillIdr, $weightsBps);

        return $result->allocations;
    }
}

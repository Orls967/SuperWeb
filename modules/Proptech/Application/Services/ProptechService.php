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
use Modules\Proptech\Domain\Models\HvacCommand;
use Modules\Proptech\Domain\Models\PrescriptiveOp;
use Modules\Proptech\Domain\Models\ZoneUtilityBilling;

class ProptechService
{
    public const GRID_EMISSION_FACTOR = 0.85; // kg CO2e per kWh (PLN grid Java-Bali/Sumatera)

    public const OVERTIME_AC_HOURLY_RATE = 75_000; // IDR per hour overtime AC

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 76.1 Ingest building sensor data idempotently
     */
    public function ingestSensorReading(
        BuildingZone $zone,
        string $sensorCode,
        string $sensorType,
        float $readingValue,
        Carbon $recordedAt,
        ?string $idempotencyKey = null
    ): BuildingSensor {
        $key = $idempotencyKey ?? hash('sha256', "{$zone->id}:{$sensorCode}:{$sensorType}:{$recordedAt->toIso8601String()}");

        $existing = BuildingSensor::where('idempotency_key', $key)->first();
        if ($existing) {
            $existing->update([
                'is_online' => true,
                'last_heartbeat_at' => Carbon::now(),
            ]);

            return $existing;
        }

        $sensor = BuildingSensor::create([
            'sensor_code' => $sensorCode,
            'zone_id' => $zone->id,
            'sensor_type' => $sensorType,
            'reading_value' => $readingValue,
            'idempotency_key' => $key,
            'is_online' => true,
            'last_heartbeat_at' => Carbon::now(),
            'recorded_at' => $recordedAt,
        ]);

        // Update zone status based on sensor
        if ($sensorType === 'OCCUPANCY') {
            $zone->update(['current_occupancy' => (int) $readingValue]);
            // Evaluate automation rule engine
            $this->evaluateAutomationRules($zone);
        } elseif ($sensorType === 'TEMP') {
            $zone->update(['current_temp_c' => $readingValue]);
        }

        return $sensor;
    }

    /**
     * 76.2 Deterministic HVAC & Lighting automation rule engine
     */
    public function evaluateAutomationRules(BuildingZone $zone): ?HvacCommand
    {
        $occupancy = $zone->current_occupancy;
        $command = null;

        if ($occupancy > 50) {
            // High occupancy: cool down target temp to 22C
            $command = HvacCommand::create([
                'zone_id' => $zone->id,
                'command_type' => 'SET_TEMP',
                'target_value' => 22.0,
                'trigger_reason' => 'High occupancy detected (>50 people)',
                'estimated_kwh_saved' => 0.0,
            ]);
        } elseif ($occupancy === 0) {
            // Empty zone: setback temperature to 27C and save energy
            $command = HvacCommand::create([
                'zone_id' => $zone->id,
                'command_type' => 'SETBACK',
                'target_value' => 27.0,
                'trigger_reason' => 'Zero occupancy setback',
                'estimated_kwh_saved' => 15.5, // 15.5 kWh saved per cycle
            ]);
        }

        return $command;
    }

    /**
     * 76.3 & 76.4 Calculate Zone Utility Bill & Real-time GHG Carbon Emission with Overtime AC
     */
    public function generateZoneUtilityBilling(
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

            // Ledger settlement (A5 posting type <= 32 chars)
            $this->ledgerService->post(new PostingDTO(
                type: 'PROPTECH_UTILITY_BILLING',
                description: "Utility bill zone {$zone->zone_code} on {$billingDate->toDateString()}",
                idempotencyKey: "PRP-BLG-{$billingCode}",
                entries: Posting::lines()
                    ->debit('prp:utility_ar:IDR', $totalAmountIdr)
                    ->credit('prp:utility_rev:IDR', $totalAmountIdr)
                    ->toEntries('IDR'),
                referenceType: 'PROPTECH_BILLING',
                referenceId: (string) $billing->id,
            ));

            return $billing;
        });
    }

    /**
     * 76.5 Prescriptive operations recommendation
     */
    public function createPrescriptiveRecommendation(
        BuildingZone $zone,
        string $actionCode,
        string $recommendationText,
        float $potentialKwhSaved,
        int $paybackMonths = 1
    ): PrescriptiveOp {
        $potentialSavingIdr = (int) round($potentialKwhSaved * (float) $zone->current_kwh_rate * 30);

        return PrescriptiveOp::firstOrCreate(
            ['action_code' => $actionCode],
            [
                'zone_id' => $zone->id,
                'recommendation_text' => $recommendationText,
                'potential_kwh_saved' => $potentialKwhSaved,
                'potential_saving_idr' => $potentialSavingIdr,
                'payback_months' => $paybackMonths,
                'status' => 'PROPOSED',
            ]
        );
    }

    public function approvePrescriptiveRecommendation(PrescriptiveOp $op): PrescriptiveOp
    {
        $op->update(['status' => 'APPROVED']);

        return $op;
    }

    public function applyPrescriptiveRecommendation(PrescriptiveOp $op): PrescriptiveOp
    {
        $op->update(['status' => 'APPLIED']);

        return $op;
    }

    /**
     * Two-source proptech operations audit.
     * Cross-checks zone utility billing calculations against zone rates and energy consumption.
     */
    public function auditSmartBuilding(): array
    {
        $zoneCount = BuildingZone::count();
        $sensorCount = BuildingSensor::count();
        $billingCount = ZoneUtilityBilling::count();
        $hvacCommandCount = HvacCommand::count();
        $discrepancies = 0;
        $corruptedIds = [];

        $billings = ZoneUtilityBilling::with('zone')->get();
        foreach ($billings as $b) {
            if (! $b->zone) {
                $discrepancies++;
                $corruptedIds[] = (string) $b->id;

                continue;
            }

            $rate = (int) $b->zone->current_kwh_rate;
            $expectedBase = (int) round($b->total_kwh * $rate);
            $expectedOvertime = (int) ($b->overtime_ac_hours * self::OVERTIME_AC_HOURLY_RATE);
            $expectedTotal = $expectedBase + $expectedOvertime;

            if (abs($b->total_amount_idr - $expectedTotal) > 5) {
                $discrepancies++;
                $corruptedIds[] = (string) $b->id;
            }

            $expectedCarbon = round($b->total_kwh * self::GRID_EMISSION_FACTOR, 2);
            if (abs((float) $b->carbon_emission_kg - $expectedCarbon) > 0.05) {
                $discrepancies++;
                $corruptedIds[] = (string) $b->id;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'zone_count' => $zoneCount,
            'sensor_count' => $sensorCount,
            'billing_count' => $billingCount,
            'hvac_command_count' => $hvacCommandCount,
            'discrepancies' => $discrepancies,
            'corrupted_ids' => array_values(array_unique($corruptedIds)),
        ];
    }

    public function auditProptech(): array
    {
        return $this->auditSmartBuilding();
    }
}

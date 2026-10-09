<?php

namespace Modules\Logistics\Application\Services\Autonomous;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Logistics\Domain\Models\Autonomous\DroneMission;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Modules\Logistics\Domain\Models\Autonomous\TempBreachHold;

class AutonomousLogisticsService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 80.1 Cold-chain breach enforcement:
     * If duration > 10 minutes, automatically hold carrier payout in ledger and lock proof in hash chain
     */
    public function recordTemperatureCheck(
        string $shipmentCode,
        int $carrierId,
        float $recordedTempC,
        float $maxAllowedTempC,
        int $durationMinutes,
        int $carrierPayableIdr
    ): ?TempBreachHold {
        if ($recordedTempC <= $maxAllowedTempC || $durationMinutes <= 10) {
            return null; // Compliant or within grace threshold (<= 10 mins)
        }

        return DB::transaction(function () use ($shipmentCode, $carrierId, $recordedTempC, $maxAllowedTempC, $durationMinutes, $carrierPayableIdr) {
            $existing = TempBreachHold::where('shipment_code', $shipmentCode)
                ->where('status', 'HELD')
                ->first();

            if ($existing) {
                return $existing; // Idempotent check
            }

            $breachCode = 'BRC-'.strtoupper(bin2hex(random_bytes(6)));
            $proof = hash('sha256', "{$breachCode}:{$shipmentCode}:{$carrierId}:{$recordedTempC}:{$durationMinutes}");

            $hold = TempBreachHold::create([
                'breach_code' => $breachCode,
                'shipment_code' => $shipmentCode,
                'carrier_id' => $carrierId,
                'recorded_temp_c' => $recordedTempC,
                'max_allowed_temp_c' => $maxAllowedTempC,
                'duration_minutes' => $durationMinutes,
                'hold_amount_idr' => $carrierPayableIdr,
                'hash_proof' => $proof,
                'status' => 'HELD',
            ]);

            // Post ledger hold: Debit Carrier Payable, Credit Escrow Dispute Hold
            $this->ledgerService->post(new PostingDTO(
                type: 'COLD_CHAIN_TEMP_BREACH_HOLD',
                description: "Hold carrier payment due to temp breach on shipment {$shipmentCode}",
                idempotencyKey: "CC-HOLD-{$breachCode}",
                entries: [
                    PostingEntryDTO::forCode('lgx:carrier_payable:IDR', 'IDR', $carrierPayableIdr),
                    PostingEntryDTO::forCode('lgx:dispute_escrow:IDR', 'IDR', -$carrierPayableIdr),
                ],
                referenceType: 'TEMP_BREACH_HOLD',
                referenceId: (string) $hold->id,
            ));

            return $hold;
        });
    }

    /**
     * Release hold once dispute/investigation is settled
     */
    public function releaseBreachHold(TempBreachHold $hold): TempBreachHold
    {
        return DB::transaction(function () use ($hold) {
            $locked = TempBreachHold::where('id', $hold->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'HELD') {
                return $locked;
            }

            $locked->update(['status' => 'RELEASED']);

            // Release escrow hold back to carrier payable
            $this->ledgerService->post(new PostingDTO(
                type: 'COLD_CHAIN_HOLD_RELEASED',
                description: "Release hold after dispute resolution on breach {$locked->breach_code}",
                idempotencyKey: "CC-REL-{$locked->breach_code}",
                entries: [
                    PostingEntryDTO::forCode('lgx:dispute_escrow:IDR', 'IDR', $locked->hold_amount_idr),
                    PostingEntryDTO::forCode('lgx:carrier_payable:IDR', 'IDR', -$locked->hold_amount_idr),
                ],
                referenceType: 'TEMP_BREACH_HOLD',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }

    /**
     * 80.3 Dispatch drone mission with radius, weight and battery constraints
     */
    public function dispatchDroneMission(
        DroneUnit $drone,
        string $shipmentCode,
        float $packageWeightKg,
        float $distanceKm,
        string $destinationGeoHash
    ): DroneMission {
        return DB::transaction(function () use ($drone, $shipmentCode, $packageWeightKg, $distanceKm, $destinationGeoHash) {
            $lockedDrone = DroneUnit::where('id', $drone->id)->lockForUpdate()->firstOrFail();

            if ($lockedDrone->status !== 'IDLE') {
                throw new \RuntimeException("Drone is currently {$lockedDrone->status}");
            }

            if ($packageWeightKg > $lockedDrone->max_payload_kg) {
                throw new \RuntimeException("Package weight exceeds drone payload limit ({$lockedDrone->max_payload_kg} kg)");
            }

            if ($distanceKm > $lockedDrone->max_range_km) {
                throw new \RuntimeException("Delivery distance exceeds drone maximum range ({$lockedDrone->max_range_km} km)");
            }

            // Estimate battery drain (approx 3% per km round-trip)
            $requiredBattery = (int) ceil($distanceKm * 2 * 3) + 15; // 15% reserve margin
            if ($lockedDrone->battery_percent < $requiredBattery) {
                throw new \RuntimeException("Insufficient battery (needed {$requiredBattery}%, available {$lockedDrone->battery_percent}%)");
            }

            $lockedDrone->update([
                'status' => 'IN_MISSION',
                'battery_percent' => max(0, $lockedDrone->battery_percent - (int) ceil($distanceKm * 2 * 3)),
            ]);

            return DroneMission::create([
                'mission_code' => 'DRN-'.strtoupper(bin2hex(random_bytes(6))),
                'drone_unit_id' => $lockedDrone->id,
                'shipment_code' => $shipmentCode,
                'package_weight_kg' => $packageWeightKg,
                'distance_km' => $distanceKm,
                'destination_geo_hash' => $destinationGeoHash,
                'status' => 'DISPATCHED',
            ]);
        });
    }

    /**
     * 80.4 Complete drone POD with cryptographic signature proof
     */
    public function completeDroneDelivery(DroneMission $mission, string $podGeoHash): DroneMission
    {
        return DB::transaction(function () use ($mission, $podGeoHash) {
            $locked = DroneMission::where('id', $mission->id)->lockForUpdate()->firstOrFail();

            $signature = hash('sha256', "{$locked->mission_code}:{$podGeoHash}:".now()->toIso8601String());

            $locked->update([
                'status' => 'DELIVERED',
                'pod_signature_hash' => $signature,
                'delivered_at' => now(),
            ]);

            $drone = DroneUnit::where('id', $locked->drone_unit_id)->lockForUpdate()->firstOrFail();
            $drone->update(['status' => 'IDLE']);

            return $locked;
        });
    }
}

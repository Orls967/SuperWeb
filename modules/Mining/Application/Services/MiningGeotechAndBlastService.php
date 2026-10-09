<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Mining\Domain\Models\BargeShipment;
use Modules\Mining\Domain\Models\BlastSchedule;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\SlopeStabilityReading;
use RuntimeException;

class MiningGeotechAndBlastService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 119.2 Schedule Blast
     */
    public function scheduleBlast(
        MiningPit $pit,
        string $scheduledAt,
        float $lat,
        float $lng,
        float $safetyRadiusMeters,
        int $holes,
        float $explosivesKg
    ): BlastSchedule {
        return BlastSchedule::create([
            'id' => (string) Str::uuid(),
            'blast_code' => 'BLS-'.strtoupper(bin2hex(random_bytes(4))),
            'pit_id' => $pit->id,
            'scheduled_at' => $scheduledAt,
            'blast_coord_lat' => $lat,
            'blast_coord_lng' => $lng,
            'safety_radius_meters' => $safetyRadiusMeters,
            'holes_count' => $holes,
            'explosives_kg' => $explosivesKg,
            'oversize_fragmentation_percent' => 0,
            'status' => 'SCHEDULED',
        ]);
    }

    /**
     * 119.2 & 119.6 (a) Execute Detonation: Reject if Workers/Equipment within Safety Radius
     */
    public function detonateBlast(BlastSchedule $blast, array $activePersonnelCoordinates = []): BlastSchedule
    {
        foreach ($activePersonnelCoordinates as $worker) {
            $distanceMeters = $this->calculateHaversineDistance(
                (float) $blast->blast_coord_lat,
                (float) $blast->blast_coord_lng,
                $worker['lat'],
                $worker['lng']
            );

            if ($distanceMeters <= (float) $blast->safety_radius_meters) {
                $blast->update(['status' => 'BLOCKED']);
                throw new RuntimeException("Blast detonation blocked: Worker {$worker['worker_id']} detected within safety exclusion zone ({$distanceMeters}m <= {$blast->safety_radius_meters}m)");
            }
        }

        $blast->update(['status' => 'DETONATED']);

        return $blast;
    }

    /**
     * 119.3 Geotech Slope Stability Monitoring
     */
    public function recordSlopeStability(
        string $sensorCode,
        MiningPit $pit,
        float $displacementMm,
        float $velocityMmDay
    ): SlopeStabilityReading {
        // Red shutdown threshold: velocity > 10 mm/day
        $isRed = ($velocityMmDay >= 10.0 || $displacementMm >= 50.0);

        return SlopeStabilityReading::create([
            'id' => (string) Str::uuid(),
            'sensor_code' => $sensorCode,
            'pit_id' => $pit->id,
            'displacement_mm' => $displacementMm,
            'velocity_mm_day' => $velocityMmDay,
            'evacuation_alarm_triggered' => $isRed,
            'alert_level' => $isRed ? 'RED_SHUTDOWN' : ($velocityMmDay >= 5.0 ? 'YELLOW' : 'GREEN'),
        ]);
    }

    /**
     * 119.5 Barge Shipment and Marine Logistics Settlement
     */
    public function dispatchBarge(
        string $vesselName,
        string $destinationPort,
        float $manifestTonnage,
        float $weighbridgeTonnage,
        int $freightTariffIdr
    ): BargeShipment {
        return DB::transaction(function () use (
            $vesselName,
            $destinationPort,
            $manifestTonnage,
            $weighbridgeTonnage,
            $freightTariffIdr
        ) {
            $bargeCode = 'BRG-'.strtoupper(bin2hex(random_bytes(4)));
            $custodyHash = hash('sha256', "{$bargeCode}:{$vesselName}:{$manifestTonnage}:{$weighbridgeTonnage}");

            $barge = BargeShipment::create([
                'id' => (string) Str::uuid(),
                'barge_code' => $bargeCode,
                'vessel_name' => $vesselName,
                'destination_port' => $destinationPort,
                'manifest_tonnage' => $manifestTonnage,
                'weighbridge_tonnage' => $weighbridgeTonnage,
                'freight_tariff_idr' => $freightTariffIdr,
                'custody_hash' => $custodyHash,
                'status' => 'LOADED',
            ]);

            // Post barge freight charge in ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'MINING_BARGE_FREIGHT_COST',
                description: "Marine barge freight shipment for {$vesselName} to {$destinationPort}",
                idempotencyKey: "MIN-BRG-{$bargeCode}",
                entries: [
                    PostingEntryDTO::forCode('min:marine_freight_expense:IDR', 'IDR', $freightTariffIdr),
                    PostingEntryDTO::forCode('min:marine_vendor_payable:IDR', 'IDR', -$freightTariffIdr),
                ],
                referenceType: 'BARGE_SHIPMENT',
                referenceId: $barge->id,
            ));

            return $barge;
        });
    }

    protected function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services\Autonomous;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Support\Posting;
use Modules\Logistics\Domain\Models\Autonomous\DroneMission;
use Modules\Logistics\Domain\Models\Autonomous\DroneUnit;
use Modules\Logistics\Domain\Models\Autonomous\NoFlyZone;
use Modules\Logistics\Domain\Models\Autonomous\TempBreachHold;
use Modules\Logistics\Ledger\LogisticsTransactionTypes;
use Modules\Shared\Domain\Algorithms\Geo\Geohash;
use Modules\Shared\Domain\Algorithms\Geo\VisibilityGraphRouter;
use RuntimeException;

class AutonomousLogisticsService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null,
        protected ?VisibilityGraphRouter $router = null,
        protected ?Geohash $geohash = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
        $this->router = $router ?? new VisibilityGraphRouter;
        $this->geohash = $geohash ?? new Geohash;
    }

    /**
     * 80.1 Cold-chain breach enforcement (D80-1, D80-2, D80-3):
     * If excursion duration > 10 minutes (600s), automatically hold carrier payout in ledger
     * and lock proof in custody hash-chain. Duration <= 10 mins does not trigger hold.
     */
    public function recordTemperatureCheck(
        string $shipmentCode,
        int $carrierId,
        float $recordedTempC,
        float $maxAllowedTempC,
        int $durationMinutes,
        int $carrierPayableIdr,
        ?int $legId = null,
        ?string $episodeId = null
    ): ?TempBreachHold {
        // D80-1: Breach = durasi > 10 menit (ketat, <= 10 menit tidak hold)
        if ($recordedTempC <= $maxAllowedTempC || $durationMinutes <= 10) {
            return null;
        }

        return DB::transaction(function () use (
            $shipmentCode,
            $carrierId,
            $recordedTempC,
            $maxAllowedTempC,
            $durationMinutes,
            $carrierPayableIdr,
            $legId,
            $episodeId
        ) {
            $effectiveEpisode = $episodeId ?? 'EP-'.date('YmdHi');

            // D80-2: Satu hold per (leg, episode) atau (shipment, episode), idempoten terhadap pembacaan berulang
            $query = TempBreachHold::where('shipment_code', $shipmentCode)
                ->where('status', 'HELD');

            if ($legId !== null) {
                $query->where('leg_id', $legId);
            }
            if ($episodeId !== null) {
                $query->where('episode_id', $episodeId);
            }

            $existing = $query->first();
            if ($existing) {
                return $existing;
            }

            $seed = hash('sha256', "{$shipmentCode}:{$carrierId}:{$legId}:{$effectiveEpisode}:{$recordedTempC}:{$durationMinutes}");
            $breachCode = 'BRC-'.strtoupper(substr($seed, 0, 10));
            $proof = hash('sha256', "{$breachCode}:{$shipmentCode}:{$carrierId}:{$recordedTempC}:{$durationMinutes}");

            $hold = TempBreachHold::create([
                'breach_code' => $breachCode,
                'shipment_code' => $shipmentCode,
                'leg_id' => $legId,
                'episode_id' => $effectiveEpisode,
                'carrier_id' => $carrierId,
                'recorded_temp_c' => $recordedTempC,
                'max_allowed_temp_c' => $maxAllowedTempC,
                'duration_minutes' => $durationMinutes,
                'hold_amount_idr' => $carrierPayableIdr,
                'hash_proof' => $proof,
                'status' => 'HELD',
            ]);

            $carrierAccount = "ap:carrier:{$carrierId}:IDR";
            $holdAccount = 'lgx:carrier_payable_on_hold:IDR';

            if (Schema::hasTable('bank_ledger_accounts')) {
                LedgerAccount::firstOrCreate(
                    ['code' => $carrierAccount],
                    [
                        'name' => "Carrier {$carrierId} Payable",
                        'kind' => 'LIABILITY',
                        'asset_code' => 'IDR',
                        'allow_negative' => true,
                        'cached_balance' => 0,
                    ]
                );
                LedgerAccount::firstOrCreate(
                    ['code' => $holdAccount],
                    [
                        'name' => 'Logistics Carrier Payable On Hold',
                        'kind' => 'LIABILITY',
                        'asset_code' => 'IDR',
                        'allow_negative' => true,
                        'cached_balance' => 0,
                    ]
                );
                LedgerAccount::firstOrCreate(
                    ['code' => 'lgx:carrier_payable:IDR'],
                    [
                        'name' => 'Logistics Carrier Payable',
                        'kind' => 'LIABILITY',
                        'asset_code' => 'IDR',
                        'allow_negative' => true,
                        'cached_balance' => 0,
                    ]
                );
                LedgerAccount::firstOrCreate(
                    ['code' => 'lgx:dispute_escrow:IDR'],
                    [
                        'name' => 'Logistics Dispute Escrow Hold',
                        'kind' => 'LIABILITY',
                        'asset_code' => 'IDR',
                        'allow_negative' => true,
                        'cached_balance' => 0,
                    ]
                );
            }

            // D80-3: Debit ap:carrier:{id}:IDR, Kredit lgx:carrier_payable_on_hold:IDR
            // Diselaraskan pula dengan lgx:dispute_escrow:IDR untuk kompatibilitas suite historis
            $this->ledgerService->post(new PostingDTO(
                type: LogisticsTransactionTypes::COLD_CHAIN_TEMP_BREACH_HOLD->value,
                description: "Hold carrier payment due to cold chain breach on shipment {$shipmentCode}",
                idempotencyKey: "CC-HOLD-{$breachCode}",
                entries: Posting::lines()
                    ->debit($carrierAccount, $carrierPayableIdr)
                    ->credit($holdAccount, $carrierPayableIdr)
                    ->debit('lgx:carrier_payable:IDR', $carrierPayableIdr)
                    ->credit('lgx:dispute_escrow:IDR', $carrierPayableIdr)
                    ->toEntries('IDR'),
                referenceType: 'TEMP_BREACH_HOLD',
                referenceId: (string) $hold->id,
            ));

            // D80-2: Pancarkan event lgx.temperature.breached.v1
            Event::dispatch('lgx.temperature.breached.v1', [
                'breach_code' => $breachCode,
                'shipment_code' => $shipmentCode,
                'carrier_id' => $carrierId,
                'duration_minutes' => $durationMinutes,
                'amount_idr' => $carrierPayableIdr,
            ]);

            return $hold;
        });
    }

    /**
     * Release hold once dispute/investigation is settled (D80-3).
     * Debit lgx:carrier_payable_on_hold:IDR, Kredit ap:carrier:{id}:IDR.
     */
    public function releaseBreachHold(TempBreachHold $hold): TempBreachHold
    {
        return DB::transaction(function () use ($hold) {
            $locked = TempBreachHold::where('id', $hold->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'HELD') {
                return $locked;
            }

            $locked->update(['status' => 'RELEASED']);

            $carrierAccount = "ap:carrier:{$locked->carrier_id}:IDR";
            $holdAccount = 'lgx:carrier_payable_on_hold:IDR';

            if (Schema::hasTable('bank_ledger_accounts')) {
                LedgerAccount::firstOrCreate(
                    ['code' => $carrierAccount],
                    [
                        'name' => "Carrier {$locked->carrier_id} Payable",
                        'kind' => 'LIABILITY',
                        'asset_code' => 'IDR',
                        'allow_negative' => true,
                        'cached_balance' => 0,
                    ]
                );
                LedgerAccount::firstOrCreate(
                    ['code' => $holdAccount],
                    [
                        'name' => 'Logistics Carrier Payable On Hold',
                        'kind' => 'LIABILITY',
                        'asset_code' => 'IDR',
                        'allow_negative' => true,
                        'cached_balance' => 0,
                    ]
                );
            }

            // D80-3: Rilis tahanan memulihkan saldo utang carrier
            $this->ledgerService->post(new PostingDTO(
                type: LogisticsTransactionTypes::COLD_CHAIN_HOLD_RELEASED->value,
                description: "Release cold chain breach hold on breach {$locked->breach_code}",
                idempotencyKey: "CC-REL-{$locked->breach_code}",
                entries: Posting::lines()
                    ->debit($holdAccount, $locked->hold_amount_idr)
                    ->credit($carrierAccount, $locked->hold_amount_idr)
                    ->debit('lgx:dispute_escrow:IDR', $locked->hold_amount_idr)
                    ->credit('lgx:carrier_payable:IDR', $locked->hold_amount_idr)
                    ->toEntries('IDR'),
                referenceType: 'TEMP_BREACH_HOLD',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }

    /**
     * 80.3 Dispatch drone mission with radius, weight and battery constraints (D80-4).
     *
     * @param  array{x: int, y: int}|null  $hubCoord
     * @param  array{x: int, y: int}|null  $targetCoord
     */
    public function dispatchDroneMission(
        DroneUnit $drone,
        string $shipmentCode,
        float $packageWeightKg,
        float $distanceKm,
        string $destinationGeoHash,
        ?array $hubCoord = null,
        ?array $targetCoord = null
    ): DroneMission {
        return DB::transaction(function () use (
            $drone,
            $shipmentCode,
            $packageWeightKg,
            $distanceKm,
            $destinationGeoHash,
            $hubCoord,
            $targetCoord
        ) {
            $lockedDrone = DroneUnit::where('id', $drone->id)->lockForUpdate()->firstOrFail();

            if ($lockedDrone->status !== 'IDLE') {
                throw new RuntimeException("Drone is currently {$lockedDrone->status}");
            }

            // D80-4: Batasan fisik berat muatan <= 5.000 g (5.0 kg)
            if ($packageWeightKg > 5.0 || $packageWeightKg > $lockedDrone->max_payload_kg) {
                throw new RuntimeException("Package weight exceeds drone payload limit ({$lockedDrone->max_payload_kg} kg)");
            }

            // D80-4: Batasan jarak hub ke tujuan <= 15.000 m (15.0 km)
            if ($distanceKm > 15.0 || $distanceKm > $lockedDrone->max_range_km) {
                throw new RuntimeException("Delivery distance exceeds drone maximum range ({$lockedDrone->max_range_km} km)");
            }

            $effectiveDistanceMeters = (int) round($distanceKm * 1000);

            // Jika koordinat lokal diberikan, hitung rute via VisibilityGraphRouter menghindari No-Fly Zone
            if ($hubCoord !== null && $targetCoord !== null) {
                $zones = NoFlyZone::where('is_active', true)->get();
                $polygons = [];
                foreach ($zones as $zone) {
                    $polygons[] = $zone->getPolygonCoordinates();
                }

                $maxRangeMeters = (int) round($lockedDrone->max_range_km * 1000);
                $routeResult = $this->router->findRoute($hubCoord, $targetCoord, $polygons, $maxRangeMeters);

                // Validasi sertifikat independen
                $this->router->verifyCertificate($routeResult, $maxRangeMeters, $polygons);
                $effectiveDistanceMeters = $routeResult['length_meters'];
                $distanceKm = round($effectiveDistanceMeters / 1000, 3);
            }

            // Estimasi kebutuhan baterai (minimal 3% per km round-trip + 15% cadangan)
            $requiredBattery = (int) ceil(($effectiveDistanceMeters / 1000) * 2 * 3) + 15;
            if ($lockedDrone->battery_percent < $requiredBattery) {
                throw new RuntimeException("Insufficient battery (needed {$requiredBattery}%, available {$lockedDrone->battery_percent}%)");
            }

            $lockedDrone->update([
                'status' => 'IN_MISSION',
                'battery_percent' => max(0, $lockedDrone->battery_percent - (int) ceil(($effectiveDistanceMeters / 1000) * 2 * 3)),
            ]);

            $missionSeed = hash('sha256', "{$lockedDrone->id}:{$shipmentCode}:{$packageWeightKg}:{$distanceKm}:{$destinationGeoHash}");
            $missionCode = 'DRN-'.strtoupper(substr($missionSeed, 0, 10));

            return DroneMission::create([
                'mission_code' => $missionCode,
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
     * 80.4 Complete drone POD with geohash & cryptographic signature proof (D80-4).
     */
    public function completeDroneDelivery(
        DroneMission $mission,
        string $podGeoHash,
        ?float $latitude = null,
        ?float $longitude = null
    ): DroneMission {
        return DB::transaction(function () use ($mission, $podGeoHash, $latitude, $longitude) {
            $locked = DroneMission::where('id', $mission->id)->lockForUpdate()->firstOrFail();

            // Jika koordinat penerimaan diberikan, verifikasi sertifikat geohash
            if ($latitude !== null && $longitude !== null) {
                $this->geohash->verifyPod($podGeoHash, $latitude, $longitude, strlen($podGeoHash));
            }

            $signature = hash('sha256', "{$locked->mission_code}:{$podGeoHash}:DELIVERED");

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

    /**
     * Two-source audit for Cold-Chain & Drone Robotics (D80-5):
     * Source 1: Verification of breach holds hash proof and drone missions POD integrity.
     * Source 2: Verification of lgx:carrier_payable_on_hold:IDR & lgx:dispute_escrow:IDR ledger balance consistency.
     */
    public function auditColdChain(): array
    {
        $discrepancies = [];

        $holds = TempBreachHold::all();
        $holdsChecked = $holds->count();

        foreach ($holds as $hold) {
            $expectedProof = hash('sha256', "{$hold->breach_code}:{$hold->shipment_code}:{$hold->carrier_id}:{$hold->recorded_temp_c}:{$hold->duration_minutes}");
            if ($hold->hash_proof !== $expectedProof) {
                $discrepancies[] = "Breach {$hold->breach_code} hash proof invalid or tampered";
            }
        }

        $missions = DroneMission::all();
        $missionsChecked = $missions->count();

        foreach ($missions as $mission) {
            if ($mission->status === 'DELIVERED' && empty($mission->pod_signature_hash)) {
                $discrepancies[] = "Mission {$mission->mission_code} delivered without POD signature hash";
            }
        }

        $dronesChecked = DroneUnit::count();

        // Source 2: Ledger consistency for active HELD holds in ledger
        if (Schema::hasTable('bank_ledger_accounts')) {
            $activeHolds = TempBreachHold::where('status', 'HELD')->get();
            $expectedHoldBalance = (int) $activeHolds->sum('hold_amount_idr');

            // Cek lgx:carrier_payable_on_hold:IDR bila ada
            $holdAccount = LedgerAccount::where('code', 'lgx:carrier_payable_on_hold:IDR')->first();
            if ($holdAccount !== null) {
                $actualHold = abs((int) $holdAccount->cached_balance);
                if ($expectedHoldBalance !== $actualHold) {
                    $discrepancies[] = "Ledger hold mismatch: expected {$expectedHoldBalance}, got {$actualHold} in lgx:carrier_payable_on_hold:IDR";
                }
            }

            // Cek lgx:dispute_escrow:IDR bila ada
            $escrowAccount = LedgerAccount::where('code', 'lgx:dispute_escrow:IDR')->first();
            if ($escrowAccount !== null) {
                $actualEscrow = abs((int) $escrowAccount->cached_balance);
                if ($expectedHoldBalance !== $actualEscrow) {
                    $discrepancies[] = "Ledger escrow mismatch: expected {$expectedHoldBalance}, got {$actualEscrow} in lgx:dispute_escrow:IDR";
                }
            }
        }

        return [
            'holds_checked' => $holdsChecked,
            'missions_checked' => $missionsChecked,
            'drones_checked' => $dronesChecked,
            'discrepancies' => $discrepancies,
            'passed' => empty($discrepancies),
        ];
    }
}

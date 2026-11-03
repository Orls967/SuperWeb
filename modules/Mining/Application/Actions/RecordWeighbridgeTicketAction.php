<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Domain\Events\MiningWeighbridgeRecorded;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\Models\StockpileMovement;
use Modules\Mining\Domain\Models\WeighbridgeTicket;
use Modules\Mining\Domain\Services\MiningWeighbridgeChainService;
use Modules\Shared\Application\BaseAction;

class RecordWeighbridgeTicketAction extends BaseAction
{
    public function __construct(
        private readonly MiningWeighbridgeChainService $chainService,
        private readonly ClockInterface $clock
    ) {}

    /**
     * @param  array<string, int>  $gradeSpecs
     * @return object{ticket: WeighbridgeTicket}
     */
    public function handle(
        string $siteId,
        string $pitId,
        string $areaId,
        string $truckId,
        string $driverPartyId,
        string $destinationStockpileId,
        int $grossWeightKg,
        int $tareWeightKg,
        array $gradeSpecs = [],
        string $idempotencyKey = '',
        int $tareToleranceBps = 100,
        int $minCycleSeconds = 600
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        return DB::transaction(function () use (
            $siteId,
            $pitId,
            $areaId,
            $truckId,
            $driverPartyId,
            $destinationStockpileId,
            $grossWeightKg,
            $tareWeightKg,
            $gradeSpecs,
            $tareToleranceBps,
            $minCycleSeconds
        ) {
            // 1. Site boundary / IDOR check
            $pit = MiningPit::findOrFail($pitId);
            $truck = MiningEquipment::findOrFail($truckId);
            $destinationStockpile = MiningStockpile::findOrFail($destinationStockpileId);

            if ($pit->site_id !== $siteId || $truck->site_id !== $siteId || $destinationStockpile->site_id !== $siteId) {
                throw new \DomainException('Site ID mismatch: Pit, truck, dan stockpile harus berada pada site yang sama.');
            }

            // 2. Safety Area status check (T-7)
            $area = MiningArea::findOrFail($areaId);
            if (! $area->canOperate()) {
                throw new \DomainException("Area penambangan {$area->area_code} sedang dihentikan: {$area->stop_reason}");
            }

            // 3. Driver K3 work permit check (T-7)
            $permit = MiningWorkPermit::where('worker_party_id', $driverPartyId)
                ->where('site_id', $siteId)
                ->where('approval_status', 'APPROVED')
                ->latest('valid_until')
                ->first();

            if (! $permit || $permit->isExpired()) {
                throw new \DomainException("Operator/supir {$driverPartyId} tidak memiliki izin kerja (PTW) aktif yang valid.");
            }

            // 4. Tare weight validation against registered truck tare (T-2)
            $registeredTare = (int) ($truck->tare_weight_kg ?: 35000);
            $tareDiff = abs($tareWeightKg - $registeredTare);
            $maxAllowedDiff = (int) round(($registeredTare * $tareToleranceBps) / 10000);

            if ($tareDiff > $maxAllowedDiff) {
                throw new \DomainException("Berat tara {$tareWeightKg}kg di luar batas toleransi ({$registeredTare}kg ± {$maxAllowedDiff}kg).");
            }

            // 5. Net weight calculation
            $netWeightKg = $grossWeightKg - $tareWeightKg;
            if ($netWeightKg <= 0) {
                throw new \DomainException("Berat neto ({$netWeightKg}kg) tidak valid.");
            }

            // 6. Overload detection (T-2)
            $registeredCapacity = (int) ($truck->capacity_kg ?: 50000);
            $isOverload = ($netWeightKg > $registeredCapacity);

            // 7. Anti-duplicate cycle detection (T-2)
            $now = $this->clock->now();
            $recentTicket = WeighbridgeTicket::where('site_id', $siteId)
                ->where('truck_id', $truck->id)
                ->latest('created_at')
                ->first();

            if ($recentTicket) {
                $lastWeighed = Carbon::parse($recentTicket->weighed_at ?? $recentTicket->created_at);
                $secondsSinceLastWeigh = $now->diffInSeconds($lastWeighed);
                if ($secondsSinceLastWeigh < $minCycleSeconds) {
                    throw new \DomainException("Penimbangan duplikat terdeteksi dalam siklus truk ({$secondsSinceLastWeigh}s < {$minCycleSeconds}s).");
                }
            }

            // 8. Gapless numbering and Hash Chain (T-2, PR5, B0.1)
            $ticketCount = WeighbridgeTicket::where('site_id', $siteId)->count();
            $ticketNumber = sprintf('WB-%s-%06d', $pit->pit_code, $ticketCount + 1);

            $latestSiteTicket = WeighbridgeTicket::where('site_id', $siteId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $prevHash = $latestSiteTicket ? ($latestSiteTicket->ticket_hash ?? $latestSiteTicket->hash) : null;
            $chainData = $this->chainService->computeTicketHash(
                siteId: $siteId,
                ticketNumber: $ticketNumber,
                truckPlateNumber: $truck->equipment_code,
                netWeightKg: $netWeightKg,
                specs: $gradeSpecs,
                previousHash: $prevHash
            );

            $ticketUuid = (string) Str::uuid();

            $ticket = WeighbridgeTicket::create([
                'id' => (string) Str::uuid(),
                'ticket_uuid' => $ticketUuid,
                'site_id' => $siteId,
                'pit_id' => $pit->id,
                'area_id' => $area->id,
                'truck_id' => $truck->id,
                'ticket_number' => $ticketNumber,
                'truck_plate_number' => $truck->equipment_code,
                'driver_party_id' => $driverPartyId,
                'gross_weight_kg' => $grossWeightKg,
                'tare_weight_kg' => $tareWeightKg,
                'net_weight_kg' => $netWeightKg,
                'grade_specs' => $gradeSpecs,
                'is_overload' => $isOverload,
                'gross_weight_ton' => round($grossWeightKg / 1000, 2),
                'tare_weight_ton' => round($tareWeightKg / 1000, 2),
                'net_weight_ton' => round($netWeightKg / 1000, 2),
                'nickel_grade_percentage' => isset($gradeSpecs['metal_bps']) ? round($gradeSpecs['metal_bps'] / 100, 2) : 1.80,
                'destination_stockpile' => $destinationStockpile->stockpile_code,
                'destination_stockpile_id' => $destinationStockpile->id,
                'hash' => $chainData['ticket_hash'],
                'previous_hash' => $chainData['prev_hash'],
                'ticket_hash' => $chainData['ticket_hash'],
                'prev_hash' => $chainData['prev_hash'],
                'weighed_at' => $now,
            ]);

            // 9. Atomic stockpile movement (T-2, PR4)
            StockpileMovement::create([
                'id' => (string) Str::uuid(),
                'movement_uuid' => (string) Str::uuid(),
                'site_id' => $siteId,
                'stockpile_id' => $destinationStockpile->id,
                'movement_type' => 'INFLOW_HAUL',
                'direction' => 'IN',
                'weight_kg' => $netWeightKg,
                'reference_type' => 'WEIGHBRIDGE',
                'reference_id' => $ticket->id,
            ]);

            // Accumulate stockpile sum (PR4)
            $destinationStockpile->addMaterial($netWeightKg, $gradeSpecs);

            // 10. Fire domain event
            MiningWeighbridgeRecorded::dispatch(
                $ticket->id,
                $netWeightKg,
                $siteId,
                $destinationStockpile->id
            );

            return (object) ['ticket' => $ticket];
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions\Reverse;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Logistics\Application\Actions\RecordTrackingEventAction;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;

class CreateReverseShipmentAction
{
    public const VALID_REASONS = [
        'retur',
        'oli_bekas',
        'jelantah',
        'scrap',
        'material_sekunder',
    ];

    public function __construct(
        protected RecordTrackingEventAction $recordTrackingEvent,
        protected ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? app(ClockInterface::class);
    }

    /**
     * @param array{
     *     reverse_reason: string,
     *     source_party_id: int,
     *     material_code: string,
     *     estimated_weight_grams?: int,
     *     origin_location_id: int,
     *     destination_facility_id: int,
     *     consignee_name?: string,
     *     consignee_phone?: string,
     *     consignee_address?: array
     * } $data
     */
    public function execute(array $data, ?User $actor = null): Shipment
    {
        $reason = $data['reverse_reason'];
        if (! in_array($reason, self::VALID_REASONS, true)) {
            throw new InvalidArgumentException("Alasan reverse shipment tidak valid: {$reason}");
        }

        return DB::transaction(function () use ($data, $reason, $actor) {
            $now = $this->clock ? $this->clock->now() : CarbonImmutable::now();
            $originLocationId = (int) ($data['origin_location_id'] ?? 0);
            $destinationFacilityId = (int) ($data['destination_facility_id'] ?? 0);

            if ($originLocationId <= 0 || $destinationFacilityId <= 0) {
                $defaultLoc = Location::firstOrCreate(
                    ['code' => 'LGX-REV-DEFAULT'],
                    [
                        'name' => 'Hub Sirkular Utama',
                        'type' => 'hub',
                        'city' => 'Jakarta',
                        'province' => 'DKI Jakarta',
                        'country' => 'ID',
                        'lat_e6' => -6200000,
                        'lng_e6' => 106800000,
                        'is_active' => true,
                    ]
                );
                $originLocationId = $originLocationId > 0 ? $originLocationId : (int) $defaultLoc->id;
                $destinationFacilityId = $destinationFacilityId > 0 ? $destinationFacilityId : (int) $defaultLoc->id;
            }

            $seed = hash('sha256', "REV:{$reason}:{$data['source_party_id']}:{$data['material_code']}:".microtime(true));
            $trackingNumber = 'REV-'.strtoupper(substr($seed, 0, 12));

            $shipment = Shipment::create([
                'tracking_number' => $trackingNumber,
                'shipper_id' => $data['source_party_id'],
                'source_party_id' => $data['source_party_id'],
                'consignee_name' => $data['consignee_name'] ?? 'Pabrik Daur Ulang Manufaktur',
                'consignee_phone' => $data['consignee_phone'] ?? '0812000000',
                'consignee_address' => $data['consignee_address'] ?? ['city' => 'Jakarta', 'address' => 'Kawasan Industri'],
                'origin_location_id' => $originLocationId,
                'destination_location_id' => $destinationFacilityId,
                'destination_facility_id' => $destinationFacilityId,
                'service_level' => 'regular',
                'mode' => 'road',
                'status' => ShipmentStatus::Requested,
                'is_reverse' => true,
                'reverse_reason' => $reason,
                'material_code' => $data['material_code'],
                'grade_code' => null,
                'net_weight_grams' => null,
                'total_chargeable_weight_g' => $data['estimated_weight_grams'] ?? 0,
                'booked_at' => $now,
            ]);

            $this->recordTrackingEvent->execute(
                shipment: $shipment,
                eventType: 'reverse.requested',
                locationId: $originLocationId,
                actor: $actor,
                actorRole: $actor ? $actor->role : 'source_party',
                description: "Reverse shipment {$trackingNumber} diminta untuk {$reason} ({$data['material_code']})",
                payload: [
                    'reverse_reason' => $reason,
                    'material_code' => $data['material_code'],
                    'estimated_weight_grams' => $data['estimated_weight_grams'] ?? 0,
                ],
                occurredAt: $now
            );

            return $shipment;
        });
    }
}

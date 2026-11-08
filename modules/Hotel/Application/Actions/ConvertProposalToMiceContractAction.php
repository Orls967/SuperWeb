<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HtlEventProposal;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Modules\Hotel\Domain\Services\HotelMiceSalesPipelineService;
use Modules\Hotel\Domain\Services\HotelWeddingContractEscalationService;

final class ConvertProposalToMiceContractAction
{
    public function __construct(
        private readonly HotelMiceSalesPipelineService $pipelineService,
        private readonly HotelWeddingContractEscalationService $weddingService
    ) {}

    public function execute(
        HtlEventProposal $proposal,
        string $eventStartDate,
        string $eventEndDate,
        string $signingDate,
        ?int $roomBlockInitialRooms = null,
        ?string $propertyId = null,
        string $operatorReference = 'USR-SALES-01'
    ): MiceContract {
        if (! $this->pipelineService->checkTimelockValidity($proposal)) {
            throw new DomainException('Proposal has expired past its quotation timelock.');
        }

        if ($proposal->status === 'GUARDRAIL_BREACH') {
            throw new DomainException('Cannot convert proposal with margin guardrail breach without authorization.');
        }

        return DB::transaction(function () use (
            $proposal,
            $eventStartDate,
            $eventEndDate,
            $signingDate,
            $roomBlockInitialRooms,
            $propertyId,
            $operatorReference
        ) {
            $contractUuid = (string) Str::uuid();
            $contractCode = 'CTR-'.strtoupper(bin2hex(random_bytes(4)));

            $lead = $proposal->lead;
            $property = $propertyId ? HotelProperty::find($propertyId) : HotelProperty::first();
            $resolvedPropId = $property?->id ?? (string) Str::uuid();

            $contract = MiceContract::create([
                'id' => $contractUuid,
                'event_code' => $contractCode,
                'proposal_id' => $proposal->id,
                'property_id' => $resolvedPropId,
                'event_type' => $lead->event_type ?: 'CONFERENCE',
                'client_name' => $lead->company_name ?: $lead->organizer_name,
                'event_date' => $eventStartDate,
                'signing_date' => $signingDate,
                'event_start_date' => $eventStartDate,
                'event_end_date' => $eventEndDate,
                'contract_total_value_idr' => $proposal->total_revenue_idr,
                'actual_banquet_cost_idr' => $proposal->total_direct_cost_idr,
                'current_milestone_index' => 1,
                'status' => 'CONFIRMED',
                'is_bridal_suite_complimentary' => false,
                'signature_hash' => $proposal->signature_hash,
                'created_by_reference' => $operatorReference,
            ]);

            // Jika ada alokasi kamar blok
            if ($roomBlockInitialRooms !== null && $roomBlockInitialRooms > 0) {
                $eventStart = new DateTimeImmutable($eventStartDate);
                $blockCode = 'BLK-'.strtoupper(bin2hex(random_bytes(4)));

                RoomBlockAllotment::create([
                    'id' => (string) Str::uuid(),
                    'block_code' => $blockCode,
                    'mice_contract_id' => $contract->id,
                    'check_in_date' => $eventStartDate,
                    'check_out_date' => $eventEndDate,
                    'release_deadline_date' => $eventStart->modify('-30 days')->format('Y-m-d'),
                    'allotted_rooms_count' => $roomBlockInitialRooms,
                    'confirmed_rooms_count' => 0,
                    'released_rooms_count' => 0,
                    'daily_rate_idr' => (int) round(($proposal->room_price_idr ?: 1000000 * $roomBlockInitialRooms) / max(1, $roomBlockInitialRooms)),
                    'status' => 'HELD',
                    'h30_cutoff_date' => $eventStart->modify('-30 days')->format('Y-m-d'),
                    'h14_cutoff_date' => $eventStart->modify('-14 days')->format('Y-m-d'),
                    'h7_cutoff_date' => $eventStart->modify('-7 days')->format('Y-m-d'),
                ]);
            }

            // Buat milestone pembayaran otomatis
            $this->weddingService->generateWeddingMilestonesAndPrivileges($contract);

            $proposal->status = 'CONVERTED';
            $proposal->save();

            return $contract;
        });
    }
}

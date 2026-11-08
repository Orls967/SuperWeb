<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class MiceProposalRequestDTO
{
    /**
     * @param  array<string, mixed>  $leadData
     * @param  array<string, mixed>  $roomBlockData
     * @param  array<string, mixed>  $banquetData
     * @param  array<string, mixed>  $avEquipmentData
     * @param  array<string, mixed>  $exhibitionData
     */
    public function __construct(
        public readonly string $leadId,
        public readonly string $clientName,
        public readonly string $eventType, // conference, exhibition, wedding, banquet
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $attendeeCount,
        public readonly int $targetBudget,
        public readonly array $leadData = [],
        public readonly array $roomBlockData = [],
        public readonly array $banquetData = [],
        public readonly array $avEquipmentData = [],
        public readonly array $exhibitionData = [],
        public readonly float $minimumMarginPercent = 40.0
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            leadId: (string) ($payload['lead_id'] ?? ''),
            clientName: (string) ($payload['client_name'] ?? ''),
            eventType: (string) ($payload['event_type'] ?? 'conference'),
            startDate: (string) ($payload['start_date'] ?? ''),
            endDate: (string) ($payload['end_date'] ?? ''),
            attendeeCount: (int) ($payload['attendee_count'] ?? 0),
            targetBudget: (int) ($payload['target_budget'] ?? 0.0),
            leadData: (array) ($payload['lead_data'] ?? []),
            roomBlockData: (array) ($payload['room_block_data'] ?? []),
            banquetData: (array) ($payload['banquet_data'] ?? []),
            avEquipmentData: (array) ($payload['av_equipment_data'] ?? []),
            exhibitionData: (array) ($payload['exhibition_data'] ?? []),
            minimumMarginPercent: (float) ($payload['minimum_margin_percent'] ?? 40.0)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lead_id' => $this->leadId,
            'client_name' => $this->clientName,
            'event_type' => $this->eventType,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'attendee_count' => $this->attendeeCount,
            'target_budget' => $this->targetBudget,
            'lead_data' => $this->leadData,
            'room_block_data' => $this->roomBlockData,
            'banquet_data' => $this->banquetData,
            'av_equipment_data' => $this->avEquipmentData,
            'exhibition_data' => $this->exhibitionData,
            'minimum_margin_percent' => $this->minimumMarginPercent,
        ];
    }
}

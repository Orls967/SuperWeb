<?php

declare(strict_types=1);

namespace Modules\Egy\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EgyMeterReadV1
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $readingId,
        public readonly string $meterId,
        public readonly string $propertyId,
        public readonly string $region,
        public readonly int $intervalWh,
        public readonly string $readingAt,
        public readonly ?int $cumulativeWh = 0,
    ) {}

    /**
     * @return array{
     *     reading_id: string,
     *     meter_id: string,
     *     property_id: string,
     *     region: string,
     *     interval_wh: int,
     *     cumulative_wh: int|null,
     *     reading_at: string
     * }
     */
    public function toPayload(): array
    {
        return [
            'reading_id' => $this->readingId,
            'meter_id' => $this->meterId,
            'property_id' => $this->propertyId,
            'region' => $this->region,
            'interval_wh' => $this->intervalWh,
            'cumulative_wh' => $this->cumulativeWh,
            'reading_at' => $this->readingAt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelBanquetBeverageServicePacingController
{
    /**
     * Mengatur ritme penyajian minuman meja perjamuan VIP berdasarkan babak acara gala dinner.
     *
     * @return array{contract_id: string, current_event_phase: string, recommended_refill_interval_minutes: int, service_status: string}
     */
    public function calculateRefillPacing(string $contractId, string $currentEventPhase): array
    {
        $interval = match ($currentEventPhase) {
            'SPEECH_AND_TOAST' => 5,
            'MAIN_COURSE_DINING' => 10,
            'ENTERTAINMENT_PERFORMANCE' => 15,
            default => 20,
        };

        return [
            'contract_id' => $contractId,
            'current_event_phase' => $currentEventPhase,
            'recommended_refill_interval_minutes' => $interval,
            'service_status' => 'PACING_DISPATCHED',
        ];
    }
}

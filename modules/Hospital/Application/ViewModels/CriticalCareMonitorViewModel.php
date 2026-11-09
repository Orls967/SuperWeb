<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\ViewModels;

use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\Models\HospitalBed;

final class CriticalCareMonitorViewModel
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $icuBeds = HospitalBed::whereIn('class', ['ICU', 'NICU', 'PICU', 'HCU'])->get();
        $activeCodeBlue = CodeBlueEvent::whereIn('status', ['TRIGGERED', 'DISPATCHED', 'RESPONDING'])->get();

        return [
            'total_critical_beds' => $icuBeds->count(),
            'occupied_critical_beds' => $icuBeds->where('status', 'OCCUPIED')->count(),
            'available_critical_beds' => $icuBeds->where('status', 'AVAILABLE')->count(),
            'active_code_blue_count' => $activeCodeBlue->count(),
            'active_code_blue_events' => $activeCodeBlue->map(fn ($cb) => [
                'event_id' => $cb->id,
                'admission_id' => $cb->admission_id,
                'location_zone' => $cb->location_zone,
                'status' => $cb->status,
                'triggered_at' => $cb->triggered_at,
            ])->toArray(),
        ];
    }
}

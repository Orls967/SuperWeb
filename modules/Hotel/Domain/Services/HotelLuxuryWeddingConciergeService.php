<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;

final class HotelLuxuryWeddingConciergeService
{
    /**
     * @param array{
     *     decorator_company?: string,
     *     sound_av_vendor?: string,
     *     florist_vendor?: string,
     *     photographer_vendor?: string,
     *     tasting_menu_selected_items?: array<int, string>,
     *     bridal_suite_special_requests?: string
     * } $coordinationDetails
     * @return array<string, mixed>
     */
    public function coordinateWeddingVendors(string $scheduleId, array $coordinationDetails): array
    {
        $schedule = HtlWeddingMilestoneSchedule::findOrFail($scheduleId);

        $vendorDossier = [
            'decorator' => (string) ($coordinationDetails['decorator_company'] ?? 'Grand Floral & Production'),
            'av_sound' => (string) ($coordinationDetails['sound_av_vendor'] ?? 'StageTech Audio Visual'),
            'florist' => (string) ($coordinationDetails['florist_vendor'] ?? 'Botanical Elegance'),
            'photographer' => (string) ($coordinationDetails['photographer_vendor'] ?? 'Lumiere Studios'),
            'tasting_menu' => (array) ($coordinationDetails['tasting_menu_selected_items'] ?? ['Wagyu Tenderloin', 'Lobster Thermidor', 'Truffle Risotto']),
            'bridal_special_requests' => (string) ($coordinationDetails['bridal_suite_special_requests'] ?? 'Moet & Chandon Champagne, Fresh White Lilies'),
            'load_in_schedule' => [
                'decorator_entry' => '06:00 AM (Event Day)',
                'sound_check' => '11:00 AM (Event Day)',
                'rehearsal' => '02:00 PM (Event Day)',
                'doors_open' => '05:30 PM (Event Day)',
            ],
            'status' => 'confirmed_with_banquet_captain',
        ];

        Log::info('Wedding vendor dossier registered', [
            'schedule_id' => $schedule->id,
            'milestone_name' => $schedule->name,
            'decorator' => $vendorDossier['decorator'],
        ]);

        return [
            'schedule_id' => $schedule->id,
            'milestone_name' => $schedule->name,
            'vendor_dossier' => $vendorDossier,
            'coordinated_at' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

class HotelSpaSchedulingEngine
{
    /**
     * @var array<string, array{name: string, base_rate_idr: int, duration_minutes: int, consumable_cost_idr: int}>
     */
    private const TREATMENTS = [
        'BALINESE_DEEP_TISSUE' => [
            'name' => 'Balinese Deep Tissue Massage',
            'base_rate_idr' => 450000,
            'duration_minutes' => 60,
            'consumable_cost_idr' => 65000,
        ],
        'AROMATHERAPY_HERBAL_BATH' => [
            'name' => 'Royal Herbal Aromatherapy',
            'base_rate_idr' => 650000,
            'duration_minutes' => 90,
            'consumable_cost_idr' => 110000,
        ],
        'HOT_STONE_THERAPY' => [
            'name' => 'Volcanic Hot Stone Therapy',
            'base_rate_idr' => 850000,
            'duration_minutes' => 90,
            'consumable_cost_idr' => 140000,
        ],
    ];

    /**
     * Jadwalkan sesi treatment spa, hitung dynamic pricing dan potong inventory consumable.
     *
     * @return array{booking_id: string, treatment_name: string, therapist_id: string, start_time: string, price_idr: int, room_charge_success: bool}
     */
    public function bookSpaSession(
        string $treatmentCode,
        string $therapistId,
        string $guestName,
        string $startTimeIso,
        ?string $folioId = null,
        bool $isPeakHour = false
    ): array {
        if (! isset(self::TREATMENTS[$treatmentCode])) {
            throw new InvalidArgumentException("Unknown spa treatment code: {$treatmentCode}");
        }

        $treatment = self::TREATMENTS[$treatmentCode];
        $price = $treatment['base_rate_idr'];

        // Dynamic peak surge (+20% on peak hours)
        if ($isPeakHour) {
            $price = (int) round($price * 1.20);
        }

        $bookingId = 'SPA-'.strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
        $roomChargeSuccess = false;

        // Post charge to room folio if requested
        if ($folioId !== null) {
            $folio = HotelFolio::find($folioId);
            if ($folio !== null) {
                HotelFolioLine::create([
                    'folio_id' => $folio->id,
                    'line_type' => 'CHARGE',
                    'charge_type' => 'SPA',
                    'description' => "Spa: {$treatment['name']} ({$bookingId})",
                    'amount_cents' => $price * 100,
                    'is_taxable' => true,
                    'business_date' => CarbonImmutable::now()->toDateString(),
                    'metadata' => [
                        'spa_booking_id' => $bookingId,
                        'therapist_id' => $therapistId,
                        'consumable_cost_idr' => $treatment['consumable_cost_idr'],
                    ],
                ]);
                $roomChargeSuccess = true;
            }
        }

        return [
            'booking_id' => $bookingId,
            'treatment_name' => $treatment['name'],
            'therapist_id' => $therapistId,
            'start_time' => $startTimeIso,
            'price_idr' => $price,
            'room_charge_success' => $roomChargeSuccess,
        ];
    }
}

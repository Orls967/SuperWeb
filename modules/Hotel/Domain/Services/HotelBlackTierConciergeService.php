<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTravelPass;
use RuntimeException;

final class HotelBlackTierConciergeService
{
    /**
     * @return array{
     *     dispatch_id: string,
     *     travel_pass_id: string,
     *     tier: string,
     *     assigned_butler_name: string,
     *     guaranteed_late_checkout_time: string,
     *     airport_limousine_requested: bool,
     *     special_amenities: list<string>,
     *     dispatched_at: string
     * }
     */
    public function dispatchVipArrivalExperience(
        string $travelPassId,
        string $reservationConfirmationCode,
        string $arrivalFlightNumber = 'GA-408',
        bool $requestAirportLimousine = true,
        array $customPillowSelection = ['Feather Soft Down', 'Lavender Aromatherapy']
    ): array {
        $pass = HtlTravelPass::findOrFail($travelPassId);

        if ($pass->tier !== 'BLACK') {
            throw new RuntimeException("Layanan Black Tier Concierge eksklusif hanya untuk pemegang Travel Pass tingkat BLACK (tingkat Anda: {$pass->tier})");
        }

        $butlerPool = [
            'Bapak Wayan Sudarma (Senior Head Butler)',
            'Ibu Putu Ratnasari (VIP Guest Relations Master)',
            'Bapak Hendra Gunawan (Private Concierge Specialist)',
        ];
        $assignedButler = $butlerPool[array_rand($butlerPool)];

        $dispatchId = 'VIP-CONC-'.now()->format('Ymd').'-'.bin2hex(random_bytes(3));

        $specialAmenities = array_merge([
            'Welcome Bottle of Vintage Dom Pérignon Champagne',
            'Fresh Tropical Fruit Basket & Artisan Chocolates',
            '24-Hour Dedicated Butler Hotline Access',
            'Complimentary Executive Boardroom Access (3 Hours)',
        ], $customPillowSelection);

        return [
            'dispatch_id' => $dispatchId,
            'travel_pass_id' => $pass->id,
            'tier' => $pass->tier,
            'assigned_butler_name' => $assignedButler,
            'guaranteed_late_checkout_time' => '18:00 WITA',
            'airport_limousine_requested' => $requestAirportLimousine,
            'special_amenities' => $specialAmenities,
            'dispatched_at' => now()->toIso8601String(),
        ];
    }
}

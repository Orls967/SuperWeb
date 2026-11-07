<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\MiceContract;

/**
 * Layanan Domain: Koordinasi Dapur Restoran & Pengelolaan Katering Perjamuan (Banquet).
 */
final class HotelBanquetKitchenCoordinationService
{
    /**
     * @return array{contract_id: string, event_name: string, pax_count: int, meal_schedule: array<int, array{time: string, course: string, head_chef: string}>, status: string}
     */
    public function planBanquetMealService(
        string $miceContractId,
        int $paxCount,
        string $headChefName
    ): array {
        $contract = MiceContract::findOrFail($miceContractId);

        $schedule = [
            ['time' => '10:00', 'course' => 'Morning Coffee Break & Pastry', 'head_chef' => $headChefName],
            ['time' => '12:30', 'course' => 'Executive Buffet Lunch 5-Course', 'head_chef' => $headChefName],
            ['time' => '15:30', 'course' => 'Afternoon High Tea & Canapes', 'head_chef' => $headChefName],
            ['time' => '19:00', 'course' => 'Gala Dinner Plated Set', 'head_chef' => $headChefName],
        ];

        return [
            'contract_id' => $contract->id,
            'event_name' => $contract->client_name.' - '.$contract->event_type,
            'pax_count' => $paxCount,
            'meal_schedule' => $schedule,
            'status' => 'KITCHEN_COMMITTED',
        ];
    }
}

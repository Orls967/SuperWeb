<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Services\HotelBanquetRecipeBomService;

final class GenerateBanquetBeoProductionSheetAction
{
    public function __construct(
        private readonly HotelBanquetRecipeBomService $banquetService
    ) {}

    /**
     * @param  array<int, array{
     *     item_id: string,
     *     name: string,
     *     type: string,
     *     portion_qty: float,
     *     unit: string,
     *     cost_per_unit_idr: int,
     *     yield_loss_ratio?: float,
     *     pour_shrinkage_ratio?: float,
     *     bottle_volume_ml?: int
     * }>  $recipeComponents
     */
    public function execute(
        MiceContract $contract,
        string $title,
        string $mealPeriod,
        int $guaranteedPax,
        string $eventDate,
        array $recipeComponents,
        float $safetyBufferRatio = 1.05,
        float $eventDurationHours = 4.0,
        int $hourlyLaborRateIdr = 50000
    ): array {
        return $this->banquetService->generateBanquetOrderWithBom(
            contract: $contract,
            title: $title,
            mealPeriod: $mealPeriod,
            guaranteedPax: $guaranteedPax,
            eventDate: $eventDate,
            recipeComponents: $recipeComponents,
            safetyBufferRatio: $safetyBufferRatio,
            eventDurationHours: $eventDurationHours,
            hourlyLaborRateIdr: $hourlyLaborRateIdr
        );
    }
}

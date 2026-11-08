<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Str;
use Modules\Hotel\Domain\Algorithms\BanquetBomExploder;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelBanquetRecipeBomService
{
    public function __construct(
        private readonly BanquetBomExploder $bomExploder
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
    public function generateBanquetOrderWithBom(
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
        $beoCode = 'BEO-'.strtoupper(bin2hex(random_bytes(4)));

        $certificate = $this->bomExploder->explode(
            beoId: $beoCode,
            guaranteedPax: $guaranteedPax,
            recipeComponents: $recipeComponents,
            safetyBufferRatio: $safetyBufferRatio,
            eventDurationHours: $eventDurationHours,
            hourlyLaborRateIdr: $hourlyLaborRateIdr
        );

        $order = HtlBanquetOrder::create([
            'id' => (string) Str::uuid(),
            'beo_code' => $beoCode,
            'mice_contract_id' => $contract->id,
            'title' => $title,
            'meal_period' => strtoupper($mealPeriod),
            'guaranteed_pax' => $guaranteedPax,
            'prepared_pax' => $certificate->preparedPax,
            'safety_buffer_ratio' => $safetyBufferRatio,
            'event_date' => $eventDate,
            'event_duration_hours' => $eventDurationHours,
            'hourly_labor_rate_idr' => $hourlyLaborRateIdr,
            'total_food_cogs_idr' => $certificate->totalFoodCogsIdr,
            'total_bev_cogs_idr' => $certificate->totalBeverageCogsIdr,
            'total_labor_cost_idr' => $certificate->totalLaborCostIdr,
            'status' => 'CONFIRMED',
            'recipe_bom_json' => [
                'ingredients' => $certificate->ingredientsSummary,
                'beverages' => $certificate->beveragesSummary,
            ],
            'signature_hash' => $certificate->signatureHash,
        ]);

        return [
            'order' => $order,
            'certificate' => $certificate,
        ];
    }
}

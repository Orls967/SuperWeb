<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Domain\Algorithms\AbcVelocityClassifier;
use Modules\Wms\Domain\Services\SlottingFitnessCalculator;

/**
 * Layanan rekomendasi re-slotting ABC end-to-end.
 */
final class WmsSlottingRecommendationService
{
    public function __construct(
        private readonly AbcVelocityClassifier $classifier,
        private readonly SlottingFitnessCalculator $fitnessCalculator,
        private readonly SlottingScenarioPlanner $scenarioPlanner,
    ) {}

    /**
     * @param  list<array{product_id: int, sku: string, annual_value_idr: int, zone_kind: string, distance_m: int, weekly_picks: int, qty: int}>  $skus
     * @param  list<array{id: string|int, distance: int, freeCapacity: int}>  $availableBins
     * @return array{
     *     classified_count: int,
     *     average_fitness_bps: int,
     *     mismatch_count: int,
     *     relocation_plan: array<string, mixed>
     * }
     */
    public function recommend(array $skus, array $availableBins): array
    {
        $classified = $this->classifier->classify(array_map(static fn (array $s): array => [
            'product_id' => $s['product_id'],
            'annual_value_idr' => $s['annual_value_idr'],
        ], $skus));

        $classByProduct = [];
        foreach ($classified as $c) {
            $classByProduct[$c['product_id']] = $c['abc_class'];
        }

        $fitnessInput = [];
        $relocationItems = [];
        foreach ($skus as $s) {
            $abc = $classByProduct[$s['product_id']] ?? 'C';
            $fitnessInput[] = [
                'sku' => $s['sku'],
                'abc_class' => $abc,
                'zone_kind' => $s['zone_kind'],
                'distance_m' => $s['distance_m'],
                'weekly_picks' => $s['weekly_picks'],
            ];
            $relocationItems[] = [
                'sku' => $s['sku'],
                'abcClass' => $abc,
                'qty' => $s['qty'],
                'currentDistance' => $s['distance_m'],
            ];
        }

        $fitness = $this->fitnessCalculator->evaluate($fitnessInput);
        $plan = $this->scenarioPlanner->simulateRelocation($relocationItems, $availableBins);

        return [
            'classified_count' => count($classified),
            'average_fitness_bps' => $fitness['average_fitness_bps'],
            'mismatch_count' => $fitness['mismatch_count'],
            'relocation_plan' => $plan,
        ];
    }
}

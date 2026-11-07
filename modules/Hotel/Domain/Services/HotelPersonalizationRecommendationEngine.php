<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Application\Actions\GeneratePersonalizedTravelRecommendationsAction;

final class HotelPersonalizationRecommendationEngine
{
    public function __construct(
        protected ?GeneratePersonalizedTravelRecommendationsAction $action = null
    ) {
        $this->action = $action ?? app(GeneratePersonalizedTravelRecommendationsAction::class);
    }

    /**
     * @return array{member_tier: string, recommended_packages: list<array{destination: string, package_title: string, points_discounted: int, affinity_score: float}>}
     */
    public function build360ProfileRecommendations(string $travelPassId): array
    {
        return $this->action->execute($travelPassId);
    }
}

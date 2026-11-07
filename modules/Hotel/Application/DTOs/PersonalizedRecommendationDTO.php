<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final readonly class PersonalizedRecommendationDTO
{
    /**
     * @param  list<array{destination: string, package_title: string, points_discounted: int, affinity_score: float}>  $recommendedPackages
     */
    public function __construct(
        public string $travelPassId,
        public string $memberTier,
        public array $recommendedPackages,
        public string $generatedAt
    ) {}
}

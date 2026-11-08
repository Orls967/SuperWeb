<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

final readonly class BanquetBomExplosionCertificate
{
    /**
     * @param  array<string, mixed>  $ingredientsSummary
     * @param  array<string, mixed>  $beveragesSummary
     */
    public function __construct(
        public string $beoId,
        public int $guaranteedPax,
        public int $preparedPax,
        public int $totalFoodCogsIdr,
        public int $totalBeverageCogsIdr,
        public int $totalLaborCostIdr,
        public int $totalDirectCostIdr,
        public int $waitersRequired,
        public int $cooksRequired,
        public array $ingredientsSummary,
        public array $beveragesSummary,
        public string $signatureHash
    ) {}
}

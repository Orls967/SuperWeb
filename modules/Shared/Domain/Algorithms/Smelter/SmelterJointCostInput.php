<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

use InvalidArgumentException;

final class SmelterJointCostInput
{
    /**
     * @param  int  $jointSmeltingCostIdr  Total joint smelting operating cost (power, reductant, flux, labor, refractory)
     * @param  float  $primaryMetalOutputTons  Primary metal product (NPI / Nickel Matte / Cathode) output in metric tons
     * @param  int  $primaryMetalMarketPriceIdr  Market value of primary metal per metric ton in IDR
     * @param  float  $granulatedSlagOutputTons  Granulated blast furnace slag output in metric tons
     * @param  int  $granulatedSlagMarketPriceIdr  Market value of granulated slag per metric ton in IDR
     * @param  float  $crudeSulfurOutputTons  Byproduct elemental sulfur output in metric tons
     * @param  int  $crudeSulfurMarketPriceIdr  Market value of sulfur per metric ton in IDR
     * @param  float  $metalFlueDustOutputTons  Flue dust output in metric tons
     * @param  int  $metalFlueDustMarketPriceIdr  Market value of flue dust per metric ton in IDR
     */
    public function __construct(
        public readonly int $jointSmeltingCostIdr,
        public readonly float $primaryMetalOutputTons,
        public readonly int $primaryMetalMarketPriceIdr,
        public readonly float $granulatedSlagOutputTons,
        public readonly int $granulatedSlagMarketPriceIdr,
        public readonly float $crudeSulfurOutputTons,
        public readonly int $crudeSulfurMarketPriceIdr,
        public readonly float $metalFlueDustOutputTons,
        public readonly int $metalFlueDustMarketPriceIdr,
    ) {
        if ($this->jointSmeltingCostIdr <= 0) {
            throw new InvalidArgumentException('Joint smelting cost must be positive.');
        }
        if ($this->primaryMetalOutputTons <= 0.0) {
            throw new InvalidArgumentException('Primary metal output tons must be positive.');
        }
        if ($this->primaryMetalMarketPriceIdr <= 0) {
            throw new InvalidArgumentException('Primary metal market price must be positive.');
        }
        if ($this->granulatedSlagOutputTons < 0.0 || $this->crudeSulfurOutputTons < 0.0 || $this->metalFlueDustOutputTons < 0.0) {
            throw new InvalidArgumentException('Byproduct outputs cannot be negative.');
        }
    }
}

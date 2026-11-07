<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

final class SmelterFeedParameters
{
    public function __construct(
        public readonly float $oreFeedWmt,
        public readonly float $oreMoisturePct,
        public readonly float $niGradePct,
        public readonly float $feGradePct,
        public readonly float $sio2GradePct,
        public readonly float $mgoGradePct,
        public readonly float $al2o3GradePct,
        public readonly float $coalReductantTons,
        public readonly float $coalFixedCarbonPct,
        public readonly float $silicaFluxTons,
        public readonly float $limestoneFluxTons,
        public readonly float $targetTappingTemperatureCelsius = 1520.0,
    ) {
        if ($this->oreFeedWmt <= 0.0) {
            throw new \InvalidArgumentException('Ore feed WMT must be positive.');
        }
        if ($this->oreMoisturePct < 0.0 || $this->oreMoisturePct >= 70.0) {
            throw new \InvalidArgumentException('Ore moisture percent must be between 0 and 70%.');
        }
        if ($this->niGradePct <= 0.0 || $this->niGradePct > 25.0) {
            throw new \InvalidArgumentException('Nickel grade must be between 0 and 25%.');
        }
        if ($this->feGradePct <= 0.0 || $this->feGradePct > 60.0) {
            throw new \InvalidArgumentException('Iron grade must be between 0 and 60%.');
        }
        if ($this->coalReductantTons < 0.0) {
            throw new \InvalidArgumentException('Coal reductant tons cannot be negative.');
        }
        if ($this->silicaFluxTons < 0.0) {
            throw new \InvalidArgumentException('Silica flux tons cannot be negative.');
        }
    }

    public function dryOreFeedTons(): float
    {
        return round($this->oreFeedWmt * (1.0 - ($this->oreMoisturePct / 100.0)), 4);
    }

    public function sio2MgoRatio(): float
    {
        if ($this->mgoGradePct <= 0.0) {
            return 0.0;
        }

        return round($this->sio2GradePct / $this->mgoGradePct, 4);
    }

    public function containedNickelTons(): float
    {
        return round($this->dryOreFeedTons() * ($this->niGradePct / 100.0), 4);
    }

    public function containedIronTons(): float
    {
        return round($this->dryOreFeedTons() * ($this->feGradePct / 100.0), 4);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredHedgeHorizonQuote;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredProductionHedgeOptimizer;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredProductionHedgeResult;
use Modules\Shared\Domain\Algorithms\MetalsTrading\LayeredProductionHedgeVerifier;

final class PlanLayeredMetalsHedgingAction
{
    public function __construct(
        private readonly LayeredProductionHedgeOptimizer $optimizer,
        private readonly LayeredProductionHedgeVerifier $verifier,
    ) {}

    /**
     * @param  list<LayeredHedgeHorizonQuote>  $quotes
     */
    public function execute(array $quotes, float $downsideShockPct = 0.20): LayeredProductionHedgeResult
    {
        $result = $this->optimizer->optimize($quotes, $downsideShockPct);

        $isValid = $this->verifier->verify($result, $quotes);
        if (! $isValid) {
            throw new \DomainException('Verifikasi kriptografi rencana lindung nilai berlapis (layered hedge) gagal.');
        }

        return $result;
    }
}

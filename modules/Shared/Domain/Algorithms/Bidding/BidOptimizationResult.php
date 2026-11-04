<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Bidding;

use Brick\Math\BigRational;

final class BidOptimizationResult
{
    /**
     * @param  list<int>  $history
     * @param  list<array<string, mixed>>  $candidateEvaluations
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public readonly int $c,
        public readonly int $step,
        public readonly array $history,
        public readonly int $minWinBps,
        public readonly int $minMarginBps,
        public readonly int $optimalPrice,
        public readonly BigRational $pWin,
        public readonly string $pWinFormatted,
        public readonly BigRational $expectedProfit,
        public readonly string $expectedProfitFormatted,
        public readonly array $candidateEvaluations,
        public readonly array $certificate
    ) {}

    /**
     * @param  array<string, mixed>  $certificate
     */
    public function withCertificate(array $certificate): self
    {
        return new self(
            $this->c,
            $this->step,
            $this->history,
            $this->minWinBps,
            $this->minMarginBps,
            $this->optimalPrice,
            $this->pWin,
            $this->pWinFormatted,
            $this->expectedProfit,
            $this->expectedProfitFormatted,
            $this->candidateEvaluations,
            $certificate
        );
    }
}

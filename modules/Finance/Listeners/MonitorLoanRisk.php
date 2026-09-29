<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Crypto\Domain\Events\PricesTicked;
use Modules\Finance\Application\Actions\EvaluateLoanRiskAction;

/**
 * Setiap tick harga kripto, evaluasi LTV seluruh pembiayaan terbuka:
 * margin call, pemulihan, atau likuidasi otomatis.
 */
class MonitorLoanRisk
{
    public function __construct(
        private readonly EvaluateLoanRiskAction $evaluateRisk,
    ) {}

    public function handle(PricesTicked $event): void
    {
        $this->evaluateRisk->evaluateAll();
    }
}

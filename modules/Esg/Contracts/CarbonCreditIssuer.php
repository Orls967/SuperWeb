<?php

declare(strict_types=1);

namespace Modules\Esg\Contracts;

interface CarbonCreditIssuer
{
    public function issueRestorationCredits(int $forestPlotId, int $kgCo2e, int $vintageYear, string $idempotencyKey): int;
}

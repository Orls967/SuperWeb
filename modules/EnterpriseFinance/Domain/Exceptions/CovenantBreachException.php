<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class CovenantBreachException extends RuntimeException
{
    public function __construct(string $facilityCode, string $covenantName, int $actualPermille, int $thresholdPermille)
    {
        parent::__construct("Pelanggaran covenant kredit sindikasi [{$facilityCode}] pada rasio {$covenantName}: Aktual ({$actualPermille} permille) melanggar batas ({$thresholdPermille} permille).");
    }
}

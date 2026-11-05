<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

/**
 * Hasil kalkulasi kewajiban zakat mal.
 */
final class ZakatAssessmentResult
{
    public function __construct(
        public readonly int $goldPricePerGramIdr,
        public readonly int $nisabIdr,
        public readonly int $qualifyingWealthIdr,
        public readonly int $dueDebtsIdr,
        public readonly int $netZakatBaseIdr,
        public readonly int $holdingDays,
        public readonly bool $haulFulfilled,
        public readonly bool $reachesNisab,
        public readonly bool $isObligated,
        public readonly int $zakatAmountIdr
    ) {}
}


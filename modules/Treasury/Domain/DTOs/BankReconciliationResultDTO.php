<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

use Modules\Treasury\Domain\Algorithms\Certificates\BankReconciliationCertificate;

final class BankReconciliationResultDTO
{
    /**
     * @param  list<ExactMatchDTO>  $exactMatches
     * @param  list<ToleranceMatchDTO>  $toleranceMatches
     * @param  list<ManyToOneMatchDTO>  $manyToOneMatches
     * @param  list<BankStatementLineDTO>  $unmatchedBankLines
     * @param  list<BookLedgerLineDTO>  $unmatchedBookLines
     */
    public function __construct(
        public array $exactMatches,
        public array $toleranceMatches,
        public array $manyToOneMatches,
        public array $unmatchedBankLines,
        public array $unmatchedBookLines,
        public BankReconciliationCertificate $certificate
    ) {}
}

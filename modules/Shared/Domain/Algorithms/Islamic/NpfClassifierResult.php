<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

final readonly class NpfClassifierResult
{
    /**
     * @param  array<int, array{contract_ref: string, dpd: int, collectibility: int, collectibility_name: string, principal_minor: int, provision_rate_bps: int, provision_amount_minor: int}>  $classifiedItems
     * @param  array<int, array{count: int, total_principal_minor: int, total_provision_minor: int}>  $bucketSummary
     */
    public function __construct(
        public int $totalContracts,
        public int $totalPortfolioPrincipalMinor,
        public int $performingPrincipalMinor,     // Kol 1 + Kol 2
        public int $nonPerformingPrincipalMinor,  // Kol 3 + Kol 4 + Kol 5
        public int $totalRequiredProvisionMinor,
        public int $npfGrossRatioBps,             // (Non-performing / Total) * 10000
        public int $npfNetRatioBps,               // ((Non-performing - Non-performing Provision) / Total) * 10000
        public bool $isCompliantWithPrudentialLimit, // NPF Gross <= 500 bps (5.00%)
        public array $bucketSummary,
        public array $classifiedItems,
        public string $certificateHash,
    ) {}
}

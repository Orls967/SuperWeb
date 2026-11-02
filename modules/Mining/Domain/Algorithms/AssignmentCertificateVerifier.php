<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Assignment\AssignmentCertificateVerifier as BaseVerifier;
use Modules\Shared\Domain\Algorithms\Assignment\AssignmentResult;

class AssignmentCertificateVerifier
{
    private BaseVerifier $baseVerifier;

    public function __construct(?BaseVerifier $baseVerifier = null)
    {
        $this->baseVerifier = $baseVerifier ?? new BaseVerifier;
    }

    /**
     * @param  list<list<int>>  $costMatrix
     * @param  array<int, int>  $assignment
     * @param  array{u: list<int>, v: list<int>}  $certificate
     */
    public function verify(array $costMatrix, array $assignment, int $totalCost, array $certificate): bool
    {
        $u = $certificate['u'] ?? [];
        $v = $certificate['v'] ?? [];

        try {
            $result = new AssignmentResult($assignment, $totalCost, $u, $v);

            return $this->baseVerifier->verify($costMatrix, $result);
        } catch (\Throwable) {
            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use Brick\Math\BigRational;
use Modules\Shared\Domain\Algorithms\Lp\LpCertificateVerifier as SharedLpCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Lp\LpResult;
use Modules\Shared\Domain\Algorithms\Lp\LpStatus;

class LpCertificateVerifier
{
    private SharedLpCertificateVerifier $sharedVerifier;

    public function __construct(?SharedLpCertificateVerifier $sharedVerifier = null)
    {
        $this->sharedVerifier = $sharedVerifier ?? new SharedLpCertificateVerifier;
    }

    /**
     * @param  array{c: list<int|string>, A: list<list<int|string>>, b: list<int|string>}  $lpProblem
     * @param  list<int|string|BigRational>  $rationalSolution
     * @param  list<int|string|BigRational>  $certificate
     */
    public function verifyOptimal(array $lpProblem, array $rationalSolution, array $certificate): bool
    {
        try {
            $c = array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $lpProblem['c']);
            $A = array_map(fn ($row) => array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $row), $lpProblem['A']);
            $b = array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $lpProblem['b']);
            $x = array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $rationalSolution);
            $y = array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $certificate);

            $obj = BigRational::zero();
            for ($i = 0; $i < count($c); $i++) {
                $obj = $obj->plus($c[$i]->multipliedBy($x[$i]));
            }

            $result = new LpResult(LpStatus::OPTIMAL, $x, $obj, $y);

            return $this->sharedVerifier->verify($c, $A, $b, $result);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array{c: list<int|string>, A: list<list<int|string>>, b: list<int|string>}  $lpProblem
     * @param  list<int|string|BigRational>  $farkasRay
     */
    public function verifyInfeasible(array $lpProblem, array $farkasRay): bool
    {
        try {
            $c = array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $lpProblem['c']);
            $A = array_map(fn ($row) => array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $row), $lpProblem['A']);
            $b = array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $lpProblem['b']);
            $y = array_map(fn ($v) => $v instanceof BigRational ? $v : BigRational::of($v), $farkasRay);

            $result = new LpResult(LpStatus::INFEASIBLE, [], null, $y);

            return $this->sharedVerifier->verify($c, $A, $b, $result);
        } catch (\Throwable) {
            return false;
        }
    }
}

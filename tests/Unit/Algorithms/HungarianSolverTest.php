<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Assignment\AssignmentCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Assignment\AssignmentResult;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(HungarianSolver::class, AssignmentCertificateVerifier::class, AssignmentResult::class);

describe('HungarianSolver', function (): void {
    it('validates dimension constraints and infeasible rows', function (): void {
        $solver = new HungarianSolver;

        // Rows > cols
        expect(fn () => $solver->solve([
            [1, 2],
            [3, 4],
            [5, 6],
        ]))->toThrow(InvalidArgumentException::class);

        // Irregular matrix
        expect(fn () => $solver->solve([
            [1, 2, 3],
            [4, 5],
        ]))->toThrow(InvalidArgumentException::class);

        // Row with all INF
        expect(fn () => $solver->solve([
            [1, 2, 3],
            [HungarianSolver::INF, HungarianSolver::INF, HungarianSolver::INF],
            [4, 5, 6],
        ]))->toThrow(InvalidArgumentException::class, 'tidak memiliki sel terhingga');

        // Infeasible matching
        expect(fn () => $solver->solve([
            [1, HungarianSolver::INF],
            [2, HungarianSolver::INF],
        ]))->toThrow(InvalidArgumentException::class, 'penugasan melibatkan biaya tak terhingga');
    });

    it('returns empty result for empty matrix', function (): void {
        $solver = new HungarianSolver;
        $res = $solver->solve([]);
        expect($res->assignment)->toBe([])
            ->and($res->totalCost)->toBe(0)
            ->and($res->u)->toBe([])
            ->and($res->v)->toBe([]);
    });

    it('matches mandatory test vector [[4,1,3],[2,0,5],[3,2,2]] -> total 5', function (): void {
        $solver = new HungarianSolver;
        $matrix = [
            [4, 1, 3],
            [2, 0, 5],
            [3, 2, 2],
        ];

        $result = $solver->solve($matrix);

        expect($result->totalCost)->toBe(5)
            ->and($result->assignment)->toBe([
                0 => 1,
                1 => 0,
                2 => 2,
            ]);

        // Verifier must pass
        $verifier = new AssignmentCertificateVerifier;
        expect($verifier->verify($matrix, $result))->toBeTrue();
    });

    it('supports rectangular matrix with rows < cols', function (): void {
        $solver = new HungarianSolver;
        $matrix = [
            [10, 2, 8, 12],
            [5, 9, 3, 11],
        ];

        $result = $solver->solve($matrix);

        // Optimal: row 0 -> col 1 (cost 2), row 1 -> col 2 (cost 3), total = 5
        expect($result->totalCost)->toBe(5)
            ->and($result->assignment[0])->toBe(1)
            ->and($result->assignment[1])->toBe(2);

        $verifier = new AssignmentCertificateVerifier;
        expect($verifier->verify($matrix, $result))->toBeTrue();
    });

    it('deterministically breaks ties by choosing lowest column index', function (): void {
        $solver = new HungarianSolver;
        $matrix = [
            [5, 5, 5],
        ];
        $result = $solver->solve($matrix);
        expect($result->assignment[0])->toBe(0)
            ->and($result->totalCost)->toBe(5);
    });

    it('verifier thoroughly validates all edge corruption cases', function (): void {
        $verifier = new AssignmentCertificateVerifier;
        $matrix = [
            [4, 1, 3],
            [2, 0, 5],
            [3, 2, 2],
        ];

        // u length mismatch
        $badU = new AssignmentResult([0 => 1, 1 => 0, 2 => 2], 5, [1, 0], [2, 0, 1]);
        expect(fn () => $verifier->verify($matrix, $badU))->toThrow(RuntimeException::class);

        // v length mismatch
        $badV = new AssignmentResult([0 => 1, 1 => 0, 2 => 2], 5, [1, 0, 1], [2, 0]);
        expect(fn () => $verifier->verify($matrix, $badV))->toThrow(RuntimeException::class);

        // Missing assignment for a row
        $missingRow = new AssignmentResult([0 => 1, 1 => 0], 5, [1, 0, 1], [2, 0, 1]);
        expect(fn () => $verifier->verify($matrix, $missingRow))->toThrow(RuntimeException::class);

        // Out of bounds column
        $outCol = new AssignmentResult([0 => 1, 1 => 0, 2 => 5], 5, [1, 0, 1], [2, 0, 1]);
        expect(fn () => $verifier->verify($matrix, $outCol))->toThrow(RuntimeException::class);

        // Duplicate column assignment
        $dupCol = new AssignmentResult([0 => 1, 1 => 1, 2 => 2], 5, [1, 0, 1], [2, 0, 1]);
        expect(fn () => $verifier->verify($matrix, $dupCol))->toThrow(RuntimeException::class);

        // Total cost mismatch
        $costMismatch = new AssignmentResult([0 => 1, 1 => 0, 2 => 2], 999, [1, 0, 1], [2, 0, 1]);
        expect(fn () => $verifier->verify($matrix, $costMismatch))->toThrow(RuntimeException::class);

        // Unused column with positive dual potential v > 0 in rectangular case
        $rectMatrix = [
            [1, 2],
        ];
        $badUnusedCol = new AssignmentResult([0 => 0], 1, [0], [1, 5]);
        expect(fn () => $verifier->verify($rectMatrix, $badUnusedCol))->toThrow(RuntimeException::class);
    });

    it('verifier catches invalid certificate tampering', function (): void {
        $solver = new HungarianSolver;
        $matrix = [
            [4, 1, 3],
            [2, 0, 5],
            [3, 2, 2],
        ];
        $result = $solver->solve($matrix);

        $verifier = new AssignmentCertificateVerifier;

        // Tamper with dual potential u (violating u + v <= c or slackness)
        $tamperedU = $result->u;
        $tamperedU[0] += 10;
        $badResult = new AssignmentResult($result->assignment, $result->totalCost, $tamperedU, $result->v);
        expect(fn () => $verifier->verify($matrix, $badResult))->toThrow(RuntimeException::class);

        // Tamper with assignment
        $tamperedAssign = [0 => 0, 1 => 1, 2 => 2];
        $badAssignResult = new AssignmentResult($tamperedAssign, $result->totalCost, $result->u, $result->v);
        expect(fn () => $verifier->verify($matrix, $badAssignResult))->toThrow(RuntimeException::class);
    });

    it('ensures verifier is executed and can reject invalid states', function (): void {
        $solver = new HungarianSolver;
        $mockVerifier = new class extends AssignmentCertificateVerifier
        {
            public function verify(array $costMatrix, AssignmentResult $result, int $infCost = HungarianSolver::INF): bool
            {
                throw new RuntimeException('verifier was invoked');
            }
        };

        expect(fn () => $solver->solve([[1, 2], [3, 4]], $mockVerifier))
            ->toThrow(RuntimeException::class, 'verifier was invoked');
    });

    it('property test: compares Hungarian against brute-force permutation oracle for n <= 4', function (): void {
        $solver = new HungarianSolver;
        $seed = 77889900;
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

        // Permutation generator helper
        $generatePermutations = function (array $elements) use (&$generatePermutations): array {
            if (count($elements) <= 1) {
                return [$elements];
            }
            $perms = [];
            foreach ($elements as $key => $first) {
                $rem = $elements;
                unset($rem[$key]);
                foreach ($generatePermutations(array_values($rem)) as $sub) {
                    $perms[] = array_merge([$first], $sub);
                }
            }

            return $perms;
        };

        for ($trial = 0; $trial < 8; $trial++) {
            $n = $randomizer->getInt(2, 3);
            $m = $randomizer->getInt($n, $n + 1);

            $matrix = [];
            for ($i = 0; $i < $n; $i++) {
                $row = [];
                for ($j = 0; $j < $m; $j++) {
                    $row[] = $randomizer->getInt(1, 100);
                }
                $matrix[] = $row;
            }

            $result = $solver->solve($matrix);

            // Brute force all injective assignments of n rows to m columns
            // Choose n columns from m, then all permutations
            $cols = range(0, $m - 1);
            $allPerms = $generatePermutations($cols);

            $minBruteForce = PHP_INT_MAX;
            foreach ($allPerms as $perm) {
                $cost = 0;
                for ($i = 0; $i < $n; $i++) {
                    $cost += $matrix[$i][$perm[$i]];
                }
                if ($cost < $minBruteForce) {
                    $minBruteForce = $cost;
                }
            }

            expect($result->totalCost)->toBe($minBruteForce);
        }
    });

    it('benchmarks 300x300 matrix < 2s', function (): void {
        $solver = new HungarianSolver;
        $n = 300;
        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $n; $j++) {
                $row[] = (($i * 37 + $j * 17) % 500) + 1;
            }
            $matrix[] = $row;
        }

        $start = microtime(true);
        $result = $solver->solve($matrix);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(2.0)
            ->and(count($result->assignment))->toBe(300);
    })->group('skala');
});

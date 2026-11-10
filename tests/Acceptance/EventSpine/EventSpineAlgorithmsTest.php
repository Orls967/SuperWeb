<?php

declare(strict_types=1);

namespace Tests\Acceptance\EventSpine;

use Modules\Core\Domain\Algorithms\Events\AggregateSequenceGapDetector;
use Modules\Core\Domain\Algorithms\Events\DeterministicBackoff;
use Modules\Core\Domain\Algorithms\Events\NearestRankLagCalculator;
use Modules\Core\Domain\Algorithms\Events\ProjectionHashCalculator;
use Modules\Core\Domain\Algorithms\Events\SchemaAdditiveCompatibilityChecker;
use Tests\TestCase;

class EventSpineAlgorithmsTest extends TestCase
{
    /**
     * Vektor A4: AggregateSequenceGapDetector
     * [1,2,3,5,6,9] -> celah [(4,4), (7,8)]
     * [1,2,2,3] -> duplikat [2]
     * [2,3] -> celah [(1,1)]
     * [1,2,3,4] -> bersih
     * [3,1,2] -> bersih
     */
    public function test_a4_aggregate_sequence_gap_detector_vectors(): void
    {
        $detector = new AggregateSequenceGapDetector;

        // Kasus 1: [1,2,3,5,6,9]
        $res1 = $detector->detect([1, 2, 3, 5, 6, 9]);
        $this->assertEquals([[4, 4], [7, 8]], $res1['gaps']);
        $this->assertEmpty($res1['duplicates']);

        // Kasus 2: [1,2,2,3]
        $res2 = $detector->detect([1, 2, 2, 3]);
        $this->assertEmpty($res2['gaps']);
        $this->assertEquals([2], $res2['duplicates']);

        // Kasus 3: [2,3]
        $res3 = $detector->detect([2, 3]);
        $this->assertEquals([[1, 1]], $res3['gaps']);
        $this->assertEmpty($res3['duplicates']);

        // Kasus 4: [1,2,3,4]
        $res4 = $detector->detect([1, 2, 3, 4]);
        $this->assertEmpty($res4['gaps']);
        $this->assertEmpty($res4['duplicates']);

        // Kasus 5: [3,1,2] -> unordered tapi lengkap mulai dari 1
        $res5 = $detector->detect([3, 1, 2]);
        $this->assertEmpty($res5['gaps']);
        $this->assertEmpty($res5['duplicates']);
    }

    /**
     * Vektor A5: DeterministicBackoff
     * dasar 30, batas 3600, idPesan 1001:
     * n=1..8 -> jeda 36, 65, 140, 300, 590, 1033, 2362, 3983
     * mentah 30, 60, 120, 240, 480, 960, 1920, 3600; jitter 6, 5, 20, 60, 110, 73, 442, 383
     * idPesan 7, n=1..3 -> 32, 71, 139
     */
    public function test_a5_deterministic_backoff_vectors(): void
    {
        $backoff = new DeterministicBackoff(baseSeconds: 30, maxSeconds: 3600);

        $expected1001 = [
            1 => ['raw' => 30, 'jitter' => 6, 'delay' => 36],
            2 => ['raw' => 60, 'jitter' => 5, 'delay' => 65],
            3 => ['raw' => 120, 'jitter' => 20, 'delay' => 140],
            4 => ['raw' => 240, 'jitter' => 60, 'delay' => 300],
            5 => ['raw' => 480, 'jitter' => 110, 'delay' => 590],
            6 => ['raw' => 960, 'jitter' => 73, 'delay' => 1033],
            7 => ['raw' => 1920, 'jitter' => 442, 'delay' => 2362],
            8 => ['raw' => 3600, 'jitter' => 383, 'delay' => 3983],
        ];

        foreach ($expected1001 as $n => $exp) {
            $calc = $backoff->calculate(messageId: 1001, attempt: $n);
            $this->assertEquals($exp['raw'], $calc['raw'], "Raw mismatch at attempt {$n}");
            $this->assertEquals($exp['jitter'], $calc['jitter'], "Jitter mismatch at attempt {$n}");
            $this->assertEquals($exp['delay'], $calc['delay'], "Delay mismatch at attempt {$n}");
        }

        // Test idPesan = 7, attempt 1..3 -> 32, 71, 139
        $calc7_1 = $backoff->calculate(messageId: 7, attempt: 1);
        $this->assertEquals(32, $calc7_1['delay']);

        $calc7_2 = $backoff->calculate(messageId: 7, attempt: 2);
        $this->assertEquals(71, $calc7_2['delay']);

        $calc7_3 = $backoff->calculate(messageId: 7, attempt: 3);
        $this->assertEquals(139, $calc7_3['delay']);
    }

    /**
     * Vektor A6: SchemaAdditiveCompatibilityChecker
     */
    public function test_a6_schema_additive_compatibility_checker_vectors(): void
    {
        $checker = new SchemaAdditiveCompatibilityChecker;

        $v1 = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'amount' => ['type' => 'integer', 'minimum' => 0],
                'status' => ['type' => 'string', 'enum' => ['a', 'b']],
            ],
            'required' => ['id', 'amount'],
        ];

        // v2A = v1 + properti opsional note (string) dan enum status ["a","b","c"] -> kompatibel
        $v2A = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'amount' => ['type' => 'integer', 'minimum' => 0],
                'status' => ['type' => 'string', 'enum' => ['a', 'b', 'c']],
                'note' => ['type' => 'string'],
            ],
            'required' => ['id', 'amount'],
        ];
        $violationsA = $checker->checkCompatibility($v1, $v2A);
        $this->assertEmpty($violationsA, 'v2A harus kompatibel murni secara aditif.');

        // v2B = amount minimum:1 type:number, status enum ["a"], note string, required [id, amount, note]
        $v2B = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'amount' => ['type' => 'number', 'minimum' => 1],
                'status' => ['type' => 'string', 'enum' => ['a']],
                'note' => ['type' => 'string'],
            ],
            'required' => ['id', 'amount', 'note'],
        ];
        $violationsB = $checker->checkCompatibility($v1, $v2B);
        $expectedViolationsB = [
            ['path' => '/properties/amount/minimum', 'code' => 'RANGE_NARROWED'],
            ['path' => '/properties/amount/type', 'code' => 'TYPE_CHANGED'],
            ['path' => '/properties/status/enum/b', 'code' => 'ENUM_VALUE_REMOVED'],
            ['path' => '/required/note', 'code' => 'REQUIRED_ADDED'],
        ];
        $this->assertEquals($expectedViolationsB, $violationsB);

        // v2C = v1 tanpa properti status -> [(/properties/status, PROPERTY_REMOVED)]
        $v2C = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'amount' => ['type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['id', 'amount'],
        ];
        $violationsC = $checker->checkCompatibility($v1, $v2C);
        $expectedViolationsC = [
            ['path' => '/properties/status', 'code' => 'PROPERTY_REMOVED'],
        ];
        $this->assertEquals($expectedViolationsC, $violationsC);
    }

    /**
     * Vektor Nearest-Rank Lag p95:
     * lag 1..20 -> 19
     * lag [5, 1, 9, 3] -> 9
     */
    public function test_nearest_rank_lag_p95_vectors(): void
    {
        $calculator = new NearestRankLagCalculator;

        $lags1to20 = range(1, 20);
        $p95_1 = $calculator->calculateP95($lags1to20);
        $this->assertEquals(19, $p95_1);

        $lags4 = [5, 1, 9, 3];
        $p95_2 = $calculator->calculateP95($lags4);
        $this->assertEquals(9, $p95_2);
    }

    /**
     * Vektor Kanonikal Projection Hash:
     * Dua kali hash state identik (termasuk ksort keys) menghasilkan SHA-256 yang sama.
     */
    public function test_projection_hash_canonical_consistency(): void
    {
        $calc = new ProjectionHashCalculator;

        $state1 = [
            'z_account' => 500,
            'a_balance' => 1200,
            'details' => ['y' => 2, 'x' => 1],
        ];

        $state2 = [
            'a_balance' => 1200,
            'details' => ['x' => 1, 'y' => 2],
            'z_account' => 500,
        ];

        $h1 = $calc->computeHash($state1);
        $h2 = $calc->computeHash($state2);

        $this->assertEquals($h1, $h2);
        $this->assertEquals(64, strlen($h1));
    }
}

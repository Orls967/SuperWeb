<?php

declare(strict_types=1);

namespace Tests\Unit\Math;

use InvalidArgumentException;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Modules\Shared\Domain\Math\WeightedTree;
use Modules\Shared\Domain\Math\WeightedTreeCertificate;
use Modules\Shared\Domain\Math\WeightedTreeCertificateVerifier;
use Modules\Shared\Domain\Math\WeightedTreeNode;

covers(WeightedTree::class, WeightedTreeCertificateVerifier::class);

test('it rejects invalid inputs', function () {
    expect(fn () => new WeightedTreeNode('', 5000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WeightedTreeNode('A', -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WeightedTreeNode('A', 10001))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WeightedTreeNode('A', 5000, -10))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WeightedTreeNode('A', 5000, 10001))->toThrow(InvalidArgumentException::class);

    // Children weight sum != 10000
    $badRoot = new WeightedTreeNode('root', 10000, null, [
        new WeightedTreeNode('A', 6000, 5000),
        new WeightedTreeNode('B', 3999, 5000), // Sum = 9999
    ]);
    expect(fn () => WeightedTree::computeProgress($badRoot))->toThrow(InvalidArgumentException::class);

    // Duplicate node IDs
    $dupRoot = new WeightedTreeNode('root', 10000, null, [
        new WeightedTreeNode('dup', 5000, 5000),
        new WeightedTreeNode('dup', 5000, 5000),
    ]);
    expect(fn () => WeightedTree::computeProgress($dupRoot))->toThrow(InvalidArgumentException::class);

    $nestedDup = new WeightedTreeNode('root', 10_000, null, [
        new WeightedTreeNode('root', 10_000, 5_000),
    ]);
    expect(fn () => WeightedTree::computeProgress($nestedDup))->toThrow(InvalidArgumentException::class);
});

test('it matches required vector: root A 6000 / B 4000; A children a1 5000 (100%) / a2 5000 (0%); B 0% -> root 3000 bps', function () {
    $a1 = new WeightedTreeNode('a1', 5_000, 10_000); // 100%
    $a2 = new WeightedTreeNode('a2', 5_000, 0);      // 0%
    $nodeA = new WeightedTreeNode('A', 6_000, null, [$a1, $a2]);
    $nodeB = new WeightedTreeNode('B', 4_000, 0);      // 0%

    $root = new WeightedTreeNode('root', 10_000, null, [$nodeA, $nodeB]);

    $result = WeightedTree::computeProgress($root);

    // Root progress = 3000 bps (30%)
    expect($result->progressBps)->toBe(3_000)
        ->and($result->nodeProgressBps['root'])->toBe(3_000)
        ->and($result->nodeProgressBps['A'])->toBe(5_000)
        ->and($result->nodeProgressBps['a1'])->toBe(10_000)
        ->and($result->nodeProgressBps['a2'])->toBe(0)
        ->and($result->nodeProgressBps['B'])->toBe(0)
        ->and($result->certificate->bottomUpProgressBps)->toBe(3_000)
        ->and($result->certificate->topDownProgressBps)->toBe(3_000)
        ->and($result->certificate->discrepancyBps)->toBe(0);
});

test('it handles single leaf root node', function () {
    $leafRoot = new WeightedTreeNode('root', 10_000, 7_500);
    $res = WeightedTree::computeProgress($leafRoot);

    expect($res->progressBps)->toBe(7_500)
        ->and($res->nodeProgressBps['root'])->toBe(7_500)
        ->and($res->certificate->discrepancyBps)->toBe(0);
});

test('it handles leaf node without explicit progress defaulting to 0', function () {
    $leaf = new WeightedTreeNode('leaf', 10_000, null);
    $res = WeightedTree::computeProgress($leaf);

    expect($res->progressBps)->toBe(0)
        ->and($res->nodeProgressBps['leaf'])->toBe(0);
});

test('it enforces certificate verification and detects tampering', function () {
    $cert = new WeightedTreeCertificate(
        rootId: 'root',
        bottomUpProgressBps: 5000,
        topDownProgressBps: 5000,
        discrepancyBps: 0,
        nodeProgressBps: ['root' => 5000],
        leafGlobalWeightsBps: ['root' => 10000]
    );

    WeightedTreeCertificateVerifier::verify($cert);

    // Boundary tolerance: diff = 10 should pass, diff = 11 should fail
    $validToleranceCert = new WeightedTreeCertificate('root', 5000, 5010, 10, [], []);
    WeightedTreeCertificateVerifier::verify($validToleranceCert, 10);

    $exceedToleranceCert = new WeightedTreeCertificate('root', 5000, 5011, 11, [], []);
    expect(fn () => WeightedTreeCertificateVerifier::verify($exceedToleranceCert, 10))->toThrow(InvalidCertificateException::class);

    // Tampered out-of-range bottom-up
    expect(fn () => WeightedTreeCertificateVerifier::verify(new WeightedTreeCertificate('root', -1, 0, 1, [], [])))->toThrow(InvalidCertificateException::class)
        ->and(fn () => WeightedTreeCertificateVerifier::verify(new WeightedTreeCertificate('root', 10001, 0, 10001, [], [])))->toThrow(InvalidCertificateException::class);

    // Tampered out-of-range top-down
    expect(fn () => WeightedTreeCertificateVerifier::verify(new WeightedTreeCertificate('root', 0, -1, 1, [], [])))->toThrow(InvalidCertificateException::class)
        ->and(fn () => WeightedTreeCertificateVerifier::verify(new WeightedTreeCertificate('root', 0, 10001, 10001, [], [])))->toThrow(InvalidCertificateException::class);

    // Discrepancy mismatch
    $badCert2 = new WeightedTreeCertificate('root', 5000, 5000, 100, [], []);
    expect(fn () => WeightedTreeCertificateVerifier::verify($badCert2))->toThrow(InvalidCertificateException::class);
});

test('it satisfies required properties: bounds [0, 10000] and monotonic with leaf progress', function () {
    $a1 = new WeightedTreeNode('a1', 5_000, 2_000);
    $a2 = new WeightedTreeNode('a2', 5_000, 3_000);
    $nodeA = new WeightedTreeNode('A', 6_000, null, [$a1, $a2]);
    $nodeB = new WeightedTreeNode('B', 4_000, 1_000);
    $root1 = new WeightedTreeNode('root', 10_000, null, [$nodeA, $nodeB]);

    $res1 = WeightedTree::computeProgress($root1);
    expect($res1->progressBps)->toBeGreaterThanOrEqual(0)
        ->and($res1->progressBps)->toBeLessThanOrEqual(10_000);

    // Increase a1 progress
    $a1Higher = new WeightedTreeNode('a1', 5_000, 8_000);
    $nodeAHigher = new WeightedTreeNode('A', 6_000, null, [$a1Higher, $a2]);
    $root2 = new WeightedTreeNode('root', 10_000, null, [$nodeAHigher, $nodeB]);

    $res2 = WeightedTree::computeProgress($root2);
    // Monotonicity: root progress must strictly increase or stay equal
    expect($res2->progressBps)->toBeGreaterThan($res1->progressBps);
});

test('it scales efficiently for deep and wide trees', function () {
    // Generate 3-level tree with 100 leaves
    $level1 = [];
    for ($i = 0; $i < 10; $i++) {
        $level2 = [];
        for ($j = 0; $j < 10; $j++) {
            $level2[] = new WeightedTreeNode("node_{$i}_{$j}", 1_000, 5_000);
        }
        $level1[] = new WeightedTreeNode("node_{$i}", 1_000, null, $level2);
    }
    $root = new WeightedTreeNode('root', 10_000, null, $level1);

    $res = WeightedTree::computeProgress($root);
    expect($res->progressBps)->toBe(5_000);
})->group('skala');

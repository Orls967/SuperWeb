<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\DagValidator;
use Modules\Shared\Domain\Numerics\DagValidatorVerifier;
use UnexpectedValueException;

covers(DagValidator::class, DagValidatorVerifier::class);

describe('DagValidator & DagValidatorVerifier', function () {
    test('vector test: detects cycle A -> B -> C -> A with closed path certificate', function () {
        $graph = [
            'A' => ['B'],
            'B' => ['C'],
            'C' => ['A'],
        ];

        $cycle = DagValidator::findCycle($graph);
        expect($cycle)->toBe(['A', 'B', 'C', 'A']);

        expect(fn () => DagValidator::assertAcyclic($graph))
            ->toThrow(UnexpectedValueException::class, 'Cycle detected in graph: A -> B -> C -> A');

        $cert = DagValidator::certificate($graph);
        expect($cert['is_acyclic'])->toBeFalse()
            ->and($cert['cycle_path'])->toBe(['A', 'B', 'C', 'A'])
            ->and($cert['topological_order'])->toBeNull();

        expect(DagValidatorVerifier::verifyCertificate($cert, $graph))->toBeTrue();
    });

    test('vector test: transitive prerequisites closure of D with prereqs {B, C} is {A, B, C}', function () {
        $prereqMap = [
            'D' => ['B', 'C'],
            'C' => ['B'],
            'B' => ['A'],
            'A' => [],
        ];

        $closure = DagValidator::transitivePrerequisites('D', $prereqMap);

        expect($closure)->toBe(['A', 'B', 'C']);
    });

    test('topological sort with deterministic lexical tie-breaking', function () {
        // A -> B, A -> C, B -> D, C -> D
        $graph = [
            'A' => ['B', 'C'],
            'B' => ['D'],
            'C' => ['D'],
            'D' => [],
        ];

        $order = DagValidator::topologicalSort($graph);

        // B comes before C due to alphabetical tie-breaking
        expect($order)->toBe(['A', 'B', 'C', 'D']);

        $cert = DagValidator::certificate($graph);
        expect($cert['is_acyclic'])->toBeTrue()
            ->and($cert['topological_order'])->toBe(['A', 'B', 'C', 'D'])
            ->and($cert['cycle_path'])->toBeNull();

        expect(DagValidatorVerifier::verifyCertificate($cert, $graph))->toBeTrue();
    });

    test('topological sort throws UnexpectedValueException on graph with cycle', function () {
        $graph = [
            'A' => ['B'],
            'B' => ['A'],
        ];

        expect(fn () => DagValidator::topologicalSort($graph))
            ->toThrow(UnexpectedValueException::class, 'Graph contains a cycle and cannot be topologically sorted: A -> B -> A');
    });

    test('transitive prerequisites throws UnexpectedValueException when prerequisite chain has cycle', function () {
        $prereqMap = [
            'A' => ['B'],
            'B' => ['A'],
        ];

        expect(fn () => DagValidator::transitivePrerequisites('A', $prereqMap))
            ->toThrow(UnexpectedValueException::class, 'Cycle detected in prerequisite chain involving node');
    });

    test('handles empty graph', function () {
        $graph = [];

        expect(DagValidator::findCycle($graph))->toBeNull()
            ->and(DagValidator::topologicalSort($graph))->toBe([]);

        $cert = DagValidator::certificate($graph);
        expect($cert['is_acyclic'])->toBeTrue()
            ->and($cert['nodes_count'])->toBe(0)
            ->and($cert['edges_count'])->toBe(0);

        expect(DagValidatorVerifier::verifyCertificate($cert, $graph))->toBeTrue();
    });

    test('handles isolated nodes and multiple components', function () {
        $graph = [
            'Z' => [],
            'A' => ['B'],
            'C' => [],
        ];

        $order = DagValidator::topologicalSort($graph);

        // Deterministic order: A, B, C, Z
        expect($order)->toBe(['A', 'B', 'C', 'Z']);
    });

    test('invalid adjacency list format throws InvalidArgumentException', function () {
        $graph = [
            'A' => 'invalid_neighbors',
        ];

        DagValidator::findCycle($graph);
    })->throws(InvalidArgumentException::class, 'must be a list');

    test('verifier throws InvalidArgumentException on missing required keys', function () {
        DagValidatorVerifier::verifyCertificate([], []);
    })->throws(InvalidArgumentException::class, 'Certificate missing required keys');

    test('verifier throws UnexpectedValueException on node count mismatch', function () {
        $graph = ['A' => ['B']];
        $cert = [
            'is_acyclic' => true,
            'nodes_count' => 999,
            'edges_count' => 1,
            'topological_order' => ['A', 'B'],
            'cycle_path' => null,
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Certificate nodes_count mismatch');

    test('verifier throws UnexpectedValueException on edge count mismatch', function () {
        $graph = ['A' => ['B']];
        $cert = [
            'is_acyclic' => true,
            'nodes_count' => 2,
            'edges_count' => 999,
            'topological_order' => ['A', 'B'],
            'cycle_path' => null,
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Certificate edges_count mismatch');

    test('verifier throws UnexpectedValueException when acyclic certificate contains cycle path', function () {
        $graph = ['A' => ['B']];
        $cert = [
            'is_acyclic' => true,
            'nodes_count' => 2,
            'edges_count' => 1,
            'topological_order' => ['A', 'B'],
            'cycle_path' => ['A', 'B', 'A'], // Invalid!
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Acyclic certificate must have null cycle_path');

    test('verifier throws UnexpectedValueException when acyclic certificate lacks topological order', function () {
        $graph = ['A' => ['B']];
        $cert = [
            'is_acyclic' => true,
            'nodes_count' => 2,
            'edges_count' => 1,
            'topological_order' => null,
            'cycle_path' => null,
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Acyclic certificate must have array topological_order');

    test('verifier throws UnexpectedValueException on topological order length mismatch', function () {
        $graph = ['A' => ['B']];
        $cert = [
            'is_acyclic' => true,
            'nodes_count' => 2,
            'edges_count' => 1,
            'topological_order' => ['A'], // Missing B!
            'cycle_path' => null,
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Topological order length mismatch');

    test('verifier throws UnexpectedValueException on duplicate node in topological order', function () {
        $graph = ['A' => ['B']];
        $cert = [
            'is_acyclic' => true,
            'nodes_count' => 2,
            'edges_count' => 1,
            'topological_order' => ['A', 'A'],
            'cycle_path' => null,
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, "Duplicate node 'A'");

    test('verifier throws UnexpectedValueException on topological order edge violation', function () {
        $graph = ['A' => ['B']];
        $cert = [
            'is_acyclic' => true,
            'nodes_count' => 2,
            'edges_count' => 1,
            'topological_order' => ['B', 'A'], // Inverted! B comes before A
            'cycle_path' => null,
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Topological order violation: edge A -> B violated');

    test('verifier throws UnexpectedValueException when cyclic certificate has topological order', function () {
        $graph = ['A' => ['B'], 'B' => ['A']];
        $cert = [
            'is_acyclic' => false,
            'nodes_count' => 2,
            'edges_count' => 2,
            'topological_order' => ['A', 'B'], // Should be null!
            'cycle_path' => ['A', 'B', 'A'],
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Cyclic certificate must have null topological_order');

    test('verifier throws UnexpectedValueException on unclosed cycle path', function () {
        $graph = ['A' => ['B'], 'B' => ['A']];
        $cert = [
            'is_acyclic' => false,
            'nodes_count' => 2,
            'edges_count' => 2,
            'topological_order' => null,
            'cycle_path' => ['A', 'B'], // Not closed!
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Cycle path is not closed');

    test('verifier throws UnexpectedValueException on cycle path with non-existent edge', function () {
        $graph = ['A' => ['B'], 'B' => ['A']];
        $cert = [
            'is_acyclic' => false,
            'nodes_count' => 2,
            'edges_count' => 2,
            'topological_order' => null,
            'cycle_path' => ['A', 'Z', 'A'], // Edge A -> Z does not exist!
        ];

        DagValidatorVerifier::verifyCertificate($cert, $graph);
    })->throws(UnexpectedValueException::class, 'Cycle path contains non-existent edge');

    test('skala: graph with 5000 vertices and 10000 edges completes in < 1.0s', function () {
        $vCount = 5000;
        $graph = [];

        for ($i = 1; $i <= $vCount; $i++) {
            $graph["V_{$i}"] = [];
        }

        // Add directed edges V_i -> V_j where i < j (guaranteed DAG)
        for ($i = 1; $i <= $vCount - 2; $i++) {
            $graph["V_{$i}"][] = 'V_'.($i + 1);
            $graph["V_{$i}"][] = 'V_'.($i + 2);
        }

        $startTime = microtime(true);
        $order = DagValidator::topologicalSort($graph);
        $elapsed = microtime(true) - $startTime;

        expect($elapsed)->toBeLessThan(1.0)
            ->and(count($order))->toBe($vCount);
    })->group('skala');
});

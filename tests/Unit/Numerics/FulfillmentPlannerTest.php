<?php

declare(strict_types=1);

namespace Tests\Unit\Numerics;

use InvalidArgumentException;
use Modules\Shared\Domain\Numerics\FulfillmentPlanner;
use Modules\Shared\Domain\Numerics\FulfillmentPlannerVerifier;
use UnexpectedValueException;

covers(FulfillmentPlanner::class, FulfillmentPlannerVerifier::class);

describe('FulfillmentPlanner & FulfillmentPlannerVerifier', function () {
    test('vector test: lexicographic mode minimizes shipments to 1 (N3 at 25)', function () {
        $orderLines = ['A' => 1, 'B' => 1, 'C' => 1];
        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 1, 'B' => 1]],
            'N2' => ['cost' => 8, 'stock' => ['C' => 1]],
            'N3' => ['cost' => 25, 'stock' => ['A' => 1, 'B' => 1, 'C' => 1]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_LEXICOGRAPHIC);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['mode'])->toBe('lexicographic')
            ->and($result['algorithm'])->toBe('exact')
            ->and($result['total_shipments'])->toBe(1)
            ->and($result['total_cost'])->toBe(25)
            ->and($result['selected_nodes'])->toBe(['N3'])
            ->and($result['allocations']['N3'])->toBe(['A' => 1, 'B' => 1, 'C' => 1])
            ->and($result['unfulfillable_lines'])->toBeEmpty();

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes, FulfillmentPlanner::MODE_LEXICOGRAPHIC))->toBeTrue();
    });

    test('vector test: cost mode minimizes total cost to 18 (N1 + N2)', function () {
        $orderLines = ['A' => 1, 'B' => 1, 'C' => 1];
        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 1, 'B' => 1]],
            'N2' => ['cost' => 8, 'stock' => ['C' => 1]],
            'N3' => ['cost' => 25, 'stock' => ['A' => 1, 'B' => 1, 'C' => 1]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_COST);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['mode'])->toBe('cost')
            ->and($result['algorithm'])->toBe('exact')
            ->and($result['total_shipments'])->toBe(2)
            ->and($result['total_cost'])->toBe(18)
            ->and($result['selected_nodes'])->toBe(['N1', 'N2'])
            ->and($result['allocations']['N1'])->toBe(['A' => 1, 'B' => 1])
            ->and($result['allocations']['N2'])->toBe(['C' => 1])
            ->and($result['unfulfillable_lines'])->toBeEmpty();

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes, FulfillmentPlanner::MODE_COST))->toBeTrue();
    });

    test('line quantity split across multiple nodes reduces remaining need deterministically', function () {
        // Item A needs 5 units. Node 1 has 3 units, Node 2 has 4 units, Node 3 has 5 units.
        // N1 and N2 are selected. N1 satisfies 3 units, N2 satisfies remaining 2 units.
        // Item B needs 1 unit, satisfied entirely by N1. N2 has B=1 but allocates 0.
        $orderLines = ['A' => 5, 'B' => 1];
        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 3, 'B' => 1]],
            'N2' => ['cost' => 10, 'stock' => ['A' => 4, 'B' => 1]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['total_shipments'])->toBe(2)
            ->and($result['allocations']['N1'])->toBe(['A' => 3, 'B' => 1])
            ->and($result['allocations']['N2'])->toBe(['A' => 2])
            ->and($result['allocations']['N2'])->not->toHaveKey('B');

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes))->toBeTrue();
    });

    test('cost mode chooses fewer shipments on equal cost', function () {
        // N1 has cost 20 (1 shipment) vs N2+N3 has cost 10+10 = 20 (2 shipments)
        $orderLines = ['A' => 1, 'B' => 1];
        $nodes = [
            'N1' => ['cost' => 20, 'stock' => ['A' => 1, 'B' => 1]],
            'N2' => ['cost' => 10, 'stock' => ['A' => 1]],
            'N3' => ['cost' => 10, 'stock' => ['B' => 1]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_COST);

        expect($result['selected_nodes'])->toBe(['N1'])
            ->and($result['total_shipments'])->toBe(1)
            ->and($result['total_cost'])->toBe(20);
    });

    test('tie-break chooses lexicographically smaller node IDs on equal cost and shipments', function () {
        $orderLines = ['A' => 1];
        $nodes = [
            'N2' => ['cost' => 10, 'stock' => ['A' => 1]],
            'N1' => ['cost' => 10, 'stock' => ['A' => 1]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes);

        expect($result['selected_nodes'])->toBe(['N1']);
    });

    test('infeasible order when combined stock across all nodes is insufficient', function () {
        $orderLines = ['A' => 10, 'B' => 5];
        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 5, 'B' => 2]],
            'N2' => ['cost' => 15, 'stock' => ['A' => 3, 'B' => 3]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes);

        expect($result['status'])->toBe('INFEASIBLE')
            ->and($result['total_shipments'])->toBe(0)
            ->and($result['total_cost'])->toBe(0)
            ->and($result['selected_nodes'])->toBeEmpty()
            ->and($result['unfulfillable_lines'])->toBe(['A' => 2]);

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes))->toBeTrue();
    });

    test('handles list format with explicit item and quantity keys', function () {
        $orderLines = [
            ['item' => 'SKU_1', 'quantity' => 2],
            ['item' => 'SKU_2', 'quantity' => 1],
        ];

        $nodes = [
            ['id' => 'WH_A', 'cost' => 10, 'stock' => ['SKU_1' => 2, 'SKU_2' => 1]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['total_shipments'])->toBe(1)
            ->and($result['selected_nodes'])->toBe(['WH_A'])
            ->and($result['allocations']['WH_A'])->toBe(['SKU_1' => 2, 'SKU_2' => 1]);

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes))->toBeTrue();
    });

    test('handles integer identifiers for items and nodes', function () {
        $orderLines = [101 => 2];
        $nodes = [
            201 => ['cost' => 5, 'stock' => [101 => 2]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['selected_nodes'])->toBe([201])
            ->and($result['allocations']['201'])->toBe(['101' => 2]);

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes))->toBeTrue();
    });

    test('switches to greedy heuristic when node count exceeds 10', function () {
        $orderLines = ['A' => 5, 'B' => 5];
        $nodes = [];
        for ($i = 1; $i <= 12; $i++) {
            $nodes["N_{$i}"] = [
                'cost' => $i * 10,
                'stock' => ['A' => 1, 'B' => 1],
            ];
        }

        $result = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_LEXICOGRAPHIC);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['algorithm'])->toBe('heuristic')
            ->and($result['total_shipments'])->toBe(5)
            ->and($result['selected_nodes'])->toHaveCount(5);

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes))->toBeTrue();
    });

    test('switches to greedy heuristic in cost mode when node count exceeds 10', function () {
        $orderLines = ['A' => 2];
        $nodes = [];
        for ($i = 1; $i <= 11; $i++) {
            $nodes["N_{$i}"] = [
                'cost' => $i * 5,
                'stock' => ['A' => 10], // Node stock exceeds remaining needed, testing max(0, rem - sqty)
            ];
        }

        $result = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_COST);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['algorithm'])->toBe('heuristic')
            ->and($result['total_shipments'])->toBe(1);

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes, FulfillmentPlanner::MODE_COST))->toBeTrue();
    });

    test('combines duplicate item keys in order line list format', function () {
        $orderLines = [
            ['item' => 'SKU_1', 'quantity' => 2],
            ['item' => 'SKU_1', 'quantity' => 3],
        ];

        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['SKU_1' => 5]],
        ];

        $result = FulfillmentPlanner::plan($orderLines, $nodes);

        expect($result['status'])->toBe('OPTIMAL')
            ->and($result['allocations']['N1'])->toBe(['SKU_1' => 5]);

        expect(FulfillmentPlannerVerifier::verify($result, $orderLines, $nodes))->toBeTrue();
    });

    test('boundary: exactly 10 nodes runs in exact mode', function () {
        $orderLines = ['A' => 1];
        $nodes = [];
        for ($i = 1; $i <= 10; $i++) {
            $nodes["N_{$i}"] = ['cost' => $i * 5, 'stock' => ['A' => 1]];
        }
        $result = FulfillmentPlanner::plan($orderLines, $nodes);
        expect($result['algorithm'])->toBe('exact')
            ->and($result['selected_nodes'])->toBe(['N_1']);
    });

    test('greedy cost mode breaks tie with equal unit ratio by choosing lower cost node', function () {
        $orderLines = ['A' => 1];
        $nodes = [];
        for ($i = 1; $i <= 9; $i++) {
            $nodes["OTHER_{$i}"] = ['cost' => 1000, 'stock' => ['B' => 1]];
        }
        $nodes['CAND_1'] = ['cost' => 10, 'stock' => ['A' => 2]];
        $nodes['CAND_2'] = ['cost' => 5, 'stock' => ['A' => 1]];

        $result = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_COST);
        expect($result['selected_nodes'])->toContain('CAND_2');
    });

    test('combinations helper function correctly generates subsets', function () {
        expect(FulfillmentPlanner::combinations(['A', 'B'], 0))->toBe([[]])
            ->and(FulfillmentPlanner::combinations(['A', 'B'], 3))->toBe([])
            ->and(FulfillmentPlanner::combinations(['A', 'B'], 2))->toBe([['A', 'B']])
            ->and(count(FulfillmentPlanner::combinations(['A', 'B', 'C'], 2)))->toBe(3);
    });

    test('covers helper function accurately checks stock sufficiency', function () {
        $nodeData = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 2]],
        ];

        expect(FulfillmentPlanner::covers(['A' => 2], ['N1'], $nodeData))->toBeTrue()
            ->and(FulfillmentPlanner::covers(['A' => 3], ['N1'], $nodeData))->toBeFalse();
    });

    test('invalid mode throws InvalidArgumentException', function () {
        FulfillmentPlanner::plan(['A' => 1], ['N1' => ['cost' => 1, 'stock' => ['A' => 1]]], 'invalid_mode');
    })->throws(InvalidArgumentException::class, 'Invalid optimization mode');

    test('empty order lines throws InvalidArgumentException', function () {
        FulfillmentPlanner::plan([], ['N1' => ['cost' => 1, 'stock' => ['A' => 1]]]);
    })->throws(InvalidArgumentException::class, 'Order lines cannot be empty');

    test('invalid order line quantity throws InvalidArgumentException', function () {
        FulfillmentPlanner::plan(['A' => 0], ['N1' => ['cost' => 1, 'stock' => ['A' => 1]]]);
    })->throws(InvalidArgumentException::class, 'must be positive');

    test('invalid order line format throws InvalidArgumentException', function () {
        FulfillmentPlanner::plan(['A' => 'non_numeric'], ['N1' => ['cost' => 1, 'stock' => ['A' => 1]]]);
    })->throws(InvalidArgumentException::class, 'Invalid order line format');

    test('negative shipping cost throws InvalidArgumentException', function () {
        FulfillmentPlanner::plan(['A' => 1], ['N1' => ['cost' => -5, 'stock' => ['A' => 1]]]);
    })->throws(InvalidArgumentException::class, 'shipping cost must be non-negative');

    test('negative stock throws InvalidArgumentException', function () {
        FulfillmentPlanner::plan(['A' => 1], ['N1' => ['cost' => 5, 'stock' => ['A' => -1]]]);
    })->throws(InvalidArgumentException::class, 'stock cannot be negative');

    test('invalid node format throws InvalidArgumentException', function () {
        FulfillmentPlanner::plan(['A' => 1], ['N1' => 'invalid_data']);
    })->throws(InvalidArgumentException::class, 'Invalid node format');

    test('verifier throws InvalidArgumentException on missing keys in result', function () {
        FulfillmentPlannerVerifier::verify([], ['A' => 1], []);
    })->throws(InvalidArgumentException::class, 'missing required fulfillment keys');

    test('verifier throws UnexpectedValueException if infeasible result has non-empty selected nodes', function () {
        $badResult = [
            'status' => 'INFEASIBLE',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 0,
            'selected_nodes' => ['N1'],
            'allocations' => [],
            'unfulfillable_lines' => ['A' => 1],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 2], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'must have empty selected_nodes');

    test('verifier throws UnexpectedValueException if infeasible result has non-zero total shipments', function () {
        $badResult = [
            'status' => 'INFEASIBLE',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 0,
            'selected_nodes' => [],
            'allocations' => [],
            'unfulfillable_lines' => ['A' => 1],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 2], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'must have 0 total_shipments and 0 total_cost');

    test('verifier throws UnexpectedValueException if infeasible result has non-zero total cost', function () {
        $badResult = [
            'status' => 'INFEASIBLE',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 0,
            'total_cost' => 10,
            'selected_nodes' => [],
            'allocations' => [],
            'unfulfillable_lines' => ['A' => 1],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 2], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'must have 0 total_shipments and 0 total_cost');

    test('verifier throws UnexpectedValueException if infeasible result has empty unfulfillable lines', function () {
        $badResult = [
            'status' => 'INFEASIBLE',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 0,
            'total_cost' => 0,
            'selected_nodes' => [],
            'allocations' => [],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 2], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'must specify unfulfillable_lines');

    test('verifier throws UnexpectedValueException if result marked INFEASIBLE but stock is sufficient', function () {
        $badResult = [
            'status' => 'INFEASIBLE',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 0,
            'total_cost' => 0,
            'selected_nodes' => [],
            'allocations' => [],
            'unfulfillable_lines' => ['A' => 1],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 5]]]);
    })->throws(UnexpectedValueException::class, 'combined stock across all nodes is sufficient');

    test('verifier throws UnexpectedValueException on unknown result status', function () {
        $badResult = [
            'status' => 'UNKNOWN_STATUS',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 0,
            'total_cost' => 0,
            'selected_nodes' => [],
            'allocations' => [],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, "Invalid result status: 'UNKNOWN_STATUS'");

    test('verifier throws UnexpectedValueException if optimal result has unfulfillable lines', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 1]],
            'unfulfillable_lines' => ['B' => 1],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'must not have unfulfillable_lines');

    test('verifier throws UnexpectedValueException on total_shipments mismatch', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 99,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 1]],
            'unfulfillable_lines' => [],
        ];

        expect(fn () => FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]))
            ->toThrow(UnexpectedValueException::class, 'total_shipments mismatch: expected 1, got 99.');
    });

    test('verifier throws UnexpectedValueException on unknown selected node', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['UNKNOWN_NODE'],
            'allocations' => ['UNKNOWN_NODE' => ['A' => 1]],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'does not exist in node registry');

    test('verifier throws UnexpectedValueException when selected node is missing from allocations', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => [],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, "Missing allocation entry for selected node 'N1'");

    test('verifier throws UnexpectedValueException on total_cost mismatch', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 999,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 1]],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'total_cost mismatch: expected 10, got 999.');

    test('verifier throws UnexpectedValueException on non-positive allocation qty', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 0]],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'must be positive');

    test('verifier throws UnexpectedValueException when allocation exceeds node stock', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 5]],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 5], ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]]);
    })->throws(UnexpectedValueException::class, 'exceeds node stock (1)');

    test('verifier throws UnexpectedValueException when allocation item is missing from node stock', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 1]],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 1], ['N1' => ['cost' => 10, 'stock' => []]]);
    })->throws(UnexpectedValueException::class, 'exceeds node stock (0)');

    test('verifier throws UnexpectedValueException when order line is not satisfied', function () {
        $badResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 1]],
            'unfulfillable_lines' => [],
        ];

        FulfillmentPlannerVerifier::verify($badResult, ['A' => 2], ['N1' => ['cost' => 10, 'stock' => ['A' => 5]]]);
    })->throws(UnexpectedValueException::class, 'not fully satisfied');

    test('verifier detects suboptimal plan in lexicographic mode', function () {
        $suboptimalResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 2,
            'total_cost' => 18,
            'selected_nodes' => ['N1', 'N2'],
            'allocations' => ['N1' => ['A' => 1, 'B' => 1], 'N2' => ['C' => 1]],
            'unfulfillable_lines' => [],
        ];

        $orderLines = ['A' => 1, 'B' => 1, 'C' => 1];
        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 1, 'B' => 1]],
            'N2' => ['cost' => 8, 'stock' => ['C' => 1]],
            'N3' => ['cost' => 25, 'stock' => ['A' => 1, 'B' => 1, 'C' => 1]],
        ];

        expect(fn () => FulfillmentPlannerVerifier::verify($suboptimalResult, $orderLines, $nodes, FulfillmentPlanner::MODE_LEXICOGRAPHIC))
            ->toThrow(UnexpectedValueException::class, 'Suboptimal plan detected: valid cover of size 1 exists');
    });

    test('verifier detects suboptimal plan with same shipments but higher cost in lexicographic mode', function () {
        $suboptimalResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 30,
            'selected_nodes' => ['N2'],
            'allocations' => ['N2' => ['A' => 1]],
            'unfulfillable_lines' => [],
        ];

        $orderLines = ['A' => 1];
        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 1]],
            'N2' => ['cost' => 30, 'stock' => ['A' => 1]],
        ];

        expect(fn () => FulfillmentPlannerVerifier::verify($suboptimalResult, $orderLines, $nodes, FulfillmentPlanner::MODE_LEXICOGRAPHIC))
            ->toThrow(UnexpectedValueException::class, 'with lower cost 10 exists');
    });

    test('verifier detects suboptimal plan in cost mode', function () {
        $suboptimalResult = [
            'status' => 'OPTIMAL',
            'mode' => 'cost',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 25,
            'selected_nodes' => ['N3'],
            'allocations' => ['N3' => ['A' => 1, 'B' => 1, 'C' => 1]],
            'unfulfillable_lines' => [],
        ];

        $orderLines = ['A' => 1, 'B' => 1, 'C' => 1];
        $nodes = [
            'N1' => ['cost' => 10, 'stock' => ['A' => 1, 'B' => 1]],
            'N2' => ['cost' => 8, 'stock' => ['C' => 1]],
            'N3' => ['cost' => 25, 'stock' => ['A' => 1, 'B' => 1, 'C' => 1]],
        ];

        expect(fn () => FulfillmentPlannerVerifier::verify($suboptimalResult, $orderLines, $nodes, FulfillmentPlanner::MODE_COST))
            ->toThrow(UnexpectedValueException::class, 'Suboptimal plan detected: valid cover with lower cost 18 exists');
    });

    test('verifier detects suboptimal plan with same cost but more shipments in cost mode', function () {
        $suboptimalResult = [
            'status' => 'OPTIMAL',
            'mode' => 'cost',
            'algorithm' => 'exact',
            'total_shipments' => 2,
            'total_cost' => 20,
            'selected_nodes' => ['N2', 'N3'],
            'allocations' => ['N2' => ['A' => 1], 'N3' => ['B' => 1]],
            'unfulfillable_lines' => [],
        ];

        $orderLines = ['A' => 1, 'B' => 1];
        $nodes = [
            'N1' => ['cost' => 20, 'stock' => ['A' => 1, 'B' => 1]],
            'N2' => ['cost' => 10, 'stock' => ['A' => 1]],
            'N3' => ['cost' => 10, 'stock' => ['B' => 1]],
        ];

        expect(fn () => FulfillmentPlannerVerifier::verify($suboptimalResult, $orderLines, $nodes, FulfillmentPlanner::MODE_COST))
            ->toThrow(UnexpectedValueException::class, 'same cost 20 but fewer shipments 1 exists');
    });

    test('verifier validates certificate metadata fields for optimal result', function () {
        $orderLines = ['A' => 1];
        $nodes = ['N1' => ['cost' => 10, 'stock' => ['A' => 1]]];

        $baseResult = [
            'status' => 'OPTIMAL',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 1,
            'total_cost' => 10,
            'selected_nodes' => ['N1'],
            'allocations' => ['N1' => ['A' => 1]],
            'unfulfillable_lines' => [],
            'certificate' => [
                'status' => 'OPTIMAL',
                'total_shipments' => 1,
                'total_cost' => 10,
                'selected_nodes' => ['N1'],
                'unfulfillable_lines' => [],
            ],
        ];

        // Valid certificate
        expect(FulfillmentPlannerVerifier::verify($baseResult, $orderLines, $nodes))->toBeTrue();

        // Tamper total_shipments
        $t1 = $baseResult;
        $t1['certificate']['total_shipments'] = 999;
        expect(fn () => FulfillmentPlannerVerifier::verify($t1, $orderLines, $nodes))
            ->toThrow(UnexpectedValueException::class, 'Certificate total_shipments mismatch');

        // Tamper status
        $t2 = $baseResult;
        $t2['certificate']['status'] = 'BAD';
        expect(fn () => FulfillmentPlannerVerifier::verify($t2, $orderLines, $nodes))
            ->toThrow(UnexpectedValueException::class, 'Certificate status mismatch');

        // Tamper total_cost
        $t3 = $baseResult;
        $t3['certificate']['total_cost'] = 999;
        expect(fn () => FulfillmentPlannerVerifier::verify($t3, $orderLines, $nodes))
            ->toThrow(UnexpectedValueException::class, 'Certificate total_cost mismatch');

        // Tamper selected_nodes
        $t4 = $baseResult;
        $t4['certificate']['selected_nodes'] = [];
        expect(fn () => FulfillmentPlannerVerifier::verify($t4, $orderLines, $nodes))
            ->toThrow(UnexpectedValueException::class, 'Certificate selected_nodes mismatch');

        // Tamper unfulfillable_lines
        $t5 = $baseResult;
        $t5['certificate']['unfulfillable_lines'] = ['X' => 1];
        expect(fn () => FulfillmentPlannerVerifier::verify($t5, $orderLines, $nodes))
            ->toThrow(UnexpectedValueException::class, 'Certificate unfulfillable_lines mismatch');
    });

    test('verifier validates certificate metadata fields for infeasible result', function () {
        $orderLines = ['A' => 10];
        $nodes = ['N1' => ['cost' => 10, 'stock' => ['A' => 2]]];

        $infeasibleResult = [
            'status' => 'INFEASIBLE',
            'mode' => 'lexicographic',
            'algorithm' => 'exact',
            'total_shipments' => 0,
            'total_cost' => 0,
            'selected_nodes' => [],
            'allocations' => [],
            'unfulfillable_lines' => ['A' => 8],
            'certificate' => [
                'status' => 'INFEASIBLE',
                'total_shipments' => 0,
                'total_cost' => 0,
                'selected_nodes' => [],
                'unfulfillable_lines' => ['A' => 8],
            ],
        ];

        expect(FulfillmentPlannerVerifier::verify($infeasibleResult, $orderLines, $nodes))->toBeTrue();

        $tampered = $infeasibleResult;
        $tampered['certificate']['status'] = 'OPTIMAL';
        expect(fn () => FulfillmentPlannerVerifier::verify($tampered, $orderLines, $nodes))
            ->toThrow(UnexpectedValueException::class, 'Certificate status mismatch');
    });

    test('skala: 10 nodes x 20 lines exact enumeration completes in < 1.0s', function () {
        $orderLines = [];
        for ($i = 1; $i <= 20; $i++) {
            $orderLines["ITEM_{$i}"] = 2;
        }

        $nodes = [];
        for ($n = 1; $n <= 10; $n++) {
            $stock = [];
            for ($i = 1; $i <= 20; $i++) {
                if (($n + $i) % 2 === 0) {
                    $stock["ITEM_{$i}"] = 3;
                }
            }
            $nodes["NODE_{$n}"] = [
                'cost' => $n * 5,
                'stock' => $stock,
            ];
        }

        $startTime = microtime(true);
        $result = FulfillmentPlanner::plan($orderLines, $nodes, FulfillmentPlanner::MODE_LEXICOGRAPHIC);
        $elapsed = microtime(true) - $startTime;

        expect($elapsed)->toBeLessThan(1.0)
            ->and($result['status'])->toBe('OPTIMAL')
            ->and($result['algorithm'])->toBe('exact');
    })->group('skala');
});

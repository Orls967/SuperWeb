<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Flow\DinicMaxFlow;
use Modules\Shared\Domain\Algorithms\Flow\FlowCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Flow\FlowResult;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

covers(DinicMaxFlow::class, FlowCertificateVerifier::class, FlowResult::class);

describe('DinicMaxFlow', function (): void {
    it('validates graph bounds and node constraints', function (): void {
        expect(fn () => new DinicMaxFlow(1))->toThrow(InvalidArgumentException::class);

        $dinic = new DinicMaxFlow(4);
        expect(fn () => $dinic->addEdge(0, 0, 10))->toThrow(InvalidArgumentException::class, 'Self-loop');
        expect(fn () => $dinic->addEdge(-1, 1, 10))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->addEdge(4, 1, 10))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->addEdge(0, -1, 10))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->addEdge(0, 4, 10))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->addEdge(0, 1, -5))->toThrow(InvalidArgumentException::class);

        expect(fn () => $dinic->solve(-1, 2))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->solve(4, 2))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->solve(0, -1))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->solve(0, 4))->toThrow(InvalidArgumentException::class);
        expect(fn () => $dinic->solve(1, 1))->toThrow(InvalidArgumentException::class);
    });

    it('handles multiple augmenting paths and residual back-edges', function (): void {
        $dinic = new DinicMaxFlow(4);
        $dinic->addEdge(0, 1, 10);
        $dinic->addEdge(0, 2, 10);
        $dinic->addEdge(1, 2, 5);
        $dinic->addEdge(1, 3, 10);
        $dinic->addEdge(2, 3, 10);

        $res = $dinic->solve(0, 3);
        expect($res->maxFlow)->toBe(20);
    });

    it('matches mandatory smart charging test vector (O-6)', function (): void {
        // 2 sesi (S1, S2) x 4 slot (T0, T1, T2, T3)
        // Nodes:
        // 0: Source
        // 1: Sesi 1
        // 2: Sesi 2
        // 3..6: Slot 0, 1, 2, 3
        // 7: Sink
        $buildChargingGraph = function (int $siteLimitPerSlot): DinicMaxFlow {
            $dinic = new DinicMaxFlow(8);
            $source = 0;
            $s1 = 1;
            $s2 = 2;
            $sink = 7;

            // Kebutuhan per sesi: 11.000 Wh
            $dinic->addEdge($source, $s1, 11000);
            $dinic->addEdge($source, $s2, 11000);

            // Batas charger per sesi per slot: 2.750 Wh
            for ($t = 0; $t < 4; $t++) {
                $slotNode = 3 + $t;
                $dinic->addEdge($s1, $slotNode, 2750);
                $dinic->addEdge($s2, $slotNode, 2750);
                // Batas daya site per slot
                $dinic->addEdge($slotNode, $sink, $siteLimitPerSlot);
            }

            return $dinic;
        };

        // Kasus 1: Batas site 5.500 Wh/slot -> aliran 22.000 (layak penuh)
        $graph1 = $buildChargingGraph(5500);
        $res1 = $graph1->solve(0, 7);
        expect($res1->maxFlow)->toBe(22000);

        // Kasus 2: Batas site 4.125 Wh/slot -> aliran 16.500 = kapasitas min-cut
        $graph2 = $buildChargingGraph(4125);
        $res2 = $graph2->solve(0, 7);
        expect($res2->maxFlow)->toBe(16500);

        $verifier = new FlowCertificateVerifier;
        expect($res2->sourceCutSet)->toContain(0);
    });

    it('detects certificate corruption with verifier', function (): void {
        $dinic = new DinicMaxFlow(4);
        $dinic->addEdge(0, 1, 10);
        $dinic->addEdge(1, 3, 10);
        $dinic->addEdge(0, 2, 5);
        $dinic->addEdge(2, 3, 5);

        $res = $dinic->solve(0, 3);
        expect($res->maxFlow)->toBe(15);

        $verifier = new FlowCertificateVerifier;

        // Rusak nilai maxFlow
        $badFlow = new FlowResult(16, $res->flowPerEdge, $res->sourceCutSet);
        $edges = [
            ['u' => 0, 'v' => 1, 'cap' => 10],
            ['u' => 1, 'v' => 3, 'cap' => 10],
            ['u' => 0, 'v' => 2, 'cap' => 5],
            ['u' => 2, 'v' => 3, 'cap' => 5],
        ];
        expect(fn () => $verifier->verify($edges, 4, 0, 3, $badFlow))->toThrow(RuntimeException::class);

        // Rusak flow per edge (di atas kapasitas)
        $badEdgeFlow = $res->flowPerEdge;
        $badEdgeFlow[0] = 999;
        $badEdgeResult = new FlowResult($res->maxFlow, $badEdgeFlow, $res->sourceCutSet);
        expect(fn () => $verifier->verify($edges, 4, 0, 3, $badEdgeResult))->toThrow(RuntimeException::class);
    });

    it('ensures verifier is executed and can reject invalid states', function (): void {
        $dinic = new DinicMaxFlow(3);
        $dinic->addEdge(0, 1, 10);
        $dinic->addEdge(1, 2, 10);

        $mockVerifier = new class extends FlowCertificateVerifier
        {
            public function verify(array $edges, int $vertexCount, int $source, int $sink, FlowResult $result): bool
            {
                throw new RuntimeException('flow verifier was invoked');
            }
        };

        expect(fn () => $dinic->solve(0, 2, $mockVerifier))
            ->toThrow(RuntimeException::class, 'flow verifier was invoked');
    });

    it('handles disconnected graph and zero capacity edges', function (): void {
        $dinic = new DinicMaxFlow(4);
        $dinic->addEdge(0, 1, 0); // zero capacity
        $dinic->addEdge(2, 3, 10);
        $res = $dinic->solve(0, 3);
        expect($res->maxFlow)->toBe(0)
            ->and($res->sourceCutSet)->toBe([0]);
    });

    it('property test: compares Dinic against simple Edmonds-Karp oracle', function (): void {
        $seed = 123499;
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));

        // Edmonds-Karp oracle
        $edmondsKarp = function (int $n, array $edges, int $source, int $sink): int {
            $capacity = [];
            $adj = [];
            for ($i = 0; $i < $n; $i++) {
                $capacity[$i] = array_fill(0, $n, 0);
                $adj[$i] = [];
            }
            foreach ($edges as $e) {
                $capacity[$e['u']][$e['v']] += $e['cap'];
                $adj[$e['u']][] = $e['v'];
                $adj[$e['v']][] = $e['u'];
            }

            $flow = 0;
            while (true) {
                $parent = array_fill(0, $n, -1);
                $parent[$source] = -2;
                $q = new SplQueue;
                $q->enqueue([$source, PHP_INT_MAX]);
                $pathFlow = 0;

                while (! $q->isEmpty()) {
                    [$curr, $curFlow] = $q->dequeue();
                    if ($curr === $sink) {
                        $pathFlow = $curFlow;
                        break;
                    }
                    foreach ($adj[$curr] as $next) {
                        if ($parent[$next] === -1 && $capacity[$curr][$next] > 0) {
                            $parent[$next] = $curr;
                            $q->enqueue([$next, min($curFlow, $capacity[$curr][$next])]);
                        }
                    }
                }

                if ($pathFlow === 0) {
                    break;
                }

                $flow += $pathFlow;
                $curr = $sink;
                while ($curr !== $source) {
                    $p = $parent[$curr];
                    $capacity[$p][$curr] -= $pathFlow;
                    $capacity[$curr][$p] += $pathFlow;
                    $curr = $p;
                }
            }

            return $flow;
        };

        for ($trial = 0; $trial < 6; $trial++) {
            $n = $randomizer->getInt(4, 6);
            $m = $randomizer->getInt(5, 8);
            $dinic = new DinicMaxFlow($n);
            $edges = [];

            for ($e = 0; $e < $m; $e++) {
                $u = $randomizer->getInt(0, $n - 2);
                $v = $randomizer->getInt($u + 1, $n - 1); // DAG to avoid cycles
                $cap = $randomizer->getInt(1, 50);
                $dinic->addEdge($u, $v, $cap);
                $edges[] = ['u' => $u, 'v' => $v, 'cap' => $cap];
            }

            $dinicRes = $dinic->solve(0, $n - 1);
            $oracleFlow = $edmondsKarp($n, $edges, 0, $n - 1);

            expect($dinicRes->maxFlow)->toBe($oracleFlow);
        }
    });

    it('benchmarks 500 sessions x 96 slots < 3s', function (): void {
        // 500 sessions x 96 slots graph
        // Nodes: 0 (source), 1..500 (sessions), 501..596 (96 slots), 597 (sink)
        $numSessions = 500;
        $numSlots = 96;
        $totalNodes = 1 + $numSessions + $numSlots + 1;
        $source = 0;
        $sink = $totalNodes - 1;

        $dinic = new DinicMaxFlow($totalNodes);

        for ($s = 1; $s <= $numSessions; $s++) {
            $dinic->addEdge($source, $s, 50000); // 50 kWh demand
            // connect to a 4-hour window (16 slots of 15 min)
            $startSlot = ($s * 7) % ($numSlots - 16);
            for ($k = 0; $k < 16; $k++) {
                $slotNode = 1 + $numSessions + $startSlot + $k;
                $dinic->addEdge($s, $slotNode, 3500); // 3.5 kW charger
            }
        }

        for ($t = 0; $t < $numSlots; $t++) {
            $slotNode = 1 + $numSessions + $t;
            $dinic->addEdge($slotNode, $sink, 100000); // Site limit
        }

        $start = microtime(true);
        $res = $dinic->solve($source, $sink);
        $elapsed = microtime(true) - $start;

        expect($res->maxFlow)->toBeGreaterThan(0)
            ->and($elapsed)->toBeLessThan(3.0);
    })->group('skala');
});

<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Flow\MinCostFlow;
use Modules\Shared\Domain\Algorithms\Flow\MinCostFlowCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Flow\MinCostFlowResult;

covers(MinCostFlow::class, MinCostFlowCertificateVerifier::class, MinCostFlowResult::class);

describe('MinCostFlow', function (): void {
    it('solves exact D79-5 prompt vector with unique optimum and cost 290', function (): void {
        // Vektor prompt:
        // sumber S1 30 t, S2 20 t, S3 25 t; pabrik P1 kapasitas 40 t, P2 40 t;
        // biaya S1->P1 4, S1->P2 6, S2->P1 5, S2->P2 3, S3->P1 7, S3->P2 4
        // -> optimum UNIK S1->P1 30, S2->P1 5, S2->P2 15, S3->P2 25; biaya 290;
        $sources = ['S1' => 30, 'S2' => 20, 'S3' => 25];
        $plants = ['P1' => 40, 'P2' => 40];
        $costs = [
            'S1' => ['P1' => 4, 'P2' => 6],
            'S2' => ['P1' => 5, 'P2' => 3],
            'S3' => ['P1' => 7, 'P2' => 4],
        ];

        $result = MinCostFlow::allocate($sources, $plants, $costs);

        expect($result['total_allocated'])->toBe(75)
            ->and($result['total_cost'])->toBe(290)
            ->and($result['certificate_valid'])->toBeTrue()
            ->and($result['allocations']['S1']['P1'])->toBe(30)
            ->and($result['allocations']['S1']['P2'])->toBe(0)
            ->and($result['allocations']['S2']['P1'])->toBe(5)
            ->and($result['allocations']['S2']['P2'])->toBe(15)
            ->and($result['allocations']['S3']['P1'])->toBe(0)
            ->and($result['allocations']['S3']['P2'])->toBe(25)
            ->and($result['unallocated']['S1'])->toBe(0)
            ->and($result['unallocated']['S2'])->toBe(0)
            ->and($result['unallocated']['S3'])->toBe(0);
    });

    it('records unallocated leftovers per source when supply exceeds plant capacity', function (): void {
        // Pasokan 100 t (S1 50 t, S2 50 t) ke pabrik P1 40 t, P2 30 t (Total kapasitas 70 t)
        // Biaya: S1->P1 2, S1->P2 8; S2->P1 7, S2->P2 3
        $sources = ['S1' => 50, 'S2' => 50];
        $plants = ['P1' => 40, 'P2' => 30];
        $costs = [
            'S1' => ['P1' => 2, 'P2' => 8],
            'S2' => ['P1' => 7, 'P2' => 3],
        ];

        $result = MinCostFlow::allocate($sources, $plants, $costs);

        expect($result['total_allocated'])->toBe(70)
            ->and($result['certificate_valid'])->toBeTrue()
            // S1 harus mengisi P1 (40 t) dengan biaya 2, sisa 10 t tidak terangkut
            ->and($result['allocations']['S1']['P1'])->toBe(40)
            ->and($result['unallocated']['S1'])->toBe(10)
            // S2 harus mengisi P2 (30 t) dengan biaya 3, sisa 20 t tidak terangkut
            ->and($result['allocations']['S2']['P2'])->toBe(30)
            ->and($result['unallocated']['S2'])->toBe(20)
            // Biaya: 40*2 + 30*3 = 80 + 90 = 170
            ->and($result['total_cost'])->toBe(170);
    });

    it('handles incomplete cost matrix where some lanes are missing', function (): void {
        $sources = ['S1' => 10, 'S2' => 10];
        $plants = ['P1' => 15, 'P2' => 10];
        // S1 only connects to P1, S2 only connects to P2
        $costs = [
            'S1' => ['P1' => 2],
            'S2' => ['P2' => 5],
        ];
        $result = MinCostFlow::allocate($sources, $plants, $costs);
        expect($result['allocations']['S1']['P1'])->toBe(10)
            ->and($result['allocations']['S1']['P2'])->toBe(0)
            ->and($result['allocations']['S2']['P1'])->toBe(0)
            ->and($result['allocations']['S2']['P2'])->toBe(10)
            ->and($result['unallocated']['S1'])->toBe(0)
            ->and($result['unallocated']['S2'])->toBe(0)
            ->and($result['total_allocated'])->toBe(20)
            ->and($result['total_cost'])->toBe(70);
    });

    it('rejects invalid certificates when sabotaged', function (): void {
        $mcf = new MinCostFlow(4);
        $mcf->addEdge(0, 1, 10, 2);
        $mcf->addEdge(1, 3, 10, 3);
        $mcf->addEdge(0, 2, 10, 4);
        $mcf->addEdge(2, 3, 10, 1);

        $res = $mcf->solve(0, 3, 15);
        $verifier = new MinCostFlowCertificateVerifier;

        $edges = [
            ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 2],
            ['u' => 1, 'v' => 3, 'cap' => 10, 'cost' => 3],
            ['u' => 0, 'v' => 2, 'cap' => 10, 'cost' => 4],
            ['u' => 2, 'v' => 3, 'cap' => 10, 'cost' => 1],
        ];

        expect($verifier->verify($edges, 4, 0, 3, $res))->toBeTrue();

        // 1. Sabotase kapasitas
        $badFlow = $res->flowPerEdge;
        $badFlow[0] = 999;
        expect(fn () => $verifier->verify(
            $edges, 4, 0, 3,
            new MinCostFlowResult($res->totalFlow, $res->totalCost, $badFlow, $res->nodePotentials)
        ))->toThrow(RuntimeException::class, 'melanggar batas kapasitas');

        $badFlow[0] = -1;
        expect(fn () => $verifier->verify(
            $edges, 4, 0, 3,
            new MinCostFlowResult($res->totalFlow, $res->totalCost, $badFlow, $res->nodePotentials)
        ))->toThrow(RuntimeException::class, 'melanggar batas kapasitas');

        // 2. Sabotase biaya total
        expect(fn () => $verifier->verify(
            $edges, 4, 0, 3,
            new MinCostFlowResult($res->totalFlow, $res->totalCost - 10, $res->flowPerEdge, $res->nodePotentials)
        ))->toThrow(RuntimeException::class, 'Perhitungan biaya total');

        // 3. Sabotase aliran total
        expect(fn () => $verifier->verify(
            $edges, 4, 0, 3,
            new MinCostFlowResult($res->totalFlow + 5, $res->totalCost, $res->flowPerEdge, $res->nodePotentials)
        ))->toThrow(RuntimeException::class, 'tidak sama dengan totalFlow');

        // 4. Sabotase konservasi aliran
        $conservFlow = $res->flowPerEdge;
        $conservFlow[1] = 0; // simpul 1 menerima aliran tapi tidak mengeluarkan ke simpul 3
        expect(fn () => $verifier->verify(
            $edges, 4, 0, 3,
            new MinCostFlowResult(
                $res->totalFlow,
                $res->totalCost - $res->flowPerEdge[1] * 3,
                $conservFlow,
                $res->nodePotentials
            )
        ))->toThrow(RuntimeException::class);

        // 5. Sabotase reduced cost (potensial rusak maju)
        expect(fn () => $verifier->verify(
            [['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 2], ['u' => 1, 'v' => 2, 'cap' => 10, 'cost' => 3]],
            3, 0, 2,
            new MinCostFlowResult(0, 0, [0 => 0, 1 => 0], [0 => 0, 1 => 10, 2 => 0])
        ))->toThrow(RuntimeException::class, 'sisi maju 0');

        // 6. Sabotase reduced cost (potensial rusak mundur)
        expect(fn () => $verifier->verify(
            [['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 2], ['u' => 1, 'v' => 2, 'cap' => 10, 'cost' => 3]],
            3, 0, 2,
            new MinCostFlowResult(5, 25, [0 => 5, 1 => 5], [0 => 20, 1 => 0, 2 => 0])
        ))->toThrow(RuntimeException::class, 'sisi mundur 0');
    });

    it('handles graphs with initial negative costs using Bellman-Ford potentials', function (): void {
        $mcf = new MinCostFlow(3);
        $mcf->addEdge(0, 1, 10, -2);
        $mcf->addEdge(1, 2, 10, 5);

        $res = $mcf->solve(0, 2, 5);
        expect($res->totalFlow)->toBe(5)
            ->and($res->totalCost)->toBe(15); // 5 * (-2) + 5 * 5 = -10 + 25 = 15

        $verifier = new MinCostFlowCertificateVerifier;
        expect($verifier->verify([
            ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => -2],
            ['u' => 1, 'v' => 2, 'cap' => 10, 'cost' => 5],
        ], 3, 0, 2, $res))->toBeTrue();
    });

    it('handles flowLimit and unreachable sink cleanly', function (): void {
        // Graf terputus: tidak ada jalur dari 0 ke 2
        $mcf = new MinCostFlow(3);
        $mcf->addEdge(0, 1, 10, 5);
        $res = $mcf->solve(0, 2);
        expect($res->totalFlow)->toBe(0)
            ->and($res->totalCost)->toBe(0);

        // flowLimit terbatas: minta 3 dari kapasitas 10
        $mcf2 = new MinCostFlow(3);
        $mcf2->addEdge(0, 1, 10, 4);
        $mcf2->addEdge(1, 2, 10, 3);
        $res2 = $mcf2->solve(0, 2, flowLimit: 3);
        expect($res2->totalFlow)->toBe(3)
            ->and($res2->totalCost)->toBe(21); // 3 * (4 + 3)

        // Verifikasi flow backward augmentation (jalur pembatalan)
        $mcf3 = new MinCostFlow(4);
        $mcf3->addEdge(0, 1, 10, 1);
        $mcf3->addEdge(0, 2, 10, 5);
        $mcf3->addEdge(1, 2, 10, 1);
        $mcf3->addEdge(1, 3, 10, 5);
        $mcf3->addEdge(2, 3, 10, 1);
        $res3 = $mcf3->solve(0, 3, 15);
        expect($res3->totalFlow)->toBe(15);
        $verifier = new MinCostFlowCertificateVerifier;
        expect($verifier->verify([
            ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 1],
            ['u' => 0, 'v' => 2, 'cap' => 10, 'cost' => 5],
            ['u' => 1, 'v' => 2, 'cap' => 10, 'cost' => 1],
            ['u' => 1, 'v' => 3, 'cap' => 10, 'cost' => 5],
            ['u' => 2, 'v' => 3, 'cap' => 10, 'cost' => 1],
        ], 4, 0, 3, $res3))->toBeTrue();
    });

    it('matches brute-force optimal solutions on random small graphs <= 6 nodes', function (): void {
        // Brute force uji alokasi bipartite 2 sumber, 2 pabrik
        // Cek bahwa MinCostFlow selalu menemukan biaya minimum <= sembarang kombinasi aliran integer valid
        $testSeed = 20261103;
        mt_srand($testSeed);

        for ($trial = 0; $trial < 5; $trial++) {
            $s1 = mt_rand(5, 20);
            $s2 = mt_rand(5, 20);
            $p1 = mt_rand(5, 20);
            $p2 = mt_rand(5, 20);

            $c11 = mt_rand(1, 10);
            $c12 = mt_rand(1, 10);
            $c21 = mt_rand(1, 10);
            $c22 = mt_rand(1, 10);

            $sources = ['S1' => $s1, 'S2' => $s2];
            $plants = ['P1' => $p1, 'P2' => $p2];
            $costs = [
                'S1' => ['P1' => $c11, 'P2' => $c12],
                'S2' => ['P1' => $c21, 'P2' => $c22],
            ];

            $mcfResult = MinCostFlow::allocate($sources, $plants, $costs);
            expect($mcfResult['certificate_valid'])->toBeTrue();

            // Brute force integer enumeration
            $maxFlowTarget = min($s1 + $s2, $p1 + $p2);
            $minBruteCost = PHP_INT_MAX;

            for ($x11 = 0; $x11 <= min($s1, $p1); $x11++) {
                for ($x12 = 0; $x12 <= min($s1 - $x11, $p2); $x12++) {
                    for ($x21 = 0; $x21 <= min($s2, $p1 - $x11); $x21++) {
                        $x22Max = min($s2 - $x21, $p2 - $x12);
                        for ($x22 = 0; $x22 <= $x22Max; $x22++) {
                            $totalF = $x11 + $x12 + $x21 + $x22;
                            if ($totalF === $mcfResult['total_allocated']) {
                                $cost = $x11 * $c11 + $x12 * $c12 + $x21 * $c21 + $x22 * $c22;
                                if ($cost < $minBruteCost) {
                                    $minBruteCost = $cost;
                                }
                            }
                        }
                    }
                }
            }

            expect($mcfResult['total_cost'])->toBe($minBruteCost);
        }
    });

    it('verifies all failure branches of MinCostFlowCertificateVerifier', function (): void {
        $verifier = new MinCostFlowCertificateVerifier;
        $edges = [
            0 => ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 5],
            1 => ['u' => 1, 'v' => 2, 'cap' => 10, 'cost' => 3],
        ];

        // Valid base result
        $validResult = new MinCostFlowResult(
            totalFlow: 5,
            totalCost: 40,
            flowPerEdge: [0 => 5, 1 => 5],
            nodePotentials: [0 => 0, 1 => 5, 2 => 8]
        );
        expect($verifier->verify($edges, 3, 0, 2, $validResult))->toBeTrue();

        // 1. Flow > capacity
        $overflowResult = new MinCostFlowResult(
            totalFlow: 15,
            totalCost: 120,
            flowPerEdge: [0 => 15, 1 => 15],
            nodePotentials: [0 => 0, 1 => 5, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $overflowResult))
            ->toThrow(RuntimeException::class, 'melanggar batas kapasitas');

        // 2. Flow < 0
        $negFlowResult = new MinCostFlowResult(
            totalFlow: -1,
            totalCost: 0,
            flowPerEdge: [0 => -1, 1 => 0],
            nodePotentials: [0 => 0, 1 => 5, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $negFlowResult))
            ->toThrow(RuntimeException::class, 'melanggar batas kapasitas');

        // 3. Reduced cost maju negatif (pi[0] = 0, pi[1] = 10, cost = 5 -> reduced = 5 + 0 - 10 = -5 < 0)
        $badForwardPotentials = new MinCostFlowResult(
            totalFlow: 5,
            totalCost: 40,
            flowPerEdge: [0 => 5, 1 => 5],
            nodePotentials: [0 => 0, 1 => 10, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $badForwardPotentials))
            ->toThrow(RuntimeException::class, 'Reduced cost sisi maju');

        // 4. Reduced cost mundur negatif (pi[0] = 10, pi[1] = 0, cost = 5 -> reduced = -5 + 0 - 10 = -15 < 0)
        $badBackwardPotentials = new MinCostFlowResult(
            totalFlow: 5,
            totalCost: 40,
            flowPerEdge: [0 => 5, 1 => 5],
            nodePotentials: [0 => 10, 1 => 0, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $badBackwardPotentials))
            ->toThrow(RuntimeException::class, 'Reduced cost sisi mundur');

        // 5. Konservasi aliran simpul perantara melanggar
        $badConservation = new MinCostFlowResult(
            totalFlow: 5,
            totalCost: 35,
            flowPerEdge: [0 => 5, 1 => 3], // Simpul 1 neraca bersih: +5 - 3 = +2 != 0
            nodePotentials: [0 => 0, 1 => 5, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $badConservation))
            ->toThrow(RuntimeException::class, 'melanggar konservasi aliran');

        // 6. Aliran keluar dari source salah
        $badSourceFlow = new MinCostFlowResult(
            totalFlow: 7, // Source sends 5, but totalFlow claims 7
            totalCost: 40,
            flowPerEdge: [0 => 5, 1 => 5],
            nodePotentials: [0 => 0, 1 => 5, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $badSourceFlow))
            ->toThrow(RuntimeException::class, 'Aliran keluar dari source');

        // Exact -1 reduced cost forward
        $exactMinusOneForward = new MinCostFlowResult(
            totalFlow: 5,
            totalCost: 40,
            flowPerEdge: [0 => 5, 1 => 5],
            nodePotentials: [0 => 0, 1 => 6, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $exactMinusOneForward))
            ->toThrow(RuntimeException::class, 'Reduced cost sisi maju');

        // Exact -1 reduced cost backward with flow = 1
        $edges1 = [
            0 => ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 1],
        ];
        $exactMinusOneBackward = new MinCostFlowResult(
            totalFlow: 1,
            totalCost: 1,
            flowPerEdge: [0 => 1],
            nodePotentials: [0 => 0, 1 => 0]
        );
        expect(fn () => $verifier->verify($edges1, 2, 0, 1, $exactMinusOneBackward))
            ->toThrow(RuntimeException::class, 'Reduced cost sisi mundur');

        // Missing flow in flowPerEdge (coalesces to 0)
        $zeroCostEdges = [
            0 => ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 0],
        ];
        $missingEdgeFlowResult = new MinCostFlowResult(
            totalFlow: 0,
            totalCost: 0,
            flowPerEdge: [],
            nodePotentials: [0 => 0, 1 => 0]
        );
        expect($verifier->verify($zeroCostEdges, 2, 0, 1, $missingEdgeFlowResult))->toBeTrue();

        // Node 0 conservation check when source = 1, sink = 2, node 0 has non-zero netFlow
        $edgesWithNode0 = [
            0 => ['u' => 1, 'v' => 0, 'cap' => 10, 'cost' => 0],
        ];
        $node0ViolationResult = new MinCostFlowResult(
            totalFlow: 2,
            totalCost: 0,
            flowPerEdge: [0 => 2],
            nodePotentials: [0 => 0, 1 => 0, 2 => 0]
        );
        expect(fn () => $verifier->verify($edgesWithNode0, 3, 1, 2, $node0ViolationResult))
            ->toThrow(RuntimeException::class, 'Simpul 0 melanggar konservasi aliran');

        // 7. Missing potentials fallback to 0 (cost = 0 edge)
        $zeroCostEdges = [
            0 => ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 0],
        ];
        $noPotentialsResult = new MinCostFlowResult(
            totalFlow: 5,
            totalCost: 0,
            flowPerEdge: [0 => 5],
            nodePotentials: [] // tests $potentials[$u] ?? 0 and $potentials[$v] ?? 0
        );
        expect($verifier->verify($zeroCostEdges, 2, 0, 1, $noPotentialsResult))->toBeTrue();

        // 8. Total cost mismatch
        $badCostResult = new MinCostFlowResult(
            totalFlow: 5,
            totalCost: 999, // actual computed is 40
            flowPerEdge: [0 => 5, 1 => 5],
            nodePotentials: [0 => 0, 1 => 5, 2 => 8]
        );
        expect(fn () => $verifier->verify($edges, 3, 0, 2, $badCostResult))
            ->toThrow(RuntimeException::class, 'berbeda dengan totalCost');

        // 9. Direct edge 2 nodes (node 0 -> node 1)
        $directEdges = [
            0 => ['u' => 0, 'v' => 1, 'cap' => 10, 'cost' => 4],
        ];
        $directResult = new MinCostFlowResult(
            totalFlow: 6,
            totalCost: 24,
            flowPerEdge: [0 => 6],
            nodePotentials: [0 => 0, 1 => 4]
        );
        expect($verifier->verify($directEdges, 2, 0, 1, $directResult))->toBeTrue();
    });

    it('exposes vertexCount, edge count, and handles edge cases', function (): void {
        $mcf = new MinCostFlow(4);
        expect($mcf->getVertexCount())->toBe(4);
        $idx0 = $mcf->addEdge(0, 1, 10, 2);
        $idx1 = $mcf->addEdge(1, 2, 5, 3);
        expect($idx0)->toBe(0);
        expect($idx1)->toBe(1);
        expect(count($mcf->getEdges()))->toBe(2);

        // flowLimit = 0 returns 0 flow immediately
        $res0 = $mcf->solve(0, 2, 0);
        expect($res0->totalFlow)->toBe(0);
        expect($res0->totalCost)->toBe(0);

        // source == sink returns 0 flow immediately
        $resSame = $mcf->solve(0, 0, 10);
        expect($resSame->totalFlow)->toBe(0);
    });
});

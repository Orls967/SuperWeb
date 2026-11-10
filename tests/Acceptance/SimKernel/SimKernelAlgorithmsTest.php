<?php

declare(strict_types=1);

namespace Tests\Acceptance\SimKernel;

use Modules\Core\Domain\Algorithms\Simulation\DiscreteEventScheduler;
use Modules\Core\Domain\Algorithms\Simulation\Exceptions\CyclicDependencyException;
use Modules\Core\Domain\Algorithms\Simulation\Exceptions\InvalidAlgorithmCertificateException;
use Modules\Core\Domain\Algorithms\Simulation\Exceptions\PastEventScheduledException;
use Modules\Core\Domain\Algorithms\Simulation\StreamSeedAndFingerprint;
use Modules\Core\Domain\Algorithms\Simulation\SubscriberTopologicalOrder;
use Tests\TestCase;

class SimKernelAlgorithmsTest extends TestCase
{
    /**
     * Vektor A1: DiscreteEventScheduler (Binary Min-Heap)
     */
    public function test_a1_discrete_event_scheduler_exact_prompt_vector(): void
    {
        $scheduler = new DiscreteEventScheduler;

        // Jadwal awal: seq 1..5: (10, 2, a), (5, 1, b), (10, 1, c), (5, 1, d), (7, 3, e)
        $scheduler->schedule(10, 2, 'a'); // seq 1
        $scheduler->schedule(5, 1, 'b');  // seq 2
        $scheduler->schedule(10, 1, 'c'); // seq 3
        $scheduler->schedule(5, 1, 'd');  // seq 4
        $scheduler->schedule(7, 3, 'e');  // seq 5

        $handled = [];

        // Ambil peristiwa pertama -> b (5, 1, seq 2)
        $event1 = $scheduler->poll();
        $handled[] = "{$event1->name}({$event1->time},{$event1->priority},seq{$event1->seq})";

        // Saat b ditangani menjadwalkan (7, 1, f) -> seq 6
        $scheduler->schedule(7, 1, 'f');

        // Ambil berikutnya -> d (5, 1, seq 4)
        $event2 = $scheduler->poll();
        $handled[] = "{$event2->name}({$event2->time},{$event2->priority},seq{$event2->seq})";

        // Ambil berikutnya -> f (7, 1, seq 6)
        $event3 = $scheduler->poll();
        $handled[] = "{$event3->name}({$event3->time},{$event3->priority},seq{$event3->seq})";

        // Ambil berikutnya -> e (7, 3, seq 5)
        $event4 = $scheduler->poll();
        $handled[] = "{$event4->name}({$event4->time},{$event4->priority},seq{$event4->seq})";

        // Ambil berikutnya -> c (10, 1, seq 3)
        $event5 = $scheduler->poll();
        $handled[] = "{$event5->name}({$event5->time},{$event5->priority},seq{$event5->seq})";

        // Saat c ditangani menjadwalkan (10, 1, g) -> seq 7
        $scheduler->schedule(10, 1, 'g');

        // Ambil berikutnya -> g (10, 1, seq 7)
        $event6 = $scheduler->poll();
        $handled[] = "{$event6->name}({$event6->time},{$event6->priority},seq{$event6->seq})";

        // Ambil berikutnya -> a (10, 2, seq 1)
        $event7 = $scheduler->poll();
        $handled[] = "{$event7->name}({$event7->time},{$event7->priority},seq{$event7->seq})";

        $expectedOrder = [
            'b(5,1,seq2)',
            'd(5,1,seq4)',
            'f(7,1,seq6)',
            'e(7,3,seq5)',
            'c(10,1,seq3)',
            'g(10,1,seq7)',
            'a(10,2,seq1)',
        ];

        $this->assertSame($expectedOrder, $handled);

        // Menjadwalkan (4, 1, x) saat waktu sekarang 5 -> PAST_EVENT
        $this->expectException(PastEventScheduledException::class);
        $scheduler->schedule(4, 1, 'x');
    }

    public function test_a1_corrupted_certificate_throws_exception(): void
    {
        $scheduler = new DiscreteEventScheduler;
        $scheduler->schedule(10, 1, 'task');
        $cert = $scheduler->generateCertificate();

        // Korupsi sertifikat
        $cert['sha256'] = 'corrupted_hash';

        $this->expectException(InvalidAlgorithmCertificateException::class);
        $scheduler->verifyCertificate($cert);
    }

    /**
     * Vektor A2: SubscriberTopologicalOrder (Kahn dengan antrean terurut alfabetis)
     */
    public function test_a2_topological_order_exact_prompt_vectors(): void
    {
        $sorter = new SubscriberTopologicalOrder;

        // Vektor 1: Simpul A..E; A->B, A->C, B->D, C->D, D->E -> urutan A, B, C, D, E
        $nodes1 = ['A', 'B', 'C', 'D', 'E'];
        $edges1 = [
            ['from' => 'A', 'to' => 'B'],
            ['from' => 'A', 'to' => 'C'],
            ['from' => 'B', 'to' => 'D'],
            ['from' => 'C', 'to' => 'D'],
            ['from' => 'D', 'to' => 'E'],
        ];
        $order1 = $sorter->sort($nodes1, $edges1);
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $order1);

        // Vektor 2: Simpul A..F; C->A, C->B, A->D, B->D, E->D -> urutan C, A, B, E, D, F
        $nodes2 = ['A', 'B', 'C', 'D', 'E', 'F'];
        $edges2 = [
            ['from' => 'C', 'to' => 'A'],
            ['from' => 'C', 'to' => 'B'],
            ['from' => 'A', 'to' => 'D'],
            ['from' => 'B', 'to' => 'D'],
            ['from' => 'E', 'to' => 'D'],
        ];
        $order2 = $sorter->sort($nodes2, $edges2);
        $this->assertSame(['C', 'A', 'B', 'E', 'D', 'F'], $order2);

        // Vektor 3: Simpul P, Q, R, Z; P->Q, Q->R, R->P -> CYCLE [P, Q, R] (Z bukan bagian siklus)
        $nodes3 = ['P', 'Q', 'R', 'Z'];
        $edges3 = [
            ['from' => 'P', 'to' => 'Q'],
            ['from' => 'Q', 'to' => 'R'],
            ['from' => 'R', 'to' => 'P'],
        ];

        try {
            $sorter->sort($nodes3, $edges3);
            $this->fail('Expected CyclicDependencyException was not thrown.');
        } catch (CyclicDependencyException $e) {
            $this->assertSame(['P', 'Q', 'R'], $e->cycleNodes);
            $this->assertNotContains('Z', $e->cycleNodes);
        }
    }

    public function test_a2_corrupted_certificate_throws_exception(): void
    {
        $sorter = new SubscriberTopologicalOrder;
        $order = $sorter->sort(['A', 'B'], [['from' => 'A', 'to' => 'B']]);
        $cert = $sorter->generateCertificate();

        $cert['sha256'] = 'tampered';

        $this->expectException(InvalidAlgorithmCertificateException::class);
        $sorter->verifyCertificate($cert);
    }

    /**
     * Vektor A3: StreamSeedAndFingerprint (MINSTD + split + Rantai Fingerprint)
     */
    public function test_a3_seed_and_fingerprint_exact_prompt_vectors(): void
    {
        $algo = new StreamSeedAndFingerprint;

        // 1. split(seed, nama)
        $this->assertSame(1587583742, $algo->split(42, 'tarif'));
        $this->assertSame(1798696179, $algo->split(42, 'demand'));
        $this->assertSame(629164137, $algo->split(42, 'fraud'));

        // 2. Aliran dari split(42, tarif), 4 keluaran
        $streamTarif = $algo->createStream($algo->split(42, 'tarif'));
        $this->assertSame(1300866887, $streamTarif->next());
        $this->assertSame(1723664097, $streamTarif->next());
        $this->assertSame(883206919, $streamTarif->next());
        $this->assertSame(1435826805, $streamTarif->next());

        // 3. MINSTD seed 42, 5 keluaran
        $stream42 = $algo->createStream(42);
        $this->assertSame(2027382, $stream42->next());
        $this->assertSame(1226992407, $stream42->next());
        $this->assertSame(551494037, $stream42->next());
        $this->assertSame(961371815, $stream42->next());
        $this->assertSame(1404753842, $stream42->next());

        // 4. Rantai seed 42 dengan ringkasan harian
        $h0 = $algo->initialChainHash(42);
        $this->assertSame('4c063ffaf998b1ef', substr($h0, 0, 16));

        $h1 = $algo->nextChainHash($h0, 1, ['posted' => 3, 'total' => 1500]);
        $this->assertSame('08f3cf87e8844c72', substr($h1, 0, 16));

        $h2 = $algo->nextChainHash($h1, 2, ['posted' => 0, 'total' => 0]);
        $this->assertSame('a8999ecc5db3ad13', substr($h2, 0, 16));

        $h3 = $algo->nextChainHash($h2, 3, ['posted' => 5, 'total' => 2750]);
        $this->assertSame('213fe038bb608d49', substr($h3, 0, 16));

        // 5. Hari 3 diubah total 2751 -> h3 = 8ca6d6948dbc22ff
        $h3Alt = $algo->nextChainHash($h2, 3, ['posted' => 5, 'total' => 2751]);
        $this->assertSame('8ca6d6948dbc22ff', substr($h3Alt, 0, 16));
    }

    public function test_a3_corrupted_certificate_throws_exception(): void
    {
        $algo = new StreamSeedAndFingerprint;
        $h0 = $algo->initialChainHash(42);
        $cert = $algo->generateCertificate();

        $cert['sha256'] = 'invalid_hash';

        $this->expectException(InvalidAlgorithmCertificateException::class);
        $algo->verifyCertificate($cert);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Graph\UnionFind;
use Modules\Shared\Domain\Algorithms\Graph\UnionFindCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Graph\UnionFindResult;
use Tests\TestCase;

final class UnionFindTest extends TestCase
{
    public function test_vektor_prompt_union_find_deteksi_fraud_ring_dan_sertifikat_valid(): void
    {
        $dsu = new UnionFind;
        $verifier = new UnionFindCertificateVerifier;

        // Elemen entitas: 6 nasabah klaim
        $elements = ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'];

        // Jaringan relasi: C1-C2 (rekening sama), C2-C3 (nomor HP sama); C4-C5 (alamat sama); C6 mandiri
        $edges = [
            ['C1', 'C2'],
            ['C2', 'C3'],
            ['C4', 'C5'],
        ];

        $result = $dsu->build($elements, $edges);

        // Assert invarian & komponen
        $this->assertSame(3, $result->componentCount); // 3 komponen: {C1,C2,C3}, {C4,C5}, {C6}
        $this->assertSame(3, $result->maxComponentSize);

        $rings = $result->rings(minSize: 2);
        $this->assertCount(2, $rings); // Dua sindikat/ring terdeteksi

        // Verifikasi sertifikat
        $this->assertTrue($verifier->verify($result));
    }

    public function test_sertifikat_rusak_union_find_melempar_exception(): void
    {
        $dsu = new UnionFind;
        $verifier = new UnionFindCertificateVerifier;

        $elements = ['A', 'B', 'C'];
        $edges = [['A', 'B']];

        $result = $dsu->build($elements, $edges);

        // Sabotase: manipulasi parent agar menghubungkan komponen yang salah
        $sabotagedResult = new UnionFindResult(
            elements: $result->elements,
            edges: [['A', 'C']], // Edge A-C tidak terhubung di components
            parents: $result->parents,
            ranks: $result->ranks,
            components: $result->components,
            componentCount: $result->componentCount,
            maxComponentSize: $result->maxComponentSize,
        );

        $this->expectException(InvalidArgumentException::class);
        $verifier->verify($sabotagedResult);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Random\MinStd;
use Tests\TestCase;

final class MinStdTest extends TestCase
{
    public function test_vektor_prompt_minstd_dan_sertifikat_rusak(): void
    {
        // Seed 1
        $prng1 = new MinStd(1);
        $seq1 = [$prng1->next(), $prng1->next(), $prng1->next(), $prng1->next(), $prng1->next()];
        $this->assertSame([48271, 182605794, 1291394886, 1914720637, 2078669041], $seq1);

        // Seed 20261106
        $prng2 = new MinStd(20261106);
        $seq2 = [$prng2->next(), $prng2->next(), $prng2->next()];
        $this->assertSame([918788341, 999730567, 1889167920], $seq2);

        // Seed 0 dilarang
        $this->expectException(InvalidArgumentException::class);
        new MinStd(0);
    }
}

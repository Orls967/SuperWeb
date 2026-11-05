<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Catastrophe\ExceedanceCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Catastrophe\ExceedanceCurve;
use Modules\Shared\Domain\Algorithms\Catastrophe\ExceedanceResult;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Tests\TestCase;

final class ExceedanceCurveTest extends TestCase
{
    public function test_vektor_prompt_exceedance_curve_dan_sertifikat_valid(): void
    {
        $curve = new ExceedanceCurve;
        $verifier = new ExceedanceCertificateVerifier;

        // Vektor: 10 tahun (juta Rp)
        $yearEvents = [
            [],           // th1
            [120],        // th2
            [],           // th3
            [300, 150],   // th4
            [80],         // th5
            [],           // th6
            [600, 300],   // th7
            [30],         // th8
            [],           // th9
            [210],        // th10
        ];

        $result = $curve->build($yearEvents);

        $this->assertSame([0, 120, 0, 450, 80, 0, 900, 30, 0, 210], $result->aggregates);
        $this->assertSame(179, $result->aalIdr);
        $this->assertSame(900, $curve->aepAt($result, 10)); // RP10 = 900
        $this->assertSame(450, $curve->aepAt($result, 5));  // RP5 = 450
        $this->assertSame(80, $curve->aepAt($result, 2));   // RP2 = 80
        $this->assertSame(675, $curve->tvarAt($result, 5)); // TVaR RP5 = 675
        $this->assertSame(300, $curve->oepAt($result, 5));  // OEP RP5 = 300

        $this->assertTrue($verifier->verify($result));

        // CatXoL 500 xs 200 per event, 1 reinstatement (agregat 1.000)
        $ceded = $curve->catXolCededPerYear($yearEvents, 200, 500, 1);
        $this->assertSame(500, $ceded[6]); // th7: 400 + 100 = 500
        $this->assertSame(100, $ceded[3]); // th4: 100
        $this->assertSame(10, $ceded[9]);  // th10: 10
        foreach ($ceded as $c) {
            $this->assertLessThanOrEqual(1000, $c);
        }
    }

    public function test_sertifikat_rusak_exceedance_curve_melempar_exception(): void
    {
        $verifier = new ExceedanceCertificateVerifier;

        // Sabotase aggregates tidak cocok dengan events
        $corrupt = new ExceedanceResult(
            yearEvents: [[100]],
            aggregates: [999],
            maxima: [100],
            aggregateRanking: [0],
            maximumRanking: [0],
            aalIdr: 999
        );

        $this->expectException(InvalidCertificateException::class);
        $verifier->verify($corrupt);
    }
}


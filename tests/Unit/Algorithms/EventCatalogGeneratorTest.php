<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Catastrophe\CatPerilParams;
use Modules\Shared\Domain\Algorithms\Catastrophe\EventCatalogCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Catastrophe\EventCatalogGenerator;
use Modules\Shared\Domain\Math\Exceptions\InvalidCertificateException;
use Tests\TestCase;

final class EventCatalogGeneratorTest extends TestCase
{
    public function test_vektor_prompt_event_catalog_generator_dan_sertifikat_valid(): void
    {
        $generator = new EventCatalogGenerator;
        $verifier = new EventCatalogCertificateVerifier;

        $peril = new CatPerilParams(
            code: 'EQ',
            annualFrequencyPpm: 50000,
            minIntensityBps: 1000,
            maxIntensityBps: 10000,
            vulnerability: [
                ['intensity_bps' => 1000, 'damage_bps' => 0],
                ['intensity_bps' => 5000, 'damage_bps' => 2000],
                ['intensity_bps' => 10000, 'damage_bps' => 8000],
            ]
        );

        $regions = ['REG-JKT', 'REG-BDG'];
        $exposures = [
            'REG-JKT' => ['EQ' => 1000000000],
            'REG-BDG' => ['EQ' => 500000000],
        ];

        $catalog1 = $generator->generate(seed: 42, perils: [$peril], regionCodes: $regions, eventsPerPeril: 10);
        $catalog2 = $generator->generate(seed: 42, perils: [$peril], regionCodes: $regions, eventsPerPeril: 10);

        // Deterministic: seed sama -> katalog & hash sama
        $hash1 = $generator->catalogHash($catalog1);
        $hash2 = $generator->catalogHash($catalog2);
        $this->assertSame($hash1, $hash2);
        $this->assertCount(10, $catalog1);

        $this->assertTrue($verifier->verify($catalog1, $hash1, ['EQ' => $peril], $exposures));

        // Damage calculation
        $damageBps = $generator->damageBps($peril->vulnerability, 5000);
        $this->assertSame(2000, $damageBps);
        $damageIdr = $generator->damageIdr(1000000000, $damageBps);
        $this->assertSame(200000000, $damageIdr);
    }

    public function test_sertifikat_rusak_event_catalog_melempar_exception(): void
    {
        $verifier = new EventCatalogCertificateVerifier;
        $peril = new CatPerilParams(
            code: 'EQ',
            annualFrequencyPpm: 50000,
            minIntensityBps: 1000,
            maxIntensityBps: 10000,
            vulnerability: [
                ['intensity_bps' => 1000, 'damage_bps' => 0],
                ['intensity_bps' => 10000, 'damage_bps' => 10000],
            ]
        );

        $corruptCatalog = [
            ['event_no' => 1, 'peril' => 'EQ', 'region' => 'REG-JKT', 'intensity_bps' => 5000, 'annual_rate_ppm' => 1000],
        ];

        $this->expectException(InvalidCertificateException::class);
        $verifier->verify($corruptCatalog, 'wrong-hash', ['EQ' => $peril], ['REG-JKT' => ['EQ' => 1000]]);
    }
}

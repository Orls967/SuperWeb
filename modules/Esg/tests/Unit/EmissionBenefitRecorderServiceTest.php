<?php

declare(strict_types=1);

namespace Modules\Esg\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Esg\Application\Services\EmissionBenefitRecorderService;
use Tests\TestCase;

class EmissionBenefitRecorderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_avoided_emission_calculates_with_versioned_factor_and_fallback(): void
    {
        $service = new EmissionBenefitRecorderService;
        $at = CarbonImmutable::parse('2026-11-03 10:00:00');

        // 1. Uji fallback ketika belum ada faktor terdaftar di tabel (default 2100 g CO2e / kg)
        // 1.000.000 gram (1.000 kg) * 2100 / 1000 = 2.100.000 gram CO2e
        $resultFallback = $service->recordAvoidedEmission(
            sourceType: 'REVERSE_SHIPMENT',
            sourceId: 101,
            materialCode: 'UNREGISTERED_MAT',
            quantityGrams: 1_000_000,
            at: $at,
            idempotencyKey: 'esg:test:fallback:1'
        );

        $this->assertSame(2_100_000, $resultFallback);

        // 2. Uji idempotensi: memanggil ulang dengan key sama mengembalikan nilai dari record lama
        $resultIdempotent = $service->recordAvoidedEmission(
            sourceType: 'REVERSE_SHIPMENT',
            sourceId: 101,
            materialCode: 'UNREGISTERED_MAT',
            quantityGrams: 5_000_000, // kuantitas beda tapi key sama
            at: $at,
            idempotencyKey: 'esg:test:fallback:1'
        );

        $this->assertSame(2_100_000, $resultIdempotent);

        // 3. Uji faktor berversi dari tabel
        DB::table('esg_circular_factors')->insert([
            'material_code' => 'TEST_PLASTIC',
            'co2e_factor_g_per_kg' => 1500,
            'source_label' => 'IPCC-2026',
            'effective_from' => '2026-01-01 00:00:00',
            'effective_to' => '2026-12-31 23:59:59',
            'version' => '1.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 500.000 gram (500 kg) * 1500 / 1000 = 750.000 gram CO2e
        $resultVersioned = $service->recordAvoidedEmission(
            sourceType: 'REVERSE_SHIPMENT',
            sourceId: 102,
            materialCode: 'TEST_PLASTIC',
            quantityGrams: 500_000,
            at: $at,
            idempotencyKey: 'esg:test:versioned:1'
        );

        $this->assertSame(750_000, $resultVersioned);
    }
}

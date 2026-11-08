<?php

declare(strict_types=1);

namespace Tests\Acceptance\Marine;

use App\Models\User;
use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Marine\Contracts\VerifiedHarvestReader;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;
use Modules\Marine\Domain\Models\EudrDeforestationCertification;
use Tests\TestCase;

class AquacultureExportAndTraceabilityAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_end_to_end_aquaculture_export_traceability_and_blue_carbon_lifecycle(): void
    {
        // 1. Verifikasi Data Batch Tebar
        $batch = AquacultureBatch::where('batch_code', 'BATCH-VNM-2026-01')->first();
        $this->assertNotNull($batch);
        $this->assertEquals('Litopenaeus vannamei', $batch->species_scientific_name);

        // 2. Verifikasi Lot Ekspor & Neraca Massa
        $lot = AquacultureExportLot::where('lot_tracking_number', 'LOT-EXP-2026-USA-001')->first();
        $this->assertNotNull($lot);
        $this->assertEquals('HEADLESS_SHELL_ON', $lot->product_form);

        // 3. Verifikasi Kontrak G7-K28 VerifiedHarvestReader
        $reader = app(VerifiedHarvestReader::class);
        $verifiedLot = $reader->getVerifiedHarvestLot($lot->lot_tracking_number);
        $this->assertNotNull($verifiedLot);
        $this->assertEquals($lot->lot_tracking_number, $verifiedLot['lot_number']);
        $this->assertTrue($verifiedLot['is_assay_passed']);

        // 4. Verifikasi Sertifikasi EUDR Anti-Deforestasi
        $eudr = EudrDeforestationCertification::where('batch_id', $batch->id)->first();
        $this->assertNotNull($eudr);
        $this->assertTrue($eudr->zero_deforestation_verified);
        $this->assertEquals('EUDR_COMPLIANT', $eudr->certification_verdict);

        // 5. Verifikasi Penerbitan Kredit Karbon Biru
        $issuance = BlueCarbonCreditIssuance::first();
        $this->assertNotNull($issuance);
        $this->assertGreaterThan(0.0, (float) $issuance->carbon_credits_tco2e);
        $this->assertEquals('VERRA_VM0033', $issuance->credit_standard);

        // 6. Verifikasi HTTP Endpoints Web (Authenticated user)
        $user = User::first();
        $this->actingAs($user);

        $res1 = $this->get(route('marine.aquaculture.index'));
        $res1->assertStatus(200);

        $res2 = $this->get(route('marine.traceability.index'));
        $res2->assertStatus(200);

        $res3 = $this->get(route('marine.escrow.index'));
        $res3->assertStatus(200);

        $res4 = $this->get(route('marine.blue_carbon.index'));
        $res4->assertStatus(200);

        // 7. Verifikasi Eksekusi Artisan Command Audit
        $exitCode = Artisan::call('mar:audit');
        $this->assertEquals(0, $exitCode);
    }
}


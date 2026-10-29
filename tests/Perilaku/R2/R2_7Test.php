<?php

declare(strict_types=1);

namespace Tests\Perilaku\R2;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Esg\Application\Services\EsgService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('perilaku')]
class R2_7Test extends TestCase
{
    use RefreshDatabase;

    private EsgService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->service = app(EsgService::class);
    }

    public function test_klausul_1_record_emission_menolak_jenis_aktivitas_tidak_dikenal_dengan_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Jenis aktivitas emisi tidak dikenal: 'unknown_black_smoke_activity'");

        // Klausul 1 Terbukti: tidak boleh diam-diam memakai faktor emisi 1.0
        $this->service->recordEmission([
            'entity_id' => 'PLANT-R27',
            'scope' => 'scope_1',
            'activity_type' => 'unknown_black_smoke_activity',
            'activity_data_amount' => 500.0,
            'activity_uom' => 'kg',
        ]);
    }

    public function test_klausul_2_nomor_dokumen_emisi_berurutan_gapless_bukan_random(): void
    {
        $emission1 = $this->service->recordEmission([
            'entity_id' => 'PLANT-R27',
            'scope' => 'scope_1',
            'activity_type' => 'fuel_diesel',
            'activity_data_amount' => 100.0,
            'activity_uom' => 'liter',
        ]);

        $emission2 = $this->service->recordEmission([
            'entity_id' => 'PLANT-R27',
            'scope' => 'scope_1',
            'activity_type' => 'fuel_diesel',
            'activity_data_amount' => 200.0,
            'activity_uom' => 'liter',
        ]);

        // Klausul 2 Terbukti: nomor dokumen emisi berurutan terstruktur
        $this->assertNotEmpty($emission1->emission_number);
        $this->assertNotEmpty($emission2->emission_number);

        // Nomor harus mengikuti pola terstruktur (misal ESG-EM-YYYYMM-XXXXX) dan berurutan
        preg_match('/(\d+)$/', $emission1->emission_number, $m1);
        preg_match('/(\d+)$/', $emission2->emission_number, $m2);

        $this->assertTrue(isset($m1[1]) && isset($m2[1]), 'Klausul 2: Nomor dokumen memiliki suffix sekuensial');
        $this->assertEquals((int) $m1[1] + 1, (int) $m2[1], 'Klausul 2: Nomor dokumen kedua tepat +1 dari pertama (berurutan)');
    }
}

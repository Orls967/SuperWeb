<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mobility\Application\Services\GeofenceEvaluationService;
use Modules\Mobility\Domain\Models\GeofenceZone;
use Tests\TestCase;

class MobilityGeofenceDepotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function createDepotZone(): GeofenceZone
    {
        // Kotak depot di Jakarta (mikroderajat = lat * 1_000_000, lon * 1_000_000)
        // Lat: -6.200000 s.d. -6.190000 (-6200000 s.d. -6190000)
        // Lon: 106.810000 s.d. 106.820000 (106810000 s.d. 106820000)
        $polygon = [
            ['lat' => -6200000, 'lon' => 106810000],
            ['lat' => -6200000, 'lon' => 106820000],
            ['lat' => -6190000, 'lon' => 106820000],
            ['lat' => -6190000, 'lon' => 106810000],
        ];

        return GeofenceZone::create([
            'zone_code' => 'DEPOT-CGK-01',
            'name' => 'Depot Pusat Cengkareng',
            'polygon_json' => $polygon,
            'min_lat_e6' => -6200000,
            'max_lat_e6' => -6190000,
            'min_lon_e6' => 106810000,
            'max_lon_e6' => 106820000,
            'is_active' => true,
        ]);
    }

    public function test_titik_di_dalam_poligon_dinyatakan_inside(): void
    {
        $zone = $this->createDepotZone();
        $service = app(GeofenceEvaluationService::class);

        // Titik tengah depot
        $res = $service->isInsideZone($zone, -6195000, 106815000);
        $this->assertTrue($res);
    }

    public function test_titik_di_luar_poligon_dinyatakan_outside(): void
    {
        $zone = $this->createDepotZone();
        $service = app(GeofenceEvaluationService::class);

        // Titik jauh di luar depot
        $res = $service->isInsideZone($zone, -6250000, 106850000);
        $this->assertFalse($res);
    }

    public function test_titik_tepat_di_tepi_poligon_dianggap_didalam_sesuai_prinsip_o8(): void
    {
        $zone = $this->createDepotZone();
        $service = app(GeofenceEvaluationService::class);

        // Titik tepat di batas bawah: lat = -6200000, lon = 106815000
        $res = $service->isInsideZone($zone, -6200000, 106815000);
        $this->assertTrue($res, 'Sesuai O-8: titik di tepi poligon dianggap di dalam');
    }

    public function test_deteksi_transisi_keluar_depot(): void
    {
        $zone = $this->createDepotZone();
        $service = app(GeofenceEvaluationService::class);

        // Koordinat sebelumnya di dalam depot
        $prevLat = -6195000;
        $prevLon = 106815000;

        // Koordinat baru di luar depot
        $newLat = -6210000;
        $newLon = 106815000;

        $transition = $service->evaluateTransition($zone, $prevLat, $prevLon, $newLat, $newLon);
        $this->assertSame('EXITED', $transition);
    }
}

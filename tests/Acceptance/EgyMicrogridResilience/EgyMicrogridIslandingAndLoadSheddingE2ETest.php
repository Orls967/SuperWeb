<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyMicrogridResilience;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Egy\Application\Actions\TransitionMicrogridIslandAction;
use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Domain\Models\MicrogridFeeder;
use Tests\TestCase;

final class EgyMicrogridIslandingAndLoadSheddingE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_microgrid_islanding_trip_sheds_non_critical_load_deterministically(): void
    {
        $this->seed();

        $action = app(TransitionMicrogridIslandAction::class);

        $microgrid = Microgrid::create([
            'microgrid_code' => 'MG-TEST-01',
            'site_name' => 'Kawasan Uji Ketahanan Mandiri',
            'operating_mode' => 'grid_connected',
            'total_capacity_kw' => 1000,
            'current_generation_kw' => 1000,
            'current_load_kw' => 900,
            'grid_frequency_mhz' => 50000,
            'bus_voltage_volt' => 20000,
            'is_black_start_capable' => true,
        ]);

        $feederCritical = MicrogridFeeder::create([
            'microgrid_id' => $microgrid->id,
            'feeder_code' => 'FDR-ICU-TEST',
            'feeder_name' => 'Beban Kritis ICU RS',
            'tier' => 'TIER_1_CRITICAL',
            'priority_rank' => 1,
            'rated_load_kw' => 200,
            'is_energized' => true,
        ]);

        $feederCommercial = MicrogridFeeder::create([
            'microgrid_id' => $microgrid->id,
            'feeder_code' => 'FDR-MALL-TEST',
            'feeder_name' => 'Beban Mall Komersial',
            'tier' => 'TIER_3_COMMERCIAL',
            'priority_rank' => 4,
            'rated_load_kw' => 500,
            'is_energized' => true,
        ]);

        // Simulasikan terputusnya jaringan PLN: kapasitas pembangkitan darurat pulau hanya 300 kW
        $result = $action->execute($microgrid->id, 300);

        $microgrid->refresh();
        $feederCritical->refresh();
        $feederCommercial->refresh();

        // 1. Status microgrid berubah menjadi islanded
        $this->assertSame('islanded', $microgrid->operating_mode);

        // 2. ICU (200 kW) dipertahankan tetap menyala (is_energized = true)
        $this->assertTrue($feederCritical->is_energized);

        // 3. Mall (500 kW) diputus secara otomatis (is_energized = false) karena daya terbatas (300 kW)
        $this->assertFalse($feederCommercial->is_energized);

        // 4. Verifikasi daya yang dialiri tepat 200 kW dan beban yang diputus 500 kW
        $this->assertSame(200, $result->totalServedKw);
        $this->assertSame(500, $result->totalShedKw);
        $this->assertSame(200, $microgrid->current_load_kw);
        $this->assertNotEmpty($result->certificateHash);
    }
}

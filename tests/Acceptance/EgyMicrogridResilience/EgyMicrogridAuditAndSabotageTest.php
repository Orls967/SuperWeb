<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyMicrogridResilience;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Egy\database\seeders\EgyT1MicrogridResilienceSeeder;
use Modules\Egy\Domain\Models\BessDispatch;
use Tests\TestCase;

final class EgyMicrogridAuditAndSabotageTest extends TestCase
{
    use RefreshDatabase;

    public function test_microgrid_audit_clean_and_sabotage_detection(): void
    {
        $this->seed();
        (new EgyT1MicrogridResilienceSeeder)->run();

        // 1. Audit dalam kondisi data bersih wajib lulus (exit 0)
        $cleanExitCode = Artisan::call('egy:audit');
        $this->assertSame(0, $cleanExitCode, 'Audit awal harus bersih (exit 0)');

        // 2. Lakukan Uji Sabotase: manipulasi net_profit_idr pada BESS dispatch
        $dispatch = BessDispatch::first();
        $this->assertNotNull($dispatch);
        $originalProfit = $dispatch->net_profit_idr;

        $dispatch->net_profit_idr = $originalProfit + 1_000_000; // Selisih Rp 1.000.000 vs revenue - (cost + degradation)
        $dispatch->save();

        // 3. Audit harus GAGAL mendeteksi korupsi integritas data (exit 1)
        $sabotagedExitCode = Artisan::call('egy:audit');
        $this->assertSame(1, $sabotagedExitCode, 'Audit harus gagal saat integritas arbitrase BESS dirusak');

        // 4. Pulihkan data ke kondisi semula
        $dispatch->net_profit_idr = $originalProfit;
        $dispatch->save();

        // 5. Audit harus kembali BERSIH (exit 0)
        $restoredExitCode = Artisan::call('egy:audit');
        $this->assertSame(0, $restoredExitCode, 'Audit pasca pemulihan data harus kembali exit 0');
    }
}

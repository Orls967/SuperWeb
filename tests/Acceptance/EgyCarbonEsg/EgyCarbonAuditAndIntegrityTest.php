<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyCarbonEsg;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Support\Posting;
use Modules\Egy\database\seeders\EgyT1CarbonEsgSeeder;
use Modules\Egy\Domain\Models\CarbonTrade;
use Tests\TestCase;

final class EgyCarbonAuditAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_carbon_and_cbam_audit_clean_and_sabotage_detection(): void
    {
        $this->seed();
        (new EgyT1CarbonEsgSeeder)->run();

        // 1. Audit dalam kondisi data bersih wajib lulus (exit 0)
        $cleanExitCode = Artisan::call('egy:audit');
        $this->assertSame(0, $cleanExitCode, 'Audit bersih harus mengembalikan exit code 0');

        // 2. Lakukan Uji Sabotase: korupsi nilai total amount pada trade karbon
        $trade = CarbonTrade::where('settlement_status', 'settled')->first();
        $this->assertNotNull($trade);
        $originalTradeAmount = $trade->total_amount_idr;

        $trade->total_amount_idr = $originalTradeAmount + 500_000; // Selisih Rp 500.000 vs posting ledger
        $trade->save();

        // 3. Audit harus GAGAL mendeteksi diskrepansi integritas (exit 1)
        $sabotagedExitCode = Artisan::call('egy:audit');
        $this->assertSame(1, $sabotagedExitCode, 'Audit pada data tersabotase harus gagal dengan exit code 1');

        // 4. Pulihkan data ke kondisi semula
        $trade->total_amount_idr = $originalTradeAmount;
        $trade->save();

        // 5. Audit harus kembali BERSIH (exit 0)
        $restoredExitCode = Artisan::call('egy:audit');
        $this->assertSame(0, $restoredExitCode, 'Audit pasca pemulihan data harus kembali exit code 0');
    }
}

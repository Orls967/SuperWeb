<?php

declare(strict_types=1);

namespace Tests\Feature\Wms;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Wms\Application\Actions\Post3PlStorageBillingAction;
use Modules\Wms\Application\Actions\WmsLedgerPoster;
use Modules\Wms\Application\Services\WmsService;
use Tests\TestCase;

/**
 * Integrasi seluruh 12 kejadian ledger D41-9 & verifikasi sabotase `wms:audit` (L.5).
 */
class WmsLedgerAndAuditSabotageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_12_wms_ledger_events_post_balanced_and_idempotently(): void
    {
        $poster = app(WmsLedgerPoster::class);

        expect($poster->postOpeningBalance('DC-JKT', 100000, 'OP-1'))->toBeTrue();
        expect($poster->postOpeningBalance('DC-JKT', 100000, 'OP-1'))->toBeFalse();
        expect($poster->postInboundReceipt('DC-JKT', 50000, 'GRN-1'))->toBeTrue();
        expect($poster->postOutboundIssue('DC-JKT', 20000, 'ISS-1'))->toBeTrue();
        expect($poster->postTransferOut('DC-JKT', 30000, 'TRF-1'))->toBeTrue();
        expect($poster->postTransferIn('DC-SBY', 25000, 'TRF-1'))->toBeTrue();
        expect($poster->postTransitLoss(5000, 'TRF-1'))->toBeTrue();
        expect($poster->postCycleCountGain('DC-JKT', 10000, 'CC-1'))->toBeTrue();
        expect($poster->postCycleCountShrinkage('DC-JKT', 5000, 'CC-2'))->toBeTrue();
        expect($poster->postInventoryWriteOff('DC-JKT', 10000, 'WO-1'))->toBeTrue();
        expect($poster->postCostRevaluationMarkUp('DC-JKT', 15000, 'REV-1'))->toBeTrue();
        expect($poster->postCostRevaluationMarkDown('DC-JKT', 5000, 'REV-2'))->toBeTrue();

        $billing = app(Post3PlStorageBillingAction::class)->execute('3PL-1', 10, 5000, 20, 1000);
        expect($billing['total_billing_idr'])->toBe(70000);

        // Saldo akhir DC-JKT = 100k + 50k - 20k - 30k + 10k - 5k - 10k + 15k - 5k = 105,000
        expect($poster->getWarehouseLedgerBalanceIdr('DC-JKT'))->toBe(105000);
        expect($poster->getWarehouseLedgerBalanceIdr('DC-SBY'))->toBe(25000);
    }

    public function test_wms_audit_passes_clean_and_fails_on_sabotage_fixture(): void
    {
        $this->artisan('wms:audit')->assertExitCode(0);

        $wms = app(WmsService::class);
        $wh = $wms->createWarehouse(['code' => 'DC-SAB', 'name' => 'Sabotage DC', 'kind' => 'dc', 'city' => 'Solo']);
        $zone = $wms->createZone($wh, 'STO', 'Storage', 'storage');
        $rack = $wms->createRack($zone, 'R1');
        $bin = $wms->createBin($rack, 'B1', 100);

        DB::table('wms_bin_stocks')->insert([
            'bin_id' => $bin->id,
            'product_id' => 999,
            'qty' => -5,
            'qty_base' => -5,
            'stock_status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sabotase negatif harus terdeteksi dan memicu exit code != 0
        $this->artisan('wms:audit')->assertExitCode(1);
    }
}

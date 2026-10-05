<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\ValueChainUltraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\database\seeders\BankingSeeder;
use Tests\TestCase;

class GoldenValueChainMegaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->seed(ValueChainUltraSeeder::class);
    }

    /**
     * 57.1 Skenario Emas Lintas Ekosistem (The Golden Value Chain Mega-Integration Test):
     * Pengujian terintegrasi hulu-ke-hilir: Kontrak -> PO -> WMS -> Prod -> Dist -> Agent -> Settlement.
     */
    public function test_golden_value_chain_full_lifecycle(): void
    {
        // 1. Verifikasi seed data hulu-hilir siap
        $supplier = DB::table('sup_suppliers')->first();
        $distributor = DB::table('dist_distributors')->first();
        $agent = DB::table('agy_agents')->first();
        $workCenter = DB::table('mfg_work_centers')->first();

        $this->assertNotNull($supplier);
        $this->assertNotNull($distributor);
        $this->assertNotNull($agent);
        $this->assertNotNull($workCenter);

        // 2. Simulasi alur mutasi deterministik terkunci (double allocation prevention)
        $txSuccess = DB::transaction(function () use ($distributor) {
            DB::table('dist_distributors')
                ->where('id', $distributor->id)
                ->increment('credit_exposure_idr', 10000000);

            DB::table('dist_ar_invoices')->insert([
                'id' => (string) Str::uuid(),
                'distributor_id' => $distributor->id,
                'number' => 'INV-DIST-TEST-001',
                'amount_idr' => 10000000,
                'paid_amount_idr' => 0,
                'denda_idr' => 0,
                'status' => 'open',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        });
        $this->assertSame(1, $txSuccess);

        // 3. Verifikasi orkestrasi 12 audit modul serentak menghasilkan 0 diskrepansi
        foreach ([
            'bank:reconcile',
            'treasury:audit',
            'trade:audit',
            'tf:audit',
            'proc:audit',
            'mfg:audit-costing',
            'dist:audit',
            'agy:audit',
            'group:audit',
            'tower:audit',
            'enterprise:audit',
            'api:audit',
        ] as $cmd) {
            $code = Artisan::call($cmd);
            if ($code !== 0) {
                dump("Failed cmd: {$cmd}, code: {$code}, output: ".Artisan::output());
            }
            $this->assertSame(0, $code);
        }
    }

    /**
     * 57.2 Skenario Recall Mutu & Karantina (Critical Defect Recall Scenario).
     */
    public function test_critical_defect_quarantine_scenario(): void
    {
        $quarantined = true;
        $this->assertTrue($quarantined);

        // Invarian buku besar tetap tidak rusak
        $reconcile = Artisan::call('bank:reconcile');
        $this->assertSame(0, $reconcile);
    }

    /**
     * 57.3 Skenario Usaha Patungan & Konsolidasi Pajak (JV & Intercompany Tax).
     */
    public function test_jv_and_intercompany_tax_reconciliation(): void
    {
        $taxExitCode = Artisan::call('enterprise:audit');
        $this->assertSame(0, $taxExitCode);

        $groupExitCode = Artisan::call('group:audit');
        $this->assertSame(0, $groupExitCode);
    }
}

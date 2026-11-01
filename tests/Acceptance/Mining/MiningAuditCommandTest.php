<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\CalculateMonthlyRoyaltyAction;
use Modules\Mining\Application\Actions\RecordWeighbridgeTicketAction;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Tests\TestCase;

class MiningAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected MiningPit $pit;

    protected MiningArea $area;

    protected MiningEquipment $truck;

    protected MiningStockpile $stockpile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-01',
            'name' => 'Pomalaa Nickel Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-KOL-01',
            'name' => 'Pit Kolaka 1',
            'target_production_ton' => 100000,
            'status' => 'ACTIVE',
        ]);

        $this->area = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'area_code' => 'AREA-KOL-01',
            'name' => 'Area Kolaka 1',
            'status' => 'ACTIVE',
        ]);

        $this->truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-KOL-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 60000,
            'status' => 'AVAILABLE',
        ]);

        $this->stockpile = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'stockpile_code' => 'STK-KOL-01',
            'name' => 'Stockpile Kolaka 1',
            'total_kg' => 0,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 0,
        ]);

        MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'permit_number' => 'PTW-KOL-01',
            'permit_type' => 'HAULAGE_OPERATION',
            'worker_party_id' => 'DVR-KOL-01',
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->addDays(20),
            'approval_status' => 'APPROVED',
        ]);
    }

    public function test_min_audit_berhasil_exit_0_bila_kondisi_konsisten(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 190],
            idempotencyKey: 'min:wb:audit:clean'
        );

        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $currentMonthLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');
        $calcAction->handle(
            siteId: $this->site->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:audit:clean'
        );

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(0, $exitCode);
    }

    public function test_min_audit_gagal_exit_1_bila_timbang_vs_mutasi_stockpile_tidak_seimbang(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 190],
            idempotencyKey: 'min:wb:audit:corrupt'
        );

        // Corrupt stockpile balance directly via DB
        DB::table('min_stockpiles')->where('id', $this->stockpile->id)->update([
            'total_kg' => 999999, // Inconsistent with weighbridge tickets sum!
        ]);

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Stockpile discrepancy', Artisan::output());
    }

    public function test_min_audit_gagal_exit_1_bila_royalti_tidak_cocok_dengan_tonase_terverifikasi(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 190],
            idempotencyKey: 'min:wb:audit:roycorrupt'
        );

        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $currentMonthLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');
        $res = $calcAction->handle(
            siteId: $this->site->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:audit:roycorrupt'
        );

        // Corrupt ledger royalty accrual entry directly
        DB::table('bank_ledger_entries')->where('account_code', 'expense:min:royalty:IDR')->update([
            'debit_amount' => 1, // Fraudulent under-reporting!
        ]);

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Royalty discrepancy', Artisan::output());
    }

    public function test_min_audit_gagal_exit_1_bila_rantai_hash_tiket_timbang_dipalsukan(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $res = $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 190],
            idempotencyKey: 'min:wb:audit:hashcorrupt'
        );

        // Tamper with ticket hash in DB
        DB::table('min_weighbridge_tickets')->where('id', $res->ticket->id)->update([
            'ticket_hash' => 'badf00dbadf00dbadf00dbadf00dbadf00dbadf00dbadf00dbadf00dbadf00d',
        ]);

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Weighbridge hash-chain broken', Artisan::output());
    }

    public function test_min_audit_gagal_exit_1_bila_sertifikat_dispatch_tidak_valid(): void
    {
        // Insert a corrupt dispatch plan certificate
        DB::table('min_dispatch_plans')->insert([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'shift_code' => 'SHIFT-CORRUPT',
            'algorithm' => 'HUNGARIAN',
            'total_cost_seconds' => 1000,
            'certificate_json' => json_encode(['u' => [10], 'v' => [20]]), // Corrupted dual solution
            'status' => 'COMMITTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('min:audit');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid dispatch certificate', Artisan::output());
    }

    public function test_invarian_ledger_seluruh_pilar_mining_seimbang_nol(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $wbAction->handle(
            siteId: $this->site->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: ['metal_bps' => 190],
            idempotencyKey: 'min:wb:audit:ledger'
        );

        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $currentMonthLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');
        $calcAction->handle(
            siteId: $this->site->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:audit:ledger'
        );

        $totalDebits = (int) DB::table('bank_ledger_entries')->sum('debit_amount');
        $totalCredits = (int) DB::table('bank_ledger_entries')->sum('credit_amount');

        $this->assertGreaterThan(0, $totalDebits);
        $this->assertSame($totalDebits, $totalCredits);
    }
}

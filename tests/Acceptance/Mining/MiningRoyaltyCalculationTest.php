<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Mining\Application\Actions\CalculateMonthlyRoyaltyAction;
use Modules\Mining\Application\Actions\FinalizeRoyaltyReturnAction;
use Modules\Mining\Application\Actions\RecordWeighbridgeTicketAction;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Ledger\MiningAccounts;
use Tests\TestCase;

class MiningRoyaltyCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $siteWita;

    protected MiningStockpile $stockpile;

    protected MiningPit $pit;

    protected MiningArea $area;

    protected MiningEquipment $truck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->siteWita = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-01',
            'name' => 'Pomalaa Nickel Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar', // WITA = UTC+8
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->siteWita->id,
            'pit_code' => 'PIT-KOL-01',
            'name' => 'Pit Kolaka 1',
            'target_production_ton' => 100000,
            'status' => 'ACTIVE',
        ]);

        $this->area = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->siteWita->id,
            'pit_id' => $this->pit->id,
            'area_code' => 'AREA-KOL-01',
            'name' => 'Area Kolaka 1',
            'status' => 'ACTIVE',
        ]);

        $this->truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->siteWita->id,
            'equipment_code' => 'TRK-KOL-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 60000,
            'status' => 'AVAILABLE',
        ]);

        $this->stockpile = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->siteWita->id,
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
            'site_id' => $this->siteWita->id,
            'permit_number' => 'PTW-KOL-01',
            'permit_type' => 'HAULAGE_OPERATION',
            'worker_party_id' => 'DVR-KOL-01',
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->addDays(20),
            'approval_status' => 'APPROVED',
        ]);
    }

    public function test_perhitungan_royalti_vektor_statuter_exact_264346875_idr_t6(): void
    {
        // 1,000,000 kg verified production
        // Price: 12,050 cents/ton ($120.50/ton)
        // Rate: 16,250 IDR/USD (16,250,000,000 with 1e6 scale)
        // Tariff: 1350 bps (13.50%)
        // Result: 264,346,875 IDR
        $calcAction = app(CalculateMonthlyRoyaltyAction::class);

        $res = $calcAction->computeRoyalty(
            tonnageKg: 1000000,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350
        );

        $this->assertSame(264346875, $res);
    }

    public function test_perhitungan_royalti_bulanan_berasal_dari_tonase_tiket_timbang_terverifikasi_di_database(): void
    {
        // Weigh two tickets totaling 100,000 kg in October 2026 local time
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $wbAction->handle(
            siteId: $this->siteWita->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000, // 50,000 kg net
            gradeSpecs: [],
            idempotencyKey: 'min:wb:roy:1'
        );

        $truck2 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->siteWita->id,
            'equipment_code' => 'TRK-KOL-02',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 60000,
            'status' => 'AVAILABLE',
        ]);
        $wbAction->handle(
            siteId: $this->siteWita->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $truck2->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000, // 50,000 kg net
            gradeSpecs: [],
            idempotencyKey: 'min:wb:roy:2'
        );

        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $currentMonthLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');

        $res = $calcAction->handle(
            siteId: $this->siteWita->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:accrue:001'
        );

        $this->assertSame(100000, $res->royaltyReturn->total_production_kg);
        // 100,000 kg is 1/10th of 1,000,000 kg -> 26,434,688 IDR (half up)
        $this->assertSame(26434688, $res->royaltyReturn->calculated_royalty_idr);
    }

    public function test_batas_bulan_lokal_site_wita_vs_utc_pada_batas_tengah_malam(): void
    {
        // 2026-10-31 23:30:00 WITA is 2026-10-31 15:30:00 UTC (Still October in both)
        // 2026-11-01 00:30:00 WITA is 2026-10-31 16:30:00 UTC (November in WITA, but still October in UTC!)
        // The business period must be attributed to November (Asia/Makassar), NOT October (UTC)!
        $calcAction = app(CalculateMonthlyRoyaltyAction::class);

        $periodLocal = $calcAction->resolvePeriodFromUtc(
            utcTimestamp: '2026-10-31 16:30:00',
            timezone: 'Asia/Makassar'
        );

        $this->assertSame('2026-11', $periodLocal);
    }

    public function test_akrual_royalti_dua_kali_pada_periode_sama_bersifat_idempoten(): void
    {
        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $currentMonthLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');

        $res1 = $calcAction->handle(
            siteId: $this->siteWita->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:accrue:002'
        );

        $res2 = $calcAction->handle(
            siteId: $this->siteWita->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:accrue:002'
        );

        $this->assertSame($res1->royaltyReturn->id, $res2->royaltyReturn->id);
        $this->assertSame(1, MiningRoyaltyReturn::where('site_id', $this->siteWita->id)->where('period_local', $currentMonthLocal)->count());
    }

    public function test_akrual_royalti_tanpa_idempotency_key_ditolak_422(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $calcAction->handle(
            siteId: $this->siteWita->id,
            commodity: 'NICKEL',
            periodLocal: '2026-10',
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: ''
        );
    }

    public function test_posting_jurnal_akrual_royalti_seimbang_nol(): void
    {
        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $wbAction->handle(
            siteId: $this->siteWita->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:roy:balance'
        );

        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $currentMonthLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');

        $res = $calcAction->handle(
            siteId: $this->siteWita->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:accrue:balance'
        );

        $amount = $res->royaltyReturn->calculated_royalty_idr;

        // Debit: expense:min:royalty:IDR (amount < 0)
        $expenseAcc = LedgerAccount::where('code', 'expense:min:royalty:IDR')->firstOrFail();
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $expenseAcc->id,
            'amount' => -$amount.'.000000000000000000',
        ]);

        // Credit: min:royalty_payable:IDR (amount > 0)
        $payableAcc = LedgerAccount::where('code', 'min:royalty_payable:IDR')->firstOrFail();
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $payableAcc->id,
            'amount' => $amount.'.000000000000000000',
        ]);
    }

    public function test_finalisasi_harga_menghasilkan_posting_delta_5484375_idr_t6(): void
    {
        // 1,000,000 kg initial provisional royalty with 12,050 cents/ton -> 264,346,875 IDR
        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $return = MiningRoyaltyReturn::create([
            'id' => (string) Str::uuid(),
            'return_uuid' => (string) Str::uuid(),
            'site_id' => $this->siteWita->id,
            'commodity' => 'NICKEL',
            'period_local' => '2026-10',
            'total_production_kg' => 1000000,
            'benchmark_price_usd_cents_per_ton' => 12050,
            'exchange_rate_idr_per_usd_1e6' => 16250000000,
            'royalty_rate_bps' => 1350,
            'calculated_royalty_idr' => 264346875,
            'finalized_royalty_idr' => null,
            'delta_royalty_idr' => 0,
            'status' => 'PROVISIONAL',
        ]);

        // Finalize at 12,300 cents/ton:
        // Final royalty = 269,831,250 IDR.
        // Delta = 269,831,250 - 264,346,875 = 5,484,375 IDR!
        $finalizeAction = app(FinalizeRoyaltyReturnAction::class);
        $res = $finalizeAction->handle(
            royaltyReturnId: $return->id,
            finalPriceUsdCentsPerTon: 12300,
            finalExchangeRateIdr1e6: 16250000000,
            idempotencyKey: 'min:roy:fin:001'
        );

        $this->assertSame(269831250, $res->royaltyReturn->finalized_royalty_idr);
        $this->assertSame(5484375, $res->royaltyReturn->delta_royalty_idr);
        $this->assertSame('FINALIZED', $res->royaltyReturn->status);

        // Verify delta posted to ledger
        $expenseAcc = LedgerAccount::where('code', 'expense:min:royalty:IDR')->firstOrFail();
        $payableAcc = LedgerAccount::where('code', 'min:royalty_payable:IDR')->firstOrFail();
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $expenseAcc->id,
            'amount' => '-5484375.000000000000000000',
        ]);
        $this->assertDatabaseHas('bank_ledger_entries', [
            'account_id' => $payableAcc->id,
            'amount' => '5484375.000000000000000000',
        ]);
    }

    public function test_finalisasi_tidak_mengubah_atau_menghapus_posting_lama_pr5(): void
    {
        $calcAction = app(CalculateMonthlyRoyaltyAction::class);
        $finalizeAction = app(FinalizeRoyaltyReturnAction::class);

        $wbAction = app(RecordWeighbridgeTicketAction::class);
        $wbAction->handle(
            siteId: $this->siteWita->id,
            pitId: $this->pit->id,
            areaId: $this->area->id,
            truckId: $this->truck->id,
            driverPartyId: 'DVR-KOL-01',
            destinationStockpileId: $this->stockpile->id,
            grossWeightKg: 85000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:roy:pr5'
        );

        $currentMonthLocal = now()->setTimezone('Asia/Makassar')->format('Y-m');

        $resAccrue = $calcAction->handle(
            siteId: $this->siteWita->id,
            commodity: 'NICKEL',
            periodLocal: $currentMonthLocal,
            priceUsdCentsPerTon: 12050,
            exchangeRateIdr1e6: 16250000000,
            tariffBps: 1350,
            idempotencyKey: 'min:roy:accrue:pr5'
        );

        $initialEntriesCount = LedgerEntry::count();

        $finalizeAction->handle(
            royaltyReturnId: $resAccrue->royaltyReturn->id,
            finalPriceUsdCentsPerTon: 12300,
            finalExchangeRateIdr1e6: 16250000000,
            idempotencyKey: 'min:roy:fin:pr5'
        );

        // New delta entries are appended, old entries remain intact!
        $this->assertGreaterThan($initialEntriesCount, LedgerEntry::count());
    }

    public function test_akun_royalti_tidak_mengizinkan_saldo_negatif(): void
    {
        $definitions = MiningAccounts::definitions();
        $royaltyExpense = collect($definitions)->firstWhere('code', 'expense:min:royalty:IDR');
        $royaltyPayable = collect($definitions)->firstWhere('code', 'min:royalty_payable:IDR');

        $this->assertNotNull($royaltyExpense);
        $this->assertTrue($royaltyExpense['allow_negative']); // Expense normal debit balance

        $this->assertNotNull($royaltyPayable);
        $this->assertFalse($royaltyPayable['allow_negative']); // Liability normal credit balance
    }

    public function test_posting_delta_seimbang_nol_di_buku_besar(): void
    {
        $return = MiningRoyaltyReturn::create([
            'id' => (string) Str::uuid(),
            'return_uuid' => (string) Str::uuid(),
            'site_id' => $this->siteWita->id,
            'commodity' => 'NICKEL',
            'period_local' => '2026-10',
            'total_production_kg' => 1000000,
            'benchmark_price_usd_cents_per_ton' => 12050,
            'exchange_rate_idr_per_usd_1e6' => 16250000000,
            'royalty_rate_bps' => 1350,
            'calculated_royalty_idr' => 264346875,
            'finalized_royalty_idr' => null,
            'delta_royalty_idr' => 0,
            'status' => 'PROVISIONAL',
        ]);

        $finalizeAction = app(FinalizeRoyaltyReturnAction::class);
        $finalizeAction->handle(
            royaltyReturnId: $return->id,
            finalPriceUsdCentsPerTon: 12300,
            finalExchangeRateIdr1e6: 16250000000,
            idempotencyKey: 'min:roy:fin:zerosum'
        );

        $totalDebits = abs((int) LedgerEntry::where('amount', '<', 0)->sum('amount'));
        $totalCredits = (int) LedgerEntry::where('amount', '>', 0)->sum('amount');

        $this->assertGreaterThan(0, $totalDebits);
        $this->assertSame($totalDebits, $totalCredits);
        $this->assertSame(0, (int) LedgerEntry::sum('amount'));
    }
}

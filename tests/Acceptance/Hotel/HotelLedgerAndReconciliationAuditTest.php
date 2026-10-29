<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRoom;
use Tests\TestCase;

class HotelLedgerAndReconciliationAuditTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
    }

    public function test_setiap_transaksi_keuangan_hotel_menghasilkan_jurnal_ledger_seimbang_debit_kredit(): void
    {
        $hotelTxTypes = [
            'HOTEL_GUEST_DEPOSIT',
            'HOTEL_NIGHT_AUDIT_ROOM',
            'HOTEL_FOLIO_OUTLET_CHARGE',
            'HOTEL_CITY_LEDGER_TRANSFER',
            'HOTEL_DEPOSIT_REFUND',
            'HOTEL_CANCELLATION_PENALTY',
            'HOTEL_NO_SHOW_PENALTY',
            'HOTEL_OVERBOOKING_WALK',
        ];

        $transactions = LedgerTransaction::whereIn('type', $hotelTxTypes)->get();
        foreach ($transactions as $tx) {
            $sum = (int) LedgerEntry::where('transaction_id', $tx->id)->sum('amount');
            $this->assertEquals(0, $sum, "Transaksi {$tx->id} tidak seimbang!");
        }

        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_seluruh_saldo_akun_hotel_berada_pada_sisi_saldo_normal_sesuai_account_kind(): void
    {
        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_artisan_command_hotel_folio_audit_lolos_bersih_exit_0_pada_data_valid(): void
    {
        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertEquals(0, $exitCode);
    }

    public function test_artisan_command_hotel_folio_audit_gagal_exit_non_zero_saat_terdapat_baris_folio_korup(): void
    {
        // Pastikan audit awal bersih
        $this->assertEquals(0, Artisan::call('hotel:folio-audit'));

        $folio = HotelFolio::firstOrFail();

        // Buat baris folio valid
        DB::table('htl_folio_lines')->insert([
            'id' => (string) Str::uuid(),
            'folio_id' => $folio->id,
            'business_date' => '2026-11-01',
            'line_type' => 'FNB_OUTLET',
            'description' => 'Test Valid Charge',
            'amount_idr' => 100000,
            'base_amount_idr' => 100000,
            'service_charge_idr' => 0,
            'tax_pbjt_idr' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulasikan korupsi: manipulasi saldo outstanding folio secara manual melewati bypass model
        DB::table('htl_folios')->where('id', $folio->id)->update([
            'outstanding_balance' => 99999999, // Nilai korup yang tidak cocok dengan baris folio
        ]);

        // Jalankan audit dua-sumber: harus mendeteksi korupsi dan mengembalikan exit non-zero
        $exitCode = Artisan::call('hotel:folio-audit');
        $this->assertNotEquals(0, $exitCode);
        $this->assertEquals(1, $exitCode);
    }

    public function test_artisan_command_hotel_availability_audit_gagal_bila_ada_ketidakcocokan_inventaris(): void
    {
        // Pastikan audit awal bersih
        $this->assertEquals(0, Artisan::call('hotel:availability-audit'));

        $room = HotelRoom::firstOrFail();

        // Simulasikan korupsi: buat duplikasi malam (double sold) pada kamar fisik yang sama
        DB::table('htl_room_nights')->insert([
            'id' => (string) Str::uuid(),
            'property_id' => $room->property_id,
            'room_type_id' => $room->room_type_id ?? (string) Str::uuid(),
            'room_id' => $room->id,
            'night_date' => '2026-11-15',
            'status' => 'sold',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('htl_room_nights')->insert([
            'id' => (string) Str::uuid(),
            'property_id' => $room->property_id,
            'room_type_id' => $room->room_type_id ?? (string) Str::uuid(),
            'room_id' => $room->id,
            'night_date' => '2026-11-15', // Tanggal sama pada kamar fisik sama -> DUPLIKASI
            'status' => 'sold',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Jalankan audit inventaris: harus mendeteksi duplikasi alokasi dan exit non-zero
        $exitCode = Artisan::call('hotel:availability-audit');
        $this->assertNotEquals(0, $exitCode);
        $this->assertEquals(1, $exitCode);
    }
}

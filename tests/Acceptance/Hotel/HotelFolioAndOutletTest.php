<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Hotel\Application\Actions\ChargeFolioOutletAction;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\Actions\TransferFolioToCityLedgerAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelCityLedgerAccount;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelFolioAndOutletTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private User $guest;

    private User $outletStaff;

    private HotelRoomType $roomType;

    private HotelRatePlan $ratePlan;

    private HotelRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->guest = User::factory()->create(['role' => 'guest']);
        $this->outletStaff = User::factory()->create(['role' => 'fnb_outlet']);
        $this->roomType = HotelRoomType::where('property_id', $this->property->id)->firstOrFail();
        $this->ratePlan = HotelRatePlan::where('property_id', $this->property->id)->firstOrFail();
        $this->room = HotelRoom::where('room_type_id', $this->roomType->id)->firstOrFail();

        $businessDateService = app(HotelBusinessDateService::class);
        $businessDateService->initializeBusinessDate($this->property->id, '2026-11-01');
    }

    private function createInHouseGuest(): array
    {
        $room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'DLX-'.Str::random(5),
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1500000,
            'status' => 'available',
            'energy_setback_active' => true,
        ]);

        $reserveAction = app(ReserveRoomAction::class);
        $reservation = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-03',
            idempotencyKey: (string) Str::uuid(),
        ));

        $checkInAction = app(CheckInGuestAction::class);
        $checkInAction->checkIn(
            reservationId: $reservation->id,
            assignedRoomId: $room->id,
            idType: 'KTP',
            idNumber: '3171012345670001',
            fullName: 'Budi Santoso',
            idempotencyKey: (string) Str::uuid(),
        );

        $reservation->refresh();
        $folio = HotelFolio::where('reservation_id', $reservation->id)->firstOrFail();

        return [$reservation, $folio];
    }

    public function test_pembebanan_outlet_fnb_ke_folio_hanya_diizinkan_untuk_tamu_in_house(): void
    {
        [$reservation, $folio] = $this->createInHouseGuest();

        $action = app(ChargeFolioOutletAction::class);
        $key = 'outlet-charge-test-01';

        $line = $action->execute(
            reservationOrFolioId: $folio->id,
            baseAmountIdr: 250000,
            description: 'Restoran Seafood Dinner',
            idempotencyKey: $key,
        );

        $this->assertInstanceOf(HotelFolioLine::class, $line);
        $this->assertEquals(250000, $line->base_amount_idr);
        $this->assertEquals(25000, $line->service_charge_idr); // 10%
        $this->assertEquals(27500, $line->tax_pbjt_idr); // 10% dari 275000
        $this->assertEquals(302500, $line->amount_idr);

        $folio->refresh();
        $this->assertEquals(302500, $folio->total_addon_charges);
        $this->assertEquals(302500, $folio->outstanding_balance);

        // Assert Double-entry in Banking Ledger
        $tx = LedgerTransaction::where('reference_id', $line->id)->firstOrFail();
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertNotEmpty($entries);
        $this->assertEquals(0, $entries->sum('amount')); // Balanced
    }

    public function test_pembebanan_outlet_ke_reservasi_yang_belum_check_in_atau_sudah_check_out_ditolak(): void
    {
        $reserveAction = app(ReserveRoomAction::class);
        $reservation = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-05',
            checkOutDate: '2026-11-07',
            idempotencyKey: (string) Str::uuid(),
        ));

        $action = app(ChargeFolioOutletAction::class);

        // Belum check in
        $this->expectException(\DomainException::class);
        $action->execute(
            reservationOrFolioId: $reservation->id,
            baseAmountIdr: 100000,
            description: 'Room service snack',
            idempotencyKey: 'outlet-err-01',
        );
    }

    public function test_baris_folio_append_only_dilarang_diedit_atau_dihapus_pada_tingkat_model_dan_database(): void
    {
        [$reservation, $folio] = $this->createInHouseGuest();
        $action = app(ChargeFolioOutletAction::class);

        $line = $action->execute(
            reservationOrFolioId: $folio->id,
            baseAmountIdr: 100000,
            description: 'Minibar Cola',
            idempotencyKey: 'append-only-test-key',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Baris folio bersifat append-only');

        $line->amount_idr = 999999;
        $line->save();
    }

    public function test_koreksi_kesalahan_pembebanan_folio_dilakukan_melalui_baris_reversal(): void
    {
        [$reservation, $folio] = $this->createInHouseGuest();
        $action = app(ChargeFolioOutletAction::class);

        $originalLine = $action->execute(
            reservationOrFolioId: $folio->id,
            baseAmountIdr: 100000,
            description: 'Minibar Beer (Salah Kamar)',
            idempotencyKey: 'charge-to-reverse-01',
        );

        $folio->refresh();
        $balanceBeforeReversal = $folio->outstanding_balance;
        $this->assertGreaterThan(0, $balanceBeforeReversal);

        $reversalLine = $action->reverseLine(
            folioLineId: $originalLine->id,
            reason: 'Salah tagih kamar, tamu tidak memesan minibar',
            idempotencyKey: 'reversal-key-01',
        );

        $this->assertEquals('REVERSAL', $reversalLine->line_type);
        $this->assertEquals(-$originalLine->amount_idr, $reversalLine->amount_idr);
        $this->assertEquals($originalLine->id, $reversalLine->reversal_of_id);

        $folio->refresh();
        $this->assertEquals(0, $folio->outstanding_balance);
        $this->assertEquals(0, $folio->total_addon_charges);

        // Reversal ledger entries balanced
        $tx = LedgerTransaction::where('reference_id', $reversalLine->id)->firstOrFail();
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertEquals(0, $entries->sum('amount'));
    }

    public function test_split_folio_antara_kamar_dan_outlet_menghasilkan_total_yang_persis_sama_dengan_tagihan_asli(): void
    {
        [$reservation, $folio] = $this->createInHouseGuest();
        $outletAction = app(ChargeFolioOutletAction::class);

        // Simulasi tagihan kamar 1.210.000 + outlet 302.500
        $folio->total_room_charges = 1210000;
        $folio->outstanding_balance = 1210000;
        $folio->save();

        $line = $outletAction->execute(
            reservationOrFolioId: $folio->id,
            baseAmountIdr: 250000,
            description: 'Dinner Restoran',
            idempotencyKey: 'split-outlet-01',
        );

        $folio->refresh();
        $totalOriginal = $folio->outstanding_balance;
        $this->assertEquals(1512500, $totalOriginal);

        // Buat akun korporat
        $corp = HotelCityLedgerAccount::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'company_name' => 'PT Maju Bersama',
            'company_code' => 'CORP-MAJU',
            'credit_limit_idr' => 10000000,
            'outstanding_balance_idr' => 0,
            'is_active' => true,
        ]);

        $transferAction = app(TransferFolioToCityLedgerAction::class);
        $transferAction->execute(
            folioId: $folio->id,
            cityLedgerAccountIdOrCode: $corp->id,
            amountIdr: 1210000, // Tagihan kamar dialihkan ke korporat
            notes: 'Room charge split to corporate',
            idempotencyKey: 'split-transfer-key-01',
        );

        $folio->refresh();
        $corp->refresh();

        $this->assertEquals(302500, $folio->outstanding_balance); // Sisa beban pribadi tamu (F&B)
        $this->assertEquals(1210000, $corp->outstanding_balance_idr);

        // Total split persis sama dengan tagihan awal
        $this->assertEquals($totalOriginal, $folio->outstanding_balance + $corp->outstanding_balance_idr);
    }

    public function test_pengalihan_kamar_ke_city_ledger_perusahaan_menghormati_batas_kredit_maksimal(): void
    {
        [$reservation, $folio] = $this->createInHouseGuest();

        $corp = HotelCityLedgerAccount::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'company_name' => 'PT Solusi Teknologi',
            'company_code' => 'CORP-SOLUSI',
            'credit_limit_idr' => 5000000,
            'outstanding_balance_idr' => 1000000,
            'is_active' => true,
        ]);

        $folio->outstanding_balance = 3000000;
        $folio->save();

        $transferAction = app(TransferFolioToCityLedgerAction::class);
        $transfer = $transferAction->execute(
            folioId: $folio->id,
            cityLedgerAccountIdOrCode: $corp->id,
            amountIdr: 3000000,
            notes: 'Tagihan akomodasi meeting',
            idempotencyKey: 'transfer-valid-limit',
        );

        $this->assertEquals('Tagihan akomodasi meeting', $transfer->notes);
        $this->assertEquals('transfer-valid-limit', $transfer->idempotency_key);

        $transferLine = $folio->lines()->where('line_type', 'CITY_LEDGER_TRANSFER')->firstOrFail();
        $this->assertEquals(-3000000, $transferLine->amount_idr);
        $this->assertEquals('transfer-valid-limit', $transferLine->idempotency_key);

        try {
            $transferAction->execute($folio->id, $corp->id, 0, 'Zero', 'zero-key');
            $this->fail('Nominal 0 harus ditolak');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('lebih besar dari 0', $e->getMessage());
        }

        try {
            $transferAction->execute($folio->id, $corp->id, 100000, 'No key', '   ');
            $this->fail('Key kosong harus ditolak');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Idempotency-Key', $e->getMessage());
        }

        $corp->refresh();
        $this->assertEquals(4000000, $corp->outstanding_balance_idr);
        $this->assertTrue($corp->outstanding_balance_idr <= $corp->credit_limit_idr);

        // Check Ledger: Debit htl:city_ledger:IDR (-3M), Credit htl:guest_ledger:IDR (+3M)
        $tx = LedgerTransaction::where('reference_id', $transfer->id)->firstOrFail();
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertEquals(0, $entries->sum('amount'));
    }

    public function test_pengalihan_city_ledger_ditolak_jika_total_tagihan_melampaui_sisa_limit_kredit(): void
    {
        [$reservation, $folio] = $this->createInHouseGuest();

        $corp = HotelCityLedgerAccount::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'company_name' => 'PT Usaha Terbatas',
            'company_code' => 'CORP-LIMIT',
            'credit_limit_idr' => 2000000,
            'outstanding_balance_idr' => 1500000,
            'is_active' => true,
        ]);

        $folio->outstanding_balance = 1000000;
        $folio->save();

        $transferAction = app(TransferFolioToCityLedgerAction::class);

        // Sisa limit hanya 500.000, diminta 1.000.000 -> harus gagal
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Batas kredit korporat tidak mencukupi');

        $transferAction->execute(
            folioId: $folio->id,
            cityLedgerAccountIdOrCode: $corp->id,
            amountIdr: 1000000,
            notes: 'Split exceeding limit',
            idempotencyKey: 'transfer-overlimit-key',
        );
    }

    public function test_service_charge_dan_pajak_fnb_dihitung_berjenjang_sesuai_aturan_properti(): void
    {
        [$reservation, $folio] = $this->createInHouseGuest();

        $action = app(ChargeFolioOutletAction::class);
        $line = $action->execute(
            reservationOrFolioId: $folio->id,
            baseAmountIdr: 1000000,
            description: 'Room Banquet Catering',
            idempotencyKey: 'tier-calc-test-01',
        );

        // H-3: service 10% dari 1.000.000 = 100.000
        // pajak 10% dari (1.000.000 + 100.000) = 110.000
        // total = 1.210.000
        $this->assertEquals(1000000, $line->base_amount_idr);
        $this->assertEquals(100000, $line->service_charge_idr);
        $this->assertEquals(110000, $line->tax_pbjt_idr);
        $this->assertEquals(1210000, $line->amount_idr);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Hotel\Application\Actions\ProcessGuestDepositAction;
use Modules\Hotel\Application\Actions\RefundGuestDepositAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Tests\TestCase;

class HotelDepositAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelReservation $reservation;

    private User $cashier;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->reservation = HotelReservation::firstOrFail();
        $this->cashier = User::factory()->create(['role' => 'hotel_cashier']);
        $this->guest = User::factory()->create(['role' => 'customer']);
    }

    public function test_penerimaan_deposit_mencatat_kewajiban_unearned_revenue_di_ledger(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $result = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_dep_ledger_01'
        );

        $deposit = $result['deposit'];
        $this->assertEquals('HELD', $deposit->status);
        $this->assertEquals(500000, $deposit->amount_idr);

        // Verifikasi transaksi ledger
        $tx = LedgerTransaction::where('type', 'HOTEL_GUEST_DEPOSIT')
            ->where('reference_type', 'htl_deposits')
            ->where('reference_id', $deposit->id)
            ->firstOrFail();

        $this->assertEquals('htl:dep:key_dep_ledger_01', $tx->idempotency_key);

        // Verifikasi entri double-entry (Single-sign convention):
        // Kas / External Clearing = -500.000 (Debit)
        // Kewajiban Deposit Tamu = +500.000 (Kredit)
        $entries = LedgerEntry::where('transaction_id', $tx->id)->get();
        $this->assertCount(2, $entries);

        $depEntry = $entries->first(fn ($e) => str_contains($e->account->code, 'htl:guest_deposit'));
        $cashEntry = $entries->first(fn ($e) => str_contains($e->account->code, 'clearing:external'));

        $this->assertNotNull($depEntry);
        $this->assertNotNull($cashEntry);
        $this->assertEquals(500000, $depEntry->amount);
        $this->assertEquals(-500000, $cashEntry->amount);
    }

    public function test_deposit_diterapkan_penuh_ke_folio_saat_proses_check_out(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $result = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_dep_apply_01'
        );

        $this->assertEquals(500000, $result['deposit']->remaining_amount_idr);
    }

    public function test_deposit_yang_melebihi_total_tagihan_dapat_di_refund_ke_tamu(): void
    {
        $depositAction = app(ProcessGuestDepositAction::class);
        $refundAction = app(RefundGuestDepositAction::class);

        $depRes = $depositAction->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 1000000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_dep_refund_test'
        );

        $deposit = $depRes['deposit'];

        $refRes = $refundAction->handle(
            depositId: $deposit->id,
            refundAmountIdr: 300000,
            idempotencyKey: 'key_refund_tx_01'
        );

        $this->assertEquals('PARTIALLY_REFUNDED', $refRes['deposit']->status);
        $this->assertEquals('PARTIALLY_REFUNDED', $deposit->fresh()->status);
        $this->assertEquals(300000, $refRes['refunded_amount_idr']);
        $this->assertEquals(700000, $refRes['remaining_amount_idr']);

        // Refund sisa penuh (boundary $refundAmountIdr === $remaining) via execute()
        $refResFull = $refundAction->execute(
            depositId: $deposit->id,
            refundAmountIdr: 700000,
            idempotencyKey: 'key_refund_tx_02'
        );
        $this->assertEquals('REFUNDED', $refResFull['deposit']->status);
        $this->assertEquals('REFUNDED', $deposit->fresh()->status);
        $this->assertEquals(0, $refResFull['remaining_amount_idr']);

        // Pastikan jurnal ledger refund terposting
        $refundTxCount = LedgerTransaction::where('reference_id', $deposit->id)
            ->where('type', 'HOTEL_DEPOSIT_REFUND')
            ->count();
        $this->assertEquals(2, $refundTxCount);

        try {
            $refundAction->handle($deposit->id, 0, 'key_refund_zero');
            $this->fail('Refund 0 wajib ditolak');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('lebih besar dari 0', $e->getMessage());
        }

        try {
            $refundAction->handle($deposit->id, 100, '   ');
            $this->fail('Key spasi wajib ditolak');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Idempotency-Key', $e->getMessage());
        }
    }

    public function test_refund_deposit_wajib_memiliki_idempotency_key_tersendiri(): void
    {
        $depositAction = app(ProcessGuestDepositAction::class);
        $refundAction = app(RefundGuestDepositAction::class);

        $depRes = $depositAction->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_dep_for_ref_no_key'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency-Key wajib disertakan.');

        $refundAction->handle(
            depositId: $depRes['deposit']->id,
            refundAmountIdr: 100000,
            idempotencyKey: '' // Kosong
        );
    }

    public function test_transaksi_pembayaran_kasir_tanpa_idempotency_key_ditolak_422(): void
    {
        $action = app(ProcessGuestDepositAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency-Key wajib disertakan.');

        $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: ''
        );
    }

    public function test_pembayaran_dengan_idempotency_key_sama_dua_kali_hanya_menghasilkan_satu_posting(): void
    {
        $action = app(ProcessGuestDepositAction::class);

        $res1 = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_idemp_pay_double'
        );

        $initialDepositCount = HotelDeposit::count();
        $initialTxCount = LedgerTransaction::where('type', 'HOTEL_GUEST_DEPOSIT')->count();

        $res2 = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_idemp_pay_double'
        );

        $this->assertEquals($res1['deposit']->id, $res2['deposit']->id);
        $this->assertEquals($initialDepositCount, HotelDeposit::count());
        $this->assertEquals($initialTxCount, LedgerTransaction::where('type', 'HOTEL_GUEST_DEPOSIT')->count());
    }

    public function test_dua_pembayaran_sah_berbeda_dengan_nominal_sama_menghasilkan_dua_posting(): void
    {
        $action = app(ProcessGuestDepositAction::class);

        $res1 = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_pay_diff_01'
        );

        $res2 = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 500000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_pay_diff_02'
        );

        $this->assertNotEquals($res1['deposit']->id, $res2['deposit']->id);
    }

    public function test_check_out_ditolak_jika_pembayaran_kurang_dan_saldo_folio_masih_ada(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $result = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 200000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_dep_partial_pay'
        );

        $this->assertEquals(200000, $result['deposit']->amount_idr);
    }

    public function test_kelebihan_bayar_tunai_tercatat_sebagai_kembalian_atau_deposit(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $result = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 750000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_excess_cash_01'
        );

        $this->assertEquals(750000, $result['deposit']->amount_idr);
    }

    public function test_pembayaran_split_tunai_dan_kartu_kredit_terekam_seimbang_di_buku_besar(): void
    {
        $action = app(ProcessGuestDepositAction::class);
        $resCash = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 250000,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_split_cash_01'
        );

        $resCard = $action->handle(
            propertyId: $this->property->id,
            reservationId: $this->reservation->id,
            guestUserId: (string) $this->guest->id,
            amountIdr: 250000,
            paymentMethod: 'CARD',
            idempotencyKey: 'key_split_card_01'
        );

        $this->assertEquals('CASH', $resCash['deposit']->payment_method);
        $this->assertEquals('CARD', $resCard['deposit']->payment_method);
    }
}

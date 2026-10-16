<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\AutoServe\Application\Actions\CompleteBookingAction;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Payment\Domain\Models\Refund;
use Tests\TestCase;

class RefundEntityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private User $mechanic;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(BankingSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin AutoServe',
            'email' => 'admin.refund@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->customer = User::create([
            'name' => 'Customer AutoServe',
            'email' => 'customer.refund@example.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
        ]);

        $this->mechanic = User::create([
            'name' => 'Mechanic AutoServe',
            'email' => 'mechanic.refund@example.com',
            'password' => Hash::make('password'),
            'role' => 'mekanik',
        ]);

        app(SetPinAction::class)->execute($this->customer, '123456');

        $service = Service::create([
            'name' => 'Paket Servis Besar',
            'description' => 'Servis lengkap mesin dan transmisi',
            'price' => 300_000,
        ]);

        $sparepart = Sparepart::create([
            'name' => 'Paket Filter & Oli Komplet',
            'code' => 'SP-REF-01',
            'stock' => 10,
            'price' => 200_000,
            'unit' => 'set',
        ]);

        $this->booking = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => $this->customer->id,
            'mechanic_id' => $this->mechanic->id,
            'service_id' => $service->id,
            'plate_number' => 'B 1234 REF',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Innova Zenix',
            'complaint' => 'Servis 10.000 km',
            'booking_date' => now()->toDateString(),
            'booking_time' => '09:00',
            'status' => 'pending',
        ]);

        $this->booking->transitionTo(BookingStatus::Confirmed);
        $this->booking->transitionTo(BookingStatus::InProgress);

        $this->booking->spareparts()->attach($sparepart->id, [
            'quantity' => 1,
            'unit_price' => $sparepart->price,
            'subtotal' => $sparepart->price,
        ]);

        app(CompleteBookingAction::class)->execute($this->booking);

        // Top up customer dan bayar booking (Total 500.000 IDR)
        app(TopUpAction::class)->execute($this->customer, 1_000_000, 'seed_customer_wallet_refund');
        $this->actingAs($this->customer)->post(route('bookings.pay', $this->booking), [
            'pin' => '123456',
        ]);

        $this->booking->refresh();
        $this->assertTrue($this->booking->isPaid());
    }

    public function test_two_partial_refunds_with_same_nominal_amount_via_http_post_bookings_refund_create_two_pay_refunds_rows_and_two_ledger_transactions(): void
    {
        $intent = $this->booking->latestPaymentIntent;
        $this->assertNotNull($intent);

        $initialWalletBalance = (int) $this->customer->walletBalance('IDR')->amount->toInt();

        // 1. Partial refund 1 (Rp 100.000)
        $res1 = $this->actingAs($this->admin)->post(route('bookings.refund', $this->booking), [
            'amount' => 100_000,
            'reason' => 'Kompensasi keterlambatan pengerjaan',
            'refund_id' => 'ref_partial_1',
        ]);
        $res1->assertRedirect()->assertSessionHas('success');

        // 2. Partial refund 2 dengan nominal SAMA (Rp 100.000) tapi ID berbeda
        $res2 = $this->actingAs($this->admin)->post(route('bookings.refund', $this->booking), [
            'amount' => 100_000,
            'reason' => 'Diskon purnajual komponen',
            'refund_id' => 'ref_partial_2',
        ]);
        $res2->assertRedirect()->assertSessionHas('success');

        // Pastikan ada 2 baris di pay_refunds untuk payment intent ini
        $refunds = Refund::where('payment_intent_id', $intent->id)->get();
        $this->assertCount(2, $refunds);

        // Pastikan ada 2 transaksi ledger
        $tx1 = LedgerTransaction::where('idempotency_key', 'refund:ref_partial_1')->first();
        $tx2 = LedgerTransaction::where('idempotency_key', 'refund:ref_partial_2')->first();
        $this->assertNotNull($tx1);
        $this->assertNotNull($tx2);
        $this->assertNotEquals($tx1->id, $tx2->id);

        // Saldo customer bertambah tepat 200.000 IDR
        $finalWalletBalance = (int) $this->customer->walletBalance('IDR')->amount->toInt();
        $this->assertEquals($initialWalletBalance + 200_000, $finalWalletBalance);

        // Total refund pada intent tercatat 200.000
        $intent->refresh();
        $this->assertEquals(200_000, $intent->refunded_amount);
    }

    public function test_retrying_the_exact_same_refund_request_via_http_post_bookings_refund_is_idempotent_and_produces_single_transaction(): void
    {
        $intent = $this->booking->latestPaymentIntent;
        $initialWalletBalance = (int) $this->customer->walletBalance('IDR')->amount->toInt();

        // Panggilan pertama
        $res1 = $this->actingAs($this->admin)->post(route('bookings.refund', $this->booking), [
            'amount' => 150_000,
            'reason' => 'Klaim garansi servis',
            'refund_id' => 'ref_retry_same',
        ]);
        $res1->assertRedirect()->assertSessionHas('success');

        // Panggilan kedua identik (replay)
        $res2 = $this->actingAs($this->admin)->post(route('bookings.refund', $this->booking), [
            'amount' => 150_000,
            'reason' => 'Klaim garansi servis',
            'refund_id' => 'ref_retry_same',
        ]);
        $res2->assertRedirect()->assertSessionHas('success');

        // Tepat satu entitas pay_refunds dan satu transaksi ledger
        $this->assertEquals(1, Refund::where('payment_intent_id', $intent->id)->count());
        $this->assertEquals(1, LedgerTransaction::where('idempotency_key', 'refund:ref_retry_same')->count());

        // Saldo customer hanya bertambah 150.000, bukan 300.000
        $finalWalletBalance = (int) $this->customer->walletBalance('IDR')->amount->toInt();
        $this->assertEquals($initialWalletBalance + 150_000, $finalWalletBalance);
    }

    public function test_unauthorized_roles_receive_403_forbidden_on_http_post_bookings_refund(): void
    {
        // 1. Customer pemilik booking mencoba refund sendiri -> 403
        $resCustomer = $this->actingAs($this->customer)->post(route('bookings.refund', $this->booking), [
            'amount' => 50_000,
            'reason' => 'Coba refund sendiri',
        ]);
        $resCustomer->assertForbidden();

        // 2. Mekanik mencoba refund -> 403
        $resMechanic = $this->actingAs($this->mechanic)->post(route('bookings.refund', $this->booking), [
            'amount' => 50_000,
            'reason' => 'Coba refund mekanik',
        ]);
        $resMechanic->assertForbidden();

        // Tidak ada refund atau transaksi ledger tercipta
        $this->assertEquals(0, Refund::count());
        $this->assertEquals(0, LedgerTransaction::where('type', 'REFUND')->count());
    }
}

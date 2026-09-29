<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\AutoServe\Application\Actions\CompleteBookingAction;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Domain\Models\LedgerAccount;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);

    $this->admin = User::create([
        'name' => 'Admin Invoice',
        'email' => 'admin.invoice@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);

    $this->customer = User::create([
        'name' => 'Customer Invoice',
        'email' => 'customer.invoice@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    $this->mechanic = User::create([
        'name' => 'Mechanic Invoice',
        'email' => 'mechanic.invoice@example.com',
        'password' => Hash::make('password'),
        'role' => 'mekanik',
    ]);

    app(SetPinAction::class)->execute($this->customer, '123456');

    // Buat service dan sparepart
    $this->service = Service::create([
        'name' => 'Ganti Oli & Filter',
        'description' => 'Servis standar',
        'price' => 150000,
    ]);

    $this->sparepart = Sparepart::create([
        'name' => 'Oli Mesin 4L',
        'code' => 'SP-OLI-01',
        'stock' => 10,
        'price' => 250000,
        'unit' => 'botol',
    ]);

    // Buat booking sampai completed
    $this->booking = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'customer_id' => $this->customer->id,
        'mechanic_id' => $this->mechanic->id,
        'service_id' => $this->service->id,
        'plate_number' => 'B 9999 INV',
        'vehicle_brand' => 'Honda',
        'vehicle_model' => 'Civic Turbo',
        'complaint' => 'Servis rutin berkala',
        'booking_date' => now()->toDateString(),
        'booking_time' => '10:00',
        'status' => 'pending',
    ]);

    $this->booking->transitionTo(BookingStatus::Confirmed);
    $this->booking->transitionTo(BookingStatus::InProgress);

    // Tambah sparepart
    $this->booking->spareparts()->attach($this->sparepart->id, [
        'quantity' => 1,
        'unit_price' => $this->sparepart->price,
        'subtotal' => $this->sparepart->price,
    ]);

    // Complete booking
    app(CompleteBookingAction::class)->execute($this->booking);
});

test('customer dapat melihat halaman invoice dan membayar tagihan menggunakan saldo dompet', function () {
    // Isi saldo customer Rp 1.000.000
    app(TopUpAction::class)->execute($this->customer, '1000000', 'topup_inv_pay');

    // Total tagihan: 150.000 (jasa) + 250.000 (sparepart) = 400.000
    expect((float) $this->booking->grand_total)->toBe(400000.0)
        ->and($this->booking->isUnpaid())->toBeTrue();

    // 1. Kunjungi halaman invoice
    $response = $this->actingAs($this->customer)->get(route('bookings.invoice', $this->booking));
    $response->assertOk()
        ->assertSee('INVOICE')
        ->assertSee('Bayar dengan Saldo Dompet');

    // 2. Submit pembayaran invoice dengan PIN
    $payResponse = $this->actingAs($this->customer)->post(route('bookings.pay', $this->booking), [
        'pin' => '123456',
    ]);

    $payResponse->assertRedirect()
        ->assertSessionHas('success');

    $this->booking->refresh();
    expect($this->booking->isPaid())->toBeTrue()
        ->and($this->booking->paid_at)->not->toBeNull()
        ->and($this->customer->walletBalance('IDR')->amount->toInt())->toBe(600000); // 1.000.000 - 400.000

    // Verifikasi revenue splits
    $serviceRev = LedgerAccount::where('code', 'revenue:autoserve:service:IDR')->first();
    $partsRev = LedgerAccount::where('code', 'revenue:autoserve:parts:IDR')->first();
    expect($serviceRev->money()->amount->toInt())->toBe(150000)
        ->and($partsRev->money()->amount->toInt())->toBe(250000);

    // Kunjungi lagi halaman invoice -> Menampilkan badge LUNAS
    $invoiceAgain = $this->actingAs($this->customer)->get(route('bookings.invoice', $this->booking));
    $invoiceAgain->assertOk()
        ->assertSee('LUNAS')
        ->assertDontSee('Bayar dengan Saldo Dompet');
});

test('pembayaran invoice ditolak jika saldo tidak mencukupi', function () {
    // Saldo customer 0
    $response = $this->actingAs($this->customer)->post(route('bookings.pay', $this->booking), [
        'pin' => '123456',
    ]);

    $response->assertRedirect()
        ->assertSessionHas('error');

    expect($this->booking->fresh()->isUnpaid())->toBeTrue();
});

test('invoice yang sudah lunas tidak dapat dibayar kembali', function () {
    app(TopUpAction::class)->execute($this->customer, '1000000', 'topup_already_paid');

    $this->actingAs($this->customer)->post(route('bookings.pay', $this->booking), [
        'pin' => '123456',
    ]);

    expect($this->booking->fresh()->isPaid())->toBeTrue();

    // Bayar lagi
    $secondPay = $this->actingAs($this->customer)->post(route('bookings.pay', $this->booking), [
        'pin' => '123456',
    ]);

    $secondPay->assertRedirect()
        ->assertSessionHas('error', 'Tagihan invoice ini sudah berstatus lunas.');
});

test('admin dapat melakukan refund pembayaran invoice', function () {
    app(TopUpAction::class)->execute($this->customer, '1000000', 'topup_refund_test');

    $this->actingAs($this->customer)->post(route('bookings.pay', $this->booking), [
        'pin' => '123456',
    ]);

    expect($this->customer->walletBalance('IDR')->amount->toInt())->toBe(600000);

    // Admin refund
    $refundResponse = $this->actingAs($this->admin)->post(route('bookings.refund', $this->booking), [
        'reason' => 'Kompensasi pengerjaan ulang',
    ]);

    $refundResponse->assertRedirect()
        ->assertSessionHas('success');

    $this->booking->refresh();
    expect($this->booking->isRefunded())->toBeTrue()
        ->and($this->customer->walletBalance('IDR')->amount->toInt())->toBe(1000000); // Saldo kembali utuh!
});

test('rekonsiliasi bank ledger tetap bersih setelah pembayaran dan refund invoice', function () {
    $this->artisan('bank:reconcile')->assertSuccessful();
});

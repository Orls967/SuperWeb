<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\AutoServe\Application\Actions\ApproveEstimateAction;
use Modules\AutoServe\Application\Actions\ApproveExtraChargeAction;
use Modules\AutoServe\Application\Actions\CancelBookingAction;
use Modules\AutoServe\Application\Actions\CompleteBookingAction;
use Modules\AutoServe\Application\Actions\CreateEstimateAction;
use Modules\AutoServe\Application\Actions\ReceiveBackorderAction;
use Modules\AutoServe\Application\Actions\RejectEstimateAction;
use Modules\AutoServe\Application\Actions\SendEstimateAction;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\AutoServe\Domain\Models\Service;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);

    $this->admin = User::create([
        'name' => 'Admin Bengkel',
        'email' => 'admin.estimate@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);

    $this->mechanic = User::create([
        'name' => 'Mekanik Estimasi',
        'email' => 'mekanik.estimate@example.com',
        'password' => Hash::make('password'),
        'role' => 'mekanik',
    ]);

    $this->customer = User::create([
        'name' => 'Customer Estimasi',
        'email' => 'customer.estimate@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    app(SetPinAction::class)->execute($this->customer, '123456');
    app(TopUpAction::class)->execute($this->customer, 5_000_000);

    $this->service = Service::create([
        'name' => 'Overhaul Ringan',
        'description' => 'Perbaikan mesin ringan',
        'price' => 500_000,
    ]);

    $this->sparepart = Sparepart::create([
        'name' => 'Kampas Rem Depan',
        'code' => 'SP-REM-01',
        'stock' => 10,
        'price' => 300_000,
        'unit' => 'set',
    ]);

    $this->booking = Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'customer_id' => $this->customer->id,
        'mechanic_id' => $this->mechanic->id,
        'service_id' => $this->service->id,
        'plate_number' => 'B 1234 EST',
        'vehicle_brand' => 'Toyota',
        'vehicle_model' => 'Avanza',
        'complaint' => 'Rem bunyi',
        'booking_date' => now()->toDateString(),
        'booking_time' => '09:00',
        'status' => BookingStatus::Confirmed->value,
    ]);
});

/**
 * @param  array<int, array<string, mixed>>|null  $items
 */
function makeEstimate(array $items): Estimate
{
    $estimate = app(CreateEstimateAction::class)->execute(test()->booking, test()->mechanic, $items);

    return app(SendEstimateAction::class)->execute($estimate);
}

function approve(Estimate $estimate): Estimate
{
    return app(ApproveEstimateAction::class)->execute($estimate, test()->customer, '123456');
}

function escrowBalance(): int
{
    return (int) LedgerAccount::where('code', 'escrow:payment:IDR')->value('cached_balance');
}

function customerBalance(): int
{
    return (int) test()->customer->walletAccount('IDR')->fresh()->cached_balance;
}

test('estimasi disetujui menahan dana di escrow dan memulai pengerjaan', function () {
    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
        ['type' => 'part', 'ref_id' => $this->sparepart->id, 'qty' => 2],
    ]);

    expect($estimate->status)->toBe(EstimateStatus::Sent);
    expect($estimate->total)->toBe(500_000 + 600_000);

    $saldoAwal = customerBalance();
    $estimate = approve($estimate);

    expect($estimate->status)->toBe(EstimateStatus::Approved);
    expect(escrowBalance())->toBe(1_100_000);
    expect(customerBalance())->toBe($saldoAwal - 1_100_000);
    expect($this->booking->fresh()->status)->toBe(BookingStatus::InProgress->value);
});

test('biaya aktual lebih kecil dari estimasi mengembalikan sisa escrow ke customer', function () {
    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
        ['type' => 'part', 'ref_id' => $this->sparepart->id, 'qty' => 3, 'unit_price' => 300_000],
    ]);

    $saldoAwal = customerBalance();
    approve($estimate);

    // Realisasi: hanya 1 set kampas rem yang benar-benar dipakai
    $this->booking->spareparts()->attach($this->sparepart->id, [
        'quantity' => 1,
        'unit_price' => 300_000,
        'subtotal' => 300_000,
    ]);

    $booking = app(CompleteBookingAction::class)->handle($this->booking->fresh(), 'Selesai lebih murah');

    $finalTotal = 500_000 + 300_000;

    expect($booking->status)->toBe(BookingStatus::Invoiced->value);
    expect($booking->payment_status)->toBe('paid');
    expect((int) $booking->grand_total)->toBe($finalTotal);
    expect(escrowBalance())->toBe(0);
    expect(customerBalance())->toBe($saldoAwal - $finalTotal);

    expect((int) LedgerAccount::where('code', 'revenue:autoserve:service:IDR')->value('cached_balance'))->toBe(500_000);
    expect((int) LedgerAccount::where('code', 'revenue:autoserve:parts:IDR')->value('cached_balance'))->toBe(300_000);
    expect($this->sparepart->fresh()->stock)->toBe(9);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('biaya aktual melebihi estimasi meminta persetujuan tambahan lalu menagih selisih', function () {
    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
        ['type' => 'part', 'ref_id' => $this->sparepart->id, 'qty' => 1],
    ]);

    $saldoAwal = customerBalance();
    approve($estimate); // hold 800.000

    // Realisasi: butuh 3 set kampas rem
    $this->booking->spareparts()->attach($this->sparepart->id, [
        'quantity' => 3,
        'unit_price' => 300_000,
        'subtotal' => 900_000,
    ]);

    $booking = app(CompleteBookingAction::class)->handle($this->booking->fresh(), 'Perlu part tambahan');

    expect($booking->status)->toBe(BookingStatus::AwaitingExtraApproval->value);
    expect(escrowBalance())->toBe(800_000);
    expect($this->sparepart->fresh()->stock)->toBe(10); // stok belum dipotong

    $estimate->refresh();
    expect($estimate->extra_amount)->toBe(1_400_000 - 800_000);

    $booking = app(ApproveExtraChargeAction::class)->execute($booking->fresh(), $this->customer, '123456');

    expect($booking->status)->toBe(BookingStatus::Invoiced->value);
    expect($booking->payment_status)->toBe('paid');
    expect(escrowBalance())->toBe(0);
    expect(customerBalance())->toBe($saldoAwal - 1_400_000);
    expect($this->sparepart->fresh()->stock)->toBe(7);

    $totalRevenue = (int) LedgerAccount::where('code', 'revenue:autoserve:service:IDR')->value('cached_balance')
        + (int) LedgerAccount::where('code', 'revenue:autoserve:parts:IDR')->value('cached_balance');
    expect($totalRevenue)->toBe(1_400_000);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('estimasi ditolak tidak menahan dana dan mekanik dapat membuat estimasi baru', function () {
    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
    ]);

    $saldoAwal = customerBalance();
    $estimate = app(RejectEstimateAction::class)->execute($estimate, $this->customer, 'Terlalu mahal');

    expect($estimate->status)->toBe(EstimateStatus::Rejected);
    expect(escrowBalance())->toBe(0);
    expect(customerBalance())->toBe($saldoAwal);

    $baru = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1, 'unit_price' => 350_000],
    ]);

    expect($baru->total)->toBe(350_000);
    expect($baru->status)->toBe(EstimateStatus::Sent);
});

test('stok sparepart kurang memicu backorder internal dan loop waiting parts', function () {
    $this->sparepart->update(['stock' => 1]);

    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
        ['type' => 'part', 'ref_id' => $this->sparepart->id, 'qty' => 4],
    ]);

    $estimate = approve($estimate);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::WaitingParts->value);
    expect($estimate->backorder_order_id)->not->toBeNull();

    $backorder = Order::find($estimate->backorder_order_id);
    expect($backorder->status)->toBe(OrderStatus::PROCESSING);
    expect((int) $backorder->items->sum('qty'))->toBe(3); // kekurangan 4 - 1

    // Beban bengkel tercatat, bukan dompet customer
    expect((int) LedgerAccount::where('code', 'expense:autoserve:parts:IDR')->value('cached_balance'))
        ->toBe(-1 * (int) $backorder->grand_total);

    app(ReceiveBackorderAction::class)->execute($estimate);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::InProgress->value);
    expect($this->sparepart->fresh()->stock)->toBe(4);
    expect(Order::find($estimate->backorder_order_id)->status)->toBe(OrderStatus::COMPLETED);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('pembatalan booking melepaskan dana escrow kembali ke customer', function () {
    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
        ['type' => 'part', 'ref_id' => $this->sparepart->id, 'qty' => 1],
    ]);

    $saldoAwal = customerBalance();
    approve($estimate);

    expect(escrowBalance())->toBe(800_000);

    $booking = app(CancelBookingAction::class)->execute($this->booking->fresh(), 'Customer membatalkan servis');

    expect($booking->status)->toBe(BookingStatus::Cancelled->value);
    expect(escrowBalance())->toBe(0);
    expect(customerBalance())->toBe($saldoAwal);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('saldo tidak cukup menolak persetujuan estimasi tanpa menahan dana', function () {
    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1, 'unit_price' => 9_000_000],
    ]);

    expect(fn () => approve($estimate))->toThrow(Exception::class);

    expect($estimate->fresh()->status)->toBe(EstimateStatus::Sent);
    expect(escrowBalance())->toBe(0);
});

test('alur estimasi lewat http dari mekanik sampai persetujuan customer', function () {
    $this->actingAs($this->mechanic)
        ->post(route('estimates.store', $this->booking), [
            'items' => [
                ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
                ['type' => 'part', 'ref_id' => $this->sparepart->id, 'qty' => 1],
            ],
            'send_now' => true,
        ])
        ->assertRedirect();

    $estimate = Estimate::where('booking_id', $this->booking->id)->firstOrFail();
    expect($estimate->status)->toBe(EstimateStatus::Sent);

    $this->actingAs($this->customer)
        ->post(route('estimates.approve', $estimate), ['pin' => '123456'])
        ->assertRedirect();

    expect($estimate->fresh()->status)->toBe(EstimateStatus::Approved);
    expect(escrowBalance())->toBe(800_000);
    expect($this->booking->fresh()->status)->toBe(BookingStatus::InProgress->value);
});

test('halaman booking menampilkan panel estimasi untuk mekanik dan customer', function () {
    $this->actingAs($this->mechanic)
        ->get(route('bookings.show', $this->booking))
        ->assertOk()
        ->assertSee('Susun Estimasi');

    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
    ]);

    $this->actingAs($this->customer)
        ->get(route('bookings.show', $this->booking))
        ->assertOk()
        ->assertSee('Tahan Dana');

    approve($estimate);

    $this->actingAs($this->customer)
        ->get(route('bookings.show', $this->booking))
        ->assertOk()
        ->assertSee('Disetujui (Dana Ditahan)');
});

test('customer lain tidak dapat menyetujui estimasi milik orang lain', function () {
    $estimate = makeEstimate([
        ['type' => 'service', 'ref_id' => $this->service->id, 'qty' => 1],
    ]);

    $orangLain = User::create([
        'name' => 'Orang Lain',
        'email' => 'orang.lain@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);
    app(SetPinAction::class)->execute($orangLain, '654321');
    app(TopUpAction::class)->execute($orangLain, 2_000_000);

    expect(fn () => app(ApproveEstimateAction::class)->execute($estimate, $orangLain, '654321'))
        ->toThrow(Exception::class);

    expect(escrowBalance())->toBe(0);
});

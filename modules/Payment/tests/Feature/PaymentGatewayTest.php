<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Shared\Domain\ValueObjects\Money;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Modules\Banking\database\seeders\BankingSeeder']);

    $this->user = User::create([
        'name' => 'Payment Test User',
        'email' => 'payment.user@example.com',
        'password' => Hash::make('password'),
        'role' => 'customer',
    ]);

    app(TopUpAction::class)->execute($this->user, '5000000', 'topup_test_payer');
    $this->gateway = app(PaymentGateway::class);
});

function createMockPayable(int $userId, int $amount = 1000000, array $splits = []): Booking
{
    $service = Service::firstOrCreate(
        ['name' => 'General Service'],
        ['price' => 100000, 'description' => 'Servis umum']
    );

    $serviceCost = isset($splits['revenue:autoserve:service:IDR']) ? $splits['revenue:autoserve:service:IDR']->amount->toInt() : $amount;
    $sparepartCost = isset($splits['revenue:autoserve:parts:IDR']) ? $splits['revenue:autoserve:parts:IDR']->amount->toInt() : 0;

    return Booking::create([
        'booking_code' => Booking::generateBookingCode(),
        'customer_id' => $userId,
        'service_id' => $service->id,
        'plate_number' => 'B 1234 PAY',
        'vehicle_brand' => 'Toyota',
        'vehicle_model' => 'Avanza',
        'complaint' => 'Servis rutin',
        'booking_date' => now()->toDateString(),
        'booking_time' => '10:00',
        'status' => 'completed',
        'service_cost' => $serviceCost,
        'sparepart_cost' => $sparepartCost,
        'grand_total' => $amount,
        'payment_status' => 'unpaid',
    ]);
}

test('charge memotong saldo dompet, mengkredit akun revenue, dan bersifat idempotent', function () {
    $payable = createMockPayable(
        userId: $this->user->id,
        amount: 1000000,
        splits: [
            'revenue:autoserve:service:IDR' => Money::IDR(600000),
            'revenue:autoserve:parts:IDR' => Money::IDR(400000),
        ]
    );

    $intent1 = $this->gateway->charge($payable, 'idemp_key_charge_01');

    expect($intent1->isCaptured())->toBeTrue()
        ->and($payable->fresh()->isPaid())->toBeTrue()
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(4000000);

    // Verifikasi saldo revenue bertambah
    $serviceRev = LedgerAccount::where('code', 'revenue:autoserve:service:IDR')->first();
    $partsRev = LedgerAccount::where('code', 'revenue:autoserve:parts:IDR')->first();
    expect($serviceRev->money()->amount->toInt())->toBe(600000)
        ->and($partsRev->money()->amount->toInt())->toBe(400000);

    // Charge ulang dengan key yang sama tidak memotong saldo lagi
    $intent2 = $this->gateway->charge($payable, 'idemp_key_charge_01');
    expect($intent2->id)->toBe($intent1->id)
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(4000000);
});

test('hold dan capture dengan finalAmount lebih kecil dari hold mengembalikan selisih ke dompet dalam transaksi yang sama', function () {
    $payable = createMockPayable(
        userId: $this->user->id,
        amount: 2000000,
        splits: [
            'revenue:autoserve:service:IDR' => Money::IDR(1500000),
        ]
    );

    // 1. Hold 2.000.000
    // Saldo awal 5.000.000 -> sisa 3.000.000
    $intent = $this->gateway->hold($payable, Money::IDR(2000000), 'idemp_hold_01');
    expect($intent->isHeld())->toBeTrue()
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(3000000);

    // 2. Capture hanya 1.500.000 (finalAmount < holdAmount)
    // Sisa 500.000 otomatis dikembalikan ke dompet user dalam transaksi yang sama!
    // Saldo user menjadi 3.000.000 + 500.000 = 3.500.000
    $capturedIntent = $this->gateway->capture($intent, Money::IDR(1500000));

    expect($capturedIntent->isCaptured())->toBeTrue()
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(3500000);

    // Escrow balance kembali menjadi 0
    $escrow = LedgerAccount::where('code', 'escrow:payment:IDR')->first();
    expect($escrow->money()->amount->toInt())->toBe(0);
});

test('release mengembalikan seluruh dana hold dari escrow ke dompet pembayar', function () {
    $payable = createMockPayable(userId: $this->user->id, amount: 1500000);

    $intent = $this->gateway->hold($payable, Money::IDR(1500000), 'idemp_hold_release');
    expect($this->user->walletBalance('IDR')->amount->toInt())->toBe(3500000);

    $released = $this->gateway->release($intent);
    expect($released->isReleased())->toBeTrue()
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(5000000);
});

test('refund mengembalikan dana yang telah di-charge ke dompet pembayar', function () {
    $payable = createMockPayable(userId: $this->user->id, amount: 800000);

    $intent = $this->gateway->charge($payable, 'charge_to_refund');
    expect($this->user->walletBalance('IDR')->amount->toInt())->toBe(4200000);

    $refunded = $this->gateway->refund($intent, Money::IDR(800000), 'Customer batal');
    expect($refunded->isRefunded())->toBeTrue()
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(5000000)
        ->and($payable->fresh()->isRefunded())->toBeTrue();
});

test('command payment:release-expired-holds melepaskan intent yang sudah expired', function () {
    $payable = createMockPayable(userId: $this->user->id, amount: 500000);

    // Hold dengan expires_at di masa lampau
    $intent = $this->gateway->hold(
        $payable,
        Money::IDR(500000),
        'expired_hold_key',
        now()->subHour()
    );

    expect($intent->isHeld())->toBeTrue()
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(4500000);

    $this->artisan('payment:release-expired-holds')
        ->assertSuccessful()
        ->expectsOutputToContain('1 payment intent held yang kadaluarsa');

    expect($intent->fresh()->isReleased())->toBeTrue()
        ->and($this->user->walletBalance('IDR')->amount->toInt())->toBe(5000000);
});

test('rekonsiliasi bank ledger tetap bersih setelah seluruh operasi payment gateway', function () {
    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('partial refund membalik pendapatan proporsional sehingga ledger tetap seimbang', function () {
    $payable = createMockPayable(
        userId: $this->user->id,
        amount: 1000000,
        splits: ['revenue:autoserve:service:IDR' => Money::IDR(1000000)]
    );

    $intent = $this->gateway->charge($payable, 'probe_partial_refund');
    $before = $this->user->fresh()->walletBalance('IDR')->amount->toInt();

    // Refund 30% -> ledger wajib tetap seimbang (sebelumnya membalik full split -> Unbalanced)
    $refunded = $this->gateway->refund($intent, Money::IDR(300000), 'bayar sebagian', 'probe_partial_refund_r1');

    expect($this->user->fresh()->walletBalance('IDR')->amount->toInt())->toBe($before + 300000)
        ->and($refunded->isCaptured())->toBeTrue()
        ->and((float) $refunded->fresh()->refunded_amount)->toBe(300000.0);

    $rev = LedgerAccount::where('code', 'revenue:autoserve:service:IDR')->first();
    expect($rev->money()->amount->toInt())->toBe(700000);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('refund berulang dengan key sama tidak menggandakan pengembalian', function () {
    $payable = createMockPayable(
        userId: $this->user->id,
        amount: 500000,
        splits: ['revenue:autoserve:service:IDR' => Money::IDR(500000)]
    );

    $intent = $this->gateway->charge($payable, 'probe_idem_refund');
    $start = $this->user->fresh()->walletBalance('IDR')->amount->toInt();

    $this->gateway->refund($intent, Money::IDR(500000), 'full', 'probe_idem_refund_key');
    $afterFirst = $this->user->fresh()->walletBalance('IDR')->amount->toInt();

    // Retry identik -> tidak menambah saldo lagi
    $this->gateway->refund($intent->fresh(), Money::IDR(500000), 'full', 'probe_idem_refund_key');
    expect($this->user->fresh()->walletBalance('IDR')->amount->toInt())->toBe($afterFirst)
        ->and($afterFirst)->toBe($start + 500000);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('refund melebihi sisa tangkapanan ditolak', function () {
    $payable = createMockPayable(userId: $this->user->id, amount: 400000);
    $intent = $this->gateway->charge($payable, 'probe_over_refund');

    $this->gateway->refund($intent, Money::IDR(300000), 'partial', 'probe_over_1');

    expect(fn () => $this->gateway->refund($intent->fresh(), Money::IDR(300000), 'lagi', 'probe_over_2'))
        ->toThrow(InvalidArgumentException::class, 'melebihi jumlah yang di-capture');

    $this->artisan('bank:reconcile')->assertSuccessful();
});

test('capture dan release retry dengan key sama tidak menggandakan saldo', function () {
    // release idempoten
    $payable = createMockPayable(userId: $this->user->id, amount: 700000);
    $intent = $this->gateway->hold($payable, Money::IDR(700000), 'probe_hold_rel');
    $before = $this->user->fresh()->walletBalance('IDR')->amount->toInt();

    $this->gateway->release($intent, 'probe_rel_key');
    $afterFirst = $this->user->fresh()->walletBalance('IDR')->amount->toInt();
    expect($afterFirst)->toBe($before + 700000);

    // Retry dengan key sama -> hasil identik, tidak kredit dua kali
    $retried = $this->gateway->release($intent->fresh(), 'probe_rel_key');
    expect($retried->isReleased())->toBeTrue()
        ->and($this->user->fresh()->walletBalance('IDR')->amount->toInt())->toBe($afterFirst);

    $this->artisan('bank:reconcile')->assertSuccessful();
});

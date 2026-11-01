<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\ExpirePointsAction;
use Modules\Mall\Application\Actions\RedeemVoucherAction;
use Modules\Mall\Domain\Enums\LoyaltyTier;
use Modules\Mall\Domain\Exceptions\InsufficientPointsException;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\VoucherTemplate;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(LedgerAccountsSeeder::class);

    $this->customer = User::factory()->create(['role' => 'customer']);
    $this->processor = User::factory()->create(['role' => 'admin']);

    $this->member = LoyaltyMember::create([
        'user_id' => $this->customer->id,
        'tier' => LoyaltyTier::SILVER,
        'lifetime_spend' => 0,
        'current_year_spend' => 0,
        'tier_expires_at' => now()->addYear(),
    ]);

    Property::create([
        'code' => 'PROP-DM-01',
        'name' => 'Duta Mall Banjarmasin',
        'address' => 'Jl. A. Yani Km 2, Banjarmasin',
        'city' => 'Banjarmasin',
        'timezone' => 'Asia/Makassar',
    ]);
});

test('alur klaim, redeem, dan expire pada model C memutasi saldo akun secara tepat', function (): void {
    // 1. Klaim 100 PTS
    app(ClaimReceiptPointsAction::class)->execute(
        user: $this->customer,
        receiptNumber: 'RCP-MALL-FLOW-100',
        receiptAmount: 1_000_000,
        receiptDate: now(),
        processor: $this->processor,
    );

    expect($this->customer->pointsBalance())->toBe(100);

    // 2. Redeem 30 PTS
    $template = VoucherTemplate::create([
        'code' => 'VCH-FLOW-30',
        'title' => 'Voucher Belanja 30 PTS',
        'description' => 'Voucher flow test',
        'points_required' => 30,
        'nominal_value' => 30000,
        'min_spend' => 50000,
        'validity_days' => 30,
        'is_active' => true,
    ]);

    app(RedeemVoucherAction::class)->execute($this->customer, $template, 'flow_redeem_key_1');

    expect($this->customer->pointsBalance())->toBe(70);

    // 3. Expire 20 PTS dari batch aktif
    $batch = PointBatch::where('user_id', $this->customer->id)->firstOrFail();
    $batch->update([
        'expires_at' => now()->subDay(),
        'points_remaining' => 20,
    ]);

    $expired = app(ExpirePointsAction::class)->execute(now());
    expect($expired)->toBe(20)
        ->and($this->customer->pointsBalance())->toBe(50);
});

test('redeem melebihi saldo ditolak tanpa ada posting ledger apa pun', function (): void {
    // Saldo awal 0 PTS
    $template = VoucherTemplate::create([
        'code' => 'VCH-EXPENSIVE',
        'title' => 'Voucher Mahal 100 PTS',
        'description' => 'Melebihi saldo',
        'points_required' => 100,
        'nominal_value' => 100000,
        'min_spend' => 150000,
        'validity_days' => 30,
        'is_active' => true,
    ]);

    $initialTxCount = LedgerTransaction::count();

    expect(fn () => app(RedeemVoucherAction::class)->execute($this->customer, $template, 'over_limit_key'))
        ->toThrow(InsufficientPointsException::class);

    expect(LedgerTransaction::count())->toBe($initialTxCount)
        ->and($this->customer->pointsBalance())->toBe(0);
});

test('expire yang diulang bersifat idempoten dan tetap menghasilkan satu posting per batch', function (): void {
    // Klaim 50 PTS
    app(ClaimReceiptPointsAction::class)->execute(
        user: $this->customer,
        receiptNumber: 'RCP-EXPIRE-IDEMPOTENT',
        receiptAmount: 500_000,
        receiptDate: now(),
        processor: $this->processor,
    );

    $batch = PointBatch::where('user_id', $this->customer->id)->firstOrFail();
    $batch->update([
        'expires_at' => now()->subDay(),
    ]);

    $txCountBefore = LedgerTransaction::where('idempotency_key', 'mall:pts_expire:'.$batch->id)->count();
    expect($txCountBefore)->toBe(0);

    // Eksekusi pertama
    $expired1 = app(ExpirePointsAction::class)->execute(now());
    expect($expired1)->toBe(50);

    $txCountAfter1 = LedgerTransaction::where('idempotency_key', 'mall:pts_expire:'.$batch->id)->count();
    expect($txCountAfter1)->toBe(1);

    // Eksekusi kedua (diulang pada waktu yang sama)
    $expired2 = app(ExpirePointsAction::class)->execute(now());
    expect($expired2)->toBe(0);

    $txCountAfter2 = LedgerTransaction::where('idempotency_key', 'mall:pts_expire:'.$batch->id)->count();
    expect($txCountAfter2)->toBe(1);
});

test('halaman loyalty menampilkan saldo poin 50 setelah siklus transaksi', function (): void {
    // Setup sampai saldo 50 PTS
    app(ClaimReceiptPointsAction::class)->execute(
        user: $this->customer,
        receiptNumber: 'RCP-VIEW-50',
        receiptAmount: 1_000_000,
        receiptDate: now(),
        processor: $this->processor,
    );

    $template = VoucherTemplate::create([
        'code' => 'VCH-VIEW-30',
        'title' => 'Voucher 30 PTS',
        'description' => 'Voucher view test',
        'points_required' => 30,
        'nominal_value' => 30000,
        'min_spend' => 50000,
        'validity_days' => 30,
        'is_active' => true,
    ]);

    app(RedeemVoucherAction::class)->execute($this->customer, $template, 'view_redeem_key');

    $batch = PointBatch::where('user_id', $this->customer->id)->firstOrFail();
    $batch->update([
        'expires_at' => now()->subDay(),
        'points_remaining' => 20,
    ]);

    app(ExpirePointsAction::class)->execute(now());

    expect($this->customer->pointsBalance())->toBe(50);

    $response = $this->actingAs($this->customer)->get(route('mall.loyalty.index'));
    $response->assertOk();
    $response->assertSee('50');
});

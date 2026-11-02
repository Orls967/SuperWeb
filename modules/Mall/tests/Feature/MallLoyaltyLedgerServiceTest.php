<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mall\Application\Services\MallLoyaltyLedgerService;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\VoucherStatus;
use Modules\Mall\Domain\Exceptions\InsufficientPointsException;
use Modules\Mall\Domain\Exceptions\InvalidVoucherException;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Voucher;
use Tests\TestCase;

class MallLoyaltyLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Tenant $tenant;

    private MallLoyaltyLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->customer = User::firstOrCreate(
            ['email' => 'customer.loyalty.service@duttamall.com'],
            ['name' => 'Loyalty Service Customer', 'role' => 'customer', 'password' => bcrypt('password')]
        );

        $this->tenant = Tenant::firstOrFail();
        $this->tenant->update(['external_ref' => 'TENANT-EXT-001']);

        $this->service = app(MallLoyaltyLedgerService::class);
    }

    public function test_award_points_calculates_and_handles_duplicates(): void
    {
        $this->assertSame(0, $this->service->awardPoints($this->customer, 0, 'RCP-000'));

        $pts = $this->service->awardPoints($this->customer, 10000000, 'RCP-AWARD-001', 'TENANT-EXT-001');
        $this->assertGreaterThan(0, $pts);

        // Duplicate receipt claim returns 0
        $dup = $this->service->awardPoints($this->customer, 10000000, 'RCP-AWARD-001', 'TENANT-EXT-001');
        $this->assertSame(0, $dup);
    }

    public function test_apply_voucher_validates_tenant_and_uses_voucher(): void
    {
        $voucher = Voucher::create([
            'voucher_code' => 'VCH-SRV-001',
            'user_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'nominal_value' => 50000,
            'points_spent' => 500,
            'status' => VoucherStatus::ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);

        $this->expectException(InvalidVoucherException::class);
        $this->service->applyVoucher('VCH-SRV-001', 'NON-EXISTENT', 100000);
    }

    public function test_apply_voucher_successfully_applies_active_voucher(): void
    {
        $voucher = Voucher::create([
            'voucher_code' => 'VCH-SRV-002',
            'user_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'nominal_value' => 50000,
            'points_spent' => 500,
            'status' => VoucherStatus::ACTIVE,
            'expires_at' => now()->addDays(7),
        ]);

        $nominal = $this->service->applyVoucher('VCH-SRV-002', 'TENANT-EXT-001', 100000, 'tx-123');
        $this->assertSame(50000, $nominal);

        $voucher->refresh();
        $this->assertSame(VoucherStatus::USED, $voucher->status);
    }

    public function test_redeem_points_for_discount_validates_and_posts_double_entry(): void
    {
        $this->assertSame(0, $this->service->redeemPointsForDiscount($this->customer, 0, 'Diskon Nol'));

        // Insufficient balance
        $this->expectException(InsufficientPointsException::class);
        $this->service->redeemPointsForDiscount($this->customer, 500, 'Diskon Lebih');
    }

    public function test_redeem_points_for_discount_posts_ledger_entries(): void
    {
        // Award points first across two batches
        $pts1 = $this->service->awardPoints($this->customer, 10000000, 'RCP-RED-001', 'TENANT-EXT-001');
        $pts2 = $this->service->awardPoints($this->customer, 10000000, 'RCP-RED-002', 'TENANT-EXT-001');
        $totalEarned = $pts1 + $pts2;

        $batches = PointBatch::where('user_id', $this->customer->id)->orderBy('id')->get();
        $this->assertCount(2, $batches);

        // Redeem across batches
        $discountIdr = $this->service->redeemPointsForDiscount($this->customer, $pts1 + 10, 'Diskon 100 Poin', '12345');
        $this->assertSame(($pts1 + 10) * 100, $discountIdr);

        // Check batches updated
        $batches->first()->refresh();
        $batches->last()->refresh();
        $this->assertSame(0, $batches->first()->points_remaining);
        $this->assertLessThan($pts2, $batches->last()->points_remaining);

        // Check PTS transaction
        $ptsTx = LedgerTransaction::where('idempotency_key', "mall:points_redeem:{$this->customer->id}:12345")->firstOrFail();
        $this->assertSame('Diskon 100 Poin ('.($pts1 + 10).' PTS)', $ptsTx->description);
        $this->assertSame('12345', (string) $ptsTx->reference_id);
        $this->assertSame('points_discount', $ptsTx->reference_type);
        $this->assertCount(2, $ptsTx->entries);

        // Check IDR subsidy transaction
        $idrTx = LedgerTransaction::where('idempotency_key', "mall:points_discount_idr:{$this->customer->id}:12345")->firstOrFail();
        $this->assertSame('Subsidi diskon poin loyalty: Diskon 100 Poin (Rp '.number_format($discountIdr).')', $idrTx->description);
        $this->assertSame('12345', (string) $idrTx->reference_id);
        $this->assertSame('points_discount_idr', $idrTx->reference_type);
        $this->assertCount(2, $idrTx->entries);

        // Also test default referenceId branch (null)
        $this->service->redeemPointsForDiscount($this->customer, 10, 'Diskon Default Ref', null);
        $this->assertTrue(LedgerTransaction::where('idempotency_key', "mall:points_redeem:{$this->customer->id}:usr_{$this->customer->id}_pts_10")->exists());
    }
}

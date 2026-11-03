<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Finance\Application\Actions\AddCollateralAction;
use Modules\Finance\Application\Actions\OpenLoanAction;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Loan;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Mall\Application\Actions\AllocatePaymentAction;
use Modules\Mall\Application\Actions\ClaimReceiptPointsAction;
use Modules\Mall\Application\Actions\RedeemVoucherAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Store\Application\Services\CartService;
use Modules\Store\Application\Services\CheckoutService;
use Modules\Store\Domain\Models\Product;
use Modules\Venue\Application\Services\VenueCreatorEconomyService;
use Tests\TestCase;

class IdempotencyCategoryOneContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function makeUser(int $idrBalance = 40_000_000, string $btcQty = '2', string $pin = '123456'): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        app(SetPinAction::class)->execute($user, $pin);

        $topUp = app(TopUpAction::class);
        $remaining = $idrBalance;
        $chunkIndex = 0;
        while ($remaining > 0) {
            $chunk = min($remaining, 50_000_000);
            $topUp->execute($user, $chunk, "seed_topup_{$user->id}_{$chunkIndex}");
            $remaining -= $chunk;
            $chunkIndex++;
        }

        if (BigDecimal::of($btcQty)->isPositive()) {
            app(Ledger::class)->post(
                new PostingDTO(
                    type: 'genesis',
                    description: 'Holding BTC awal untuk pengujian',
                    idempotencyKey: 'test_btc_'.$user->id.'_'.Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode('clearing:external:BTC', 'BTC', BigDecimal::of($btcQty)->negated()),
                        PostingEntryDTO::forAccount($user->walletAccount('BTC')->id, 'BTC', $btcQty),
                    ],
                    postedAt: now(),
                )
            );
        }

        return $user;
    }

    private function btcAsset(): CryptoAsset
    {
        $asset = CryptoAsset::firstOrCreate(
            ['symbol' => 'BTC'],
            [
                'name' => 'Bitcoin',
                'decimals' => 8,
                'volatility' => 0.03,
                'is_active' => true,
            ]
        );

        CryptoPriceTick::create([
            'asset_id' => $asset->id,
            'price_idr' => '1000000000',
            'recorded_at' => now()->addSecond(),
        ]);

        return $asset->fresh();
    }

    private function createCarProduct(): Product
    {
        $brand = Brand::create([
            'name' => 'Lexus',
            'slug' => 'lexus-fin-'.Str::random(4),
            'country' => 'Japan',
            'category' => 'jdm',
        ]);

        $car = Car::create([
            'brand_id' => $brand->id,
            'model' => 'LC 300',
            'slug' => 'lexus-lc300-'.Str::random(4),
            'year_start' => 2024,
            'body_type' => 'SUV',
            'fuel_type' => 'gasoline',
            'is_active' => true,
        ]);

        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'productable_type' => 'dex_car',
            'productable_id' => $car->id,
            'sku' => 'CAR-'.Str::random(5),
            'name' => 'Lexus LC 300',
            'slug' => 'lexus-lc-300-'.Str::random(5),
            'price' => 300_000_000,
            'cached_stock' => 5,
            'is_listed' => true,
            'is_car' => true,
            'weight_gram' => 1_500_000,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'qty' => 5,
            'reason' => StockMovementReason::INITIAL,
            'source_type' => 'dex_car',
            'source_id' => $car->id,
            'note' => 'Stok awal unit',
            'created_at' => now(),
        ]);

        return $product;
    }

    public function test_category_1_venue_stream_accrual(): void
    {
        $service = app(VenueCreatorEconomyService::class);
        $creator = $service->registerCreator('DJ Test Accrual', 'ELECTRONIC');
        $contract = $service->createRightsContract($creator, 70.0, 14);

        // 1. Tanpa key -> throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $service->accrueStreamRevenue($contract, 10_000_000);
    }

    public function test_category_1_venue_stream_accrual_idempotency_and_distinct_calls(): void
    {
        $service = app(VenueCreatorEconomyService::class);
        $creator = $service->registerCreator('DJ Test Accrual 2', 'ELECTRONIC');
        $contract = $service->createRightsContract($creator, 70.0, 14);

        // 2. Key sama -> 1 efek (idempoten)
        $share1 = $service->accrueStreamRevenue($contract, 10_000_000, 'stream-key-replay-1');
        $share2 = $service->accrueStreamRevenue($contract, 10_000_000, 'stream-key-replay-1');

        $this->assertSame(7_000_000, $share1);
        $this->assertSame(7_000_000, $share2);
        $this->assertSame(7_000_000, (int) $contract->fresh()->held_royalties_idr);
        $this->assertSame(1, LedgerTransaction::where('idempotency_key', 'venue:stream_accrual:stream-key-replay-1')->count());

        // 3. Dua permintaan sah nominal sama dengan key berbeda -> 2 efek
        $share3 = $service->accrueStreamRevenue($contract, 10_000_000, 'stream-key-distinct-2');
        $this->assertSame(7_000_000, $share3);
        $this->assertSame(14_000_000, (int) $contract->fresh()->held_royalties_idr);
        $this->assertSame(1, LedgerTransaction::where('idempotency_key', 'venue:stream_accrual:stream-key-distinct-2')->count());
    }

    public function test_category_1_store_checkout_requires_key(): void
    {
        $user = $this->makeUser(1_000_000, '0');
        $product = Product::create([
            'sku' => 'SKU-IDEM-01',
            'name' => 'Oli Mesin Test',
            'slug' => 'oli-mesin-test-'.Str::random(6),
            'price' => 100_000,
            'cached_stock' => 10,
            'is_listed' => true,
        ]);
        app(CartService::class)->addItem($user, $product->id, 1);

        $checkoutService = app(CheckoutService::class);
        $shipping = [
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ];

        // 1. Tanpa key -> throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $checkoutService->checkout($user, $shipping, '123456');
    }

    public function test_category_1_store_checkout_replay_and_distinct_calls(): void
    {
        $user = $this->makeUser(2_000_000, '0');
        $product = Product::create([
            'sku' => 'SKU-IDEM-02',
            'name' => 'Filter Udara Test',
            'slug' => 'filter-udara-test-'.Str::random(6),
            'price' => 100_000,
            'cached_stock' => 10,
            'is_listed' => true,
        ]);
        $shipping = [
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ];

        $cartService = app(CartService::class);
        $checkoutService = app(CheckoutService::class);

        // Request 1
        $cartService->addItem($user, $product->id, 1);
        $order1 = $checkoutService->checkout($user, $shipping, '123456', 'store-chk-key-1');
        $this->assertNotNull($order1->id);

        // Request 2: nominal sama, keranjang baru, key berbeda -> 2 efek
        $cartService->addItem($user, $product->id, 1);
        $order2 = $checkoutService->checkout($user, $shipping, '123456', 'store-chk-key-2');
        $this->assertNotSame($order1->id, $order2->id);
        $this->assertSame(8, $product->fresh()->cached_stock);
    }

    public function test_category_1_mall_allocate_payment(): void
    {
        $this->seed(MallSeeder::class);
        $tenant = Tenant::first();
        $lease = Lease::first();

        $tenantUser = $tenant->user;
        if (! $tenantUser) {
            $tenantUser = User::factory()->create(['role' => 'tenant']);
            $tenant->update(['user_id' => $tenantUser->id]);
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-CAT1-'.Str::random(6),
            'lease_id' => $lease->id,
            'tenant_id' => $tenant->id,
            'property_id' => $lease->property_id,
            'period_month' => '2028-11',
            'subtotal' => 2_000_000,
            'penalty_amount' => 0,
            'total_amount' => 2_000_000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::ISSUED,
            'due_date' => Carbon::today()->addDays(10)->toDateString(),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceLineType::BASE_RENT,
            'description' => 'Sewa Toko Test',
            'quantity' => 1,
            'unit_price' => 2_000_000,
            'amount' => 2_000_000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        // Topup tenant user
        app(TopUpAction::class)->execute($tenantUser, 10_000_000, 'tenant_topup_cat1_'.Str::uuid());

        $allocator = app(AllocatePaymentAction::class);

        // 1. Tanpa key -> throw InvalidArgumentException
        try {
            $allocator->execute($invoice, 500_000, 'portal');
            $this->fail('Seharusnya melempar exception ketika key kosong');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Idempotency key wajib diisi', $e->getMessage());
        }

        // 2. Key sama -> 1 efek (idempoten)
        $allocator->execute($invoice, 500_000, 'portal', 'alloc-key-replay-1');
        $this->assertSame(500_000, $invoice->fresh()->paid_amount);

        $allocator->execute($invoice, 500_000, 'portal', 'alloc-key-replay-1');
        $this->assertSame(500_000, $invoice->fresh()->paid_amount);

        // 3. Dua permintaan sah nominal sama dengan key berbeda -> 2 efek
        $allocator->execute($invoice, 500_000, 'portal', 'alloc-key-distinct-2');
        $this->assertSame(1_000_000, $invoice->fresh()->paid_amount);
    }

    public function test_category_1_mall_redeem_voucher(): void
    {
        $this->seed(MallSeeder::class);
        $user = $this->makeUser(1_000_000, '0');
        $claimAction = app(ClaimReceiptPointsAction::class);
        $redeemAction = app(RedeemVoucherAction::class);

        // Berikan 500 PTS (5.000.000 / 10.000)
        $claimAction->execute($user, 'REC-CAT1-01', 5_000_000, Carbon::today());
        $this->assertSame(500, $user->pointsBalance());

        $template = VoucherTemplate::where('points_required', 25)->firstOrFail();

        // 1. Tanpa key -> throw InvalidArgumentException
        try {
            $redeemAction->execute($user, $template);
            $this->fail('Seharusnya melempar exception ketika key kosong');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Idempotency key wajib diisi', $e->getMessage());
        }

        // 2. Key sama -> 1 efek (idempoten, mengembalikan voucher yang sama)
        $v1 = $redeemAction->execute($user, $template, 'voucher-key-1');
        $this->assertSame(475, $user->pointsBalance());

        $v1Replay = $redeemAction->execute($user, $template, 'voucher-key-1');
        $this->assertSame($v1->id, $v1Replay->id);
        $this->assertSame(475, $user->pointsBalance());

        // 3. Dua permintaan sah dengan key berbeda -> 2 efek
        $v2 = $redeemAction->execute($user, $template, 'voucher-key-2');
        $this->assertNotSame($v1->id, $v2->id);
        $this->assertSame(450, $user->pointsBalance());
    }

    public function test_category_1_finance_add_collateral(): void
    {
        $user = $this->makeUser(10_000_000, '5');
        $asset = $this->btcAsset();

        $loan = Loan::create([
            'user_id' => $user->id,
            'principal' => 100_000_000,
            'down_payment' => 20_000_000,
            'tenor_months' => 12,
            'interest_rate_annual' => 0.08,
            'collateral_asset_id' => $asset->id,
            'collateral_qty' => '0.50000000',
            'ltv_at_open' => 20.0,
            'outstanding_principal' => 100_000_000,
            'status' => LoanStatus::Active->value,
        ]);

        $action = app(AddCollateralAction::class);

        // 1. Tanpa key -> throw InvalidArgumentException
        try {
            $action->execute($loan, $user, '0.1');
            $this->fail('Seharusnya melempar exception ketika key kosong');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Idempotency key wajib diisi', $e->getMessage());
        }

        // 2. Key sama -> 1 efek
        $action->execute($loan, $user, '0.1', 'add-col-key-1');
        $this->assertEquals('0.6', (string) $loan->fresh()->collateralQty());

        $action->execute($loan, $user, '0.1', 'add-col-key-1');
        $this->assertEquals('0.6', (string) $loan->fresh()->collateralQty());

        // 3. Dua permintaan sah nominal sama dengan key berbeda -> 2 efek
        $action->execute($loan, $user, '0.1', 'add-col-key-2');
        $this->assertEquals('0.7', (string) $loan->fresh()->collateralQty());
    }

    public function test_category_1_finance_open_loan(): void
    {
        $user = $this->makeUser(100_000_000, '10');
        $this->btcAsset();
        $product = $this->createCarProduct();

        $shipping = [
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'postal_code' => '12345',
        ];

        $action = app(OpenLoanAction::class);

        // 1. Tanpa key -> throw InvalidArgumentException
        try {
            $action->execute(
                user: $user,
                product: $product,
                downPayment: 50_000_000,
                tenorMonths: 12,
                collateralSymbol: 'BTC',
                shippingAddress: $shipping,
                pin: '123456'
            );
            $this->fail('Seharusnya melempar exception ketika key kosong');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Idempotency key wajib diisi', $e->getMessage());
        }

        // 2. Key sama -> 1 efek (mengembalikan pinjaman yang sama)
        $loan1 = $action->execute(
            user: $user,
            product: $product,
            downPayment: 50_000_000,
            tenorMonths: 12,
            collateralSymbol: 'BTC',
            shippingAddress: $shipping,
            pin: '123456',
            idempotencyKey: 'open-loan-key-1'
        );
        $this->assertNotNull($loan1->id);

        $loan1Replay = $action->execute(
            user: $user,
            product: $product,
            downPayment: 50_000_000,
            tenorMonths: 12,
            collateralSymbol: 'BTC',
            shippingAddress: $shipping,
            pin: '123456',
            idempotencyKey: 'open-loan-key-1'
        );
        $this->assertSame($loan1->id, $loan1Replay->id);

        // 3. Dua permintaan sah nominal sama dengan key berbeda -> 2 efek
        $loan2 = $action->execute(
            user: $user,
            product: $product,
            downPayment: 50_000_000,
            tenorMonths: 12,
            collateralSymbol: 'BTC',
            shippingAddress: $shipping,
            pin: '123456',
            idempotencyKey: 'open-loan-key-2'
        );
        $this->assertNotSame($loan1->id, $loan2->id);
    }
}

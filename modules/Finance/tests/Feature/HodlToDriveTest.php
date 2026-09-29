<?php

declare(strict_types=1);

namespace Modules\Finance\tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Brand;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Finance\Application\Actions\AddCollateralAction;
use Modules\Finance\Application\Actions\EvaluateLoanRiskAction;
use Modules\Finance\Application\Actions\OpenLoanAction;
use Modules\Finance\Application\Actions\PayInstallmentAction;
use Modules\Finance\Application\Services\LoanSimulator;
use Modules\Finance\Domain\Enums\InstallmentStatus;
use Modules\Finance\Domain\Enums\LoanStatus;
use Modules\Finance\Domain\Models\Loan;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Enums\OrderStatus;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\Product;
use Tests\TestCase;

class HodlToDriveTest extends TestCase
{
    use RefreshDatabase;

    private const CAR_PRICE = 300_000_000;

    private const HANDLING_FEE = 500_000;

    private const DP = 50_000_000;

    /** Harga BTC yang dipakai di seluruh test (deterministik). */
    private const BTC_PRICE = '1000000000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    private function makeUser(int $idrBalance, string $btcQty = '2', string $pin = '123456'): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        app(SetPinAction::class)->execute($user, $pin);

        $topUp = app(TopUpAction::class);
        $remaining = $idrBalance;
        while ($remaining > 0) {
            $chunk = min($remaining, 50_000_000);
            $topUp->execute($user, $chunk);
            $remaining -= $chunk;
        }

        // Kredit holding BTC lewat ledger agar reconcile tetap seimbang
        if (BigDecimal::of($btcQty)->isPositive()) {
            app(Ledger::class)->post(
                new PostingDTO(
                    type: 'genesis',
                    description: 'Holding BTC awal untuk pengujian',
                    idempotencyKey: 'test_btc_'.$user->id,
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

        $this->setBtcPrice(self::BTC_PRICE);

        return $asset->fresh();
    }

    private function setBtcPrice(string $price): void
    {
        CryptoPriceTick::create([
            'asset_id' => CryptoAsset::where('symbol', 'BTC')->value('id'),
            'price_idr' => $price,
            'recorded_at' => now()->addSecond(),
        ]);
    }

    private function carProduct(): Product
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
            'price' => self::CAR_PRICE,
            'cached_stock' => 2,
            'is_listed' => true,
            'is_car' => true,
            'weight_gram' => 1_500_000,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'qty' => 2,
            'reason' => StockMovementReason::INITIAL,
            'source_type' => 'dex_car',
            'source_id' => $car->id,
            'note' => 'Stok awal unit',
            'created_at' => now(),
        ]);

        return $product;
    }

    private function shippingAddress(): array
    {
        return [
            'name' => 'Peminjam Uji',
            'phone' => '08123456789',
            'address' => 'Jl. Kredit No. 8',
            'city' => 'Bandung',
            'postal_code' => '40123',
        ];
    }

    private function openLoan(User $user, Product $product, int $tenor = 12, int $dp = self::DP): Loan
    {
        return app(OpenLoanAction::class)->execute(
            user: $user,
            product: $product,
            downPayment: $dp,
            tenorMonths: $tenor,
            collateralSymbol: 'BTC',
            shippingAddress: $this->shippingAddress(),
            pin: '123456',
        );
    }

    private function balance(string $code): string
    {
        return (string) LedgerAccount::where('code', $code)->value('cached_balance');
    }

    public function test_simulator_menghitung_jadwal_bunga_flat_dengan_total_presisi(): void
    {
        $simulation = app(LoanSimulator::class)->simulate(120_000_000, 12);

        $this->assertSame(9_600_000, $simulation['total_interest']); // 8% x 1 tahun
        $this->assertSame(129_600_000, $simulation['total_payable']);
        $this->assertCount(12, $simulation['schedule']);

        $sumPrincipal = array_sum(array_column($simulation['schedule'], 'principal_part'));
        $sumInterest = array_sum(array_column($simulation['schedule'], 'interest_part'));

        $this->assertSame(120_000_000, $sumPrincipal);
        $this->assertSame(9_600_000, $sumInterest);
    }

    public function test_tenor_di_luar_pilihan_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(LoanSimulator::class)->simulate(100_000_000, 18);
    }

    public function test_buka_pinjaman_mengunci_kolateral_mencairkan_dana_dan_mengirim_mobil_ke_garasi(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '2');
        $product = $this->carProduct();

        $loan = $this->openLoan($user, $product);

        $principal = self::CAR_PRICE + self::HANDLING_FEE - self::DP;

        $this->assertSame($principal, $loan->principal);
        $this->assertSame($principal, $loan->outstanding_principal);
        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertCount(12, $loan->installments);

        // LTV pembukaan tepat 50%
        $this->assertEqualsWithDelta(0.5, (float) $loan->ltv_at_open, 0.0001);

        // Kolateral pindah ke akun kolateral sistem
        $requiredQty = BigDecimal::of($loan->collateral_qty);
        $this->assertSame($requiredQty->__toString(), (string) BigDecimal::of($this->balance('collateral:crypto:BTC')));
        $this->assertTrue(
            BigDecimal::of($this->balance("wallet:user:{$user->id}:BTC"))
                ->isEqualTo(BigDecimal::of('2')->minus($requiredQty))
        );

        // Dana pinjaman langsung habis untuk membayar order: saldo IDR nol
        $this->assertSame(0, (int) $this->balance("wallet:user:{$user->id}:IDR"));
        $this->assertSame(-$principal, (int) $this->balance('loan_receivable:IDR'));

        // Order lunas dan kendaraan masuk garasi
        $order = Order::find($loan->order_id);
        $this->assertSame(OrderStatus::COMPLETED, $order->status);
        $this->assertSame(1, Vehicle::where('user_id', $user->id)->count());
        $this->assertSame(1, (int) $product->fresh()->cached_stock);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_kolateral_tidak_cukup_menolak_pinjaman(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '0.01');
        $product = $this->carProduct();

        try {
            $this->openLoan($user, $product);
            $this->fail('Pinjaman seharusnya ditolak karena kolateral kurang.');
        } catch (Exception $e) {
            $this->assertStringContainsString('Kolateral BTC tidak mencukupi', $e->getMessage());
        }

        $this->assertSame(0, Loan::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(0.0, (float) $this->balance('collateral:crypto:BTC'));
    }

    public function test_uang_muka_melebihi_saldo_ditolak(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(1_000_000, '2');
        $product = $this->carProduct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Saldo dompet kurang');

        $this->openLoan($user, $product);
    }

    public function test_cicilan_terdebit_otomatis_oleh_scheduler(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP + 40_000_000, '2');
        $product = $this->carProduct();

        $loan = $this->openLoan($user, $product);
        $first = $loan->installments->first();

        // Belum jatuh tempo: tidak ada debit
        $this->artisan('finance:charge-installments')->assertSuccessful();
        $this->assertSame(InstallmentStatus::Scheduled, $first->fresh()->status);

        $this->travelTo($first->due_date->copy()->addDay());

        $saldoSebelum = (int) $this->balance("wallet:user:{$user->id}:IDR");
        $this->artisan('finance:charge-installments')->assertSuccessful();

        $first->refresh();
        $loan->refresh();

        $this->assertSame(InstallmentStatus::Paid, $first->status);
        $this->assertNotNull($first->ledger_transaction_id);

        $penalty = $first->penalty;
        $this->assertSame(
            $saldoSebelum - $first->amount - $penalty,
            (int) $this->balance("wallet:user:{$user->id}:IDR")
        );
        $this->assertSame($loan->principal - $first->principal_part, $loan->outstanding_principal);
        $this->assertSame((float) $first->interest_part, (float) $this->balance('fin:interest:IDR'));

        $this->travelBack();
        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_saldo_kurang_membuat_cicilan_overdue_dan_berdenda(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '2');
        $product = $this->carProduct();

        $loan = $this->openLoan($user, $product);
        $first = $loan->installments->first();

        // Saldo habis dipakai DP + order, cicilan jatuh tempo 10 hari lalu
        $this->travelTo($first->due_date->copy()->addDays(10));

        $this->artisan('finance:charge-installments')->assertSuccessful();

        $first->refresh();
        $this->assertSame(InstallmentStatus::Overdue, $first->status);
        $this->assertGreaterThan(0, $first->penalty);
        $this->assertSame(
            (int) floor($first->amount * Loan::DAILY_PENALTY_RATE * 10),
            $first->penalty
        );

        $this->travelBack();
    }

    public function test_pelunasan_penuh_mengembalikan_kolateral_ke_dompet_kripto(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP + 300_000_000, '2');
        $product = $this->carProduct();

        $loan = $this->openLoan($user, $product, tenor: 6);
        $collateralQty = BigDecimal::of($loan->collateral_qty);
        $btcSebelum = BigDecimal::of($this->balance("wallet:user:{$user->id}:BTC"));

        $loan = app(PayInstallmentAction::class)->payOff($loan);

        $this->assertSame(LoanStatus::PaidOff, $loan->status);
        $this->assertSame(0, $loan->outstanding_principal);
        $this->assertNotNull($loan->closed_at);
        $this->assertSame(6, $loan->installments->where('status', InstallmentStatus::Paid)->count());

        $this->assertTrue(
            BigDecimal::of($this->balance("wallet:user:{$user->id}:BTC"))
                ->isEqualTo($btcSebelum->plus($collateralQty))
        );
        $this->assertSame(0.0, (float) $this->balance('collateral:crypto:BTC'));
        $this->assertSame(0, (int) $this->balance('loan_receivable:IDR'));

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_harga_kolateral_turun_memicu_margin_call_lalu_pulih_setelah_tambah_kolateral(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '3');
        $product = $this->carProduct();

        $loan = $this->openLoan($user, $product);

        // Harga turun 30%: LTV ≈ 0,71 → margin call
        $this->setBtcPrice('700000000');
        $result = app(EvaluateLoanRiskAction::class)->evaluate($loan->fresh());

        $this->assertSame('margin_call', $result['outcome']);
        $loan->refresh();
        $this->assertSame(LoanStatus::MarginCall, $loan->status);
        $this->assertNotNull($loan->margin_called_at);

        // Tambah kolateral → LTV turun kembali di bawah 70%
        app(AddCollateralAction::class)->execute($loan, $user, '0.2');

        $loan->refresh();
        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertNull($loan->margin_called_at);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_harga_jatuh_dalam_memicu_likuidasi_otomatis_dan_sisa_kembali_ke_user(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '2');
        $product = $this->carProduct();

        $loan = $this->openLoan($user, $product);
        $outstanding = $loan->outstanding_principal;
        $collateralQty = BigDecimal::of($loan->collateral_qty);

        // Harga turun 40%: LTV ≈ 0,83 → likuidasi
        $this->setBtcPrice('600000000');
        $result = app(EvaluateLoanRiskAction::class)->evaluate($loan->fresh());

        $this->assertSame('liquidated', $result['outcome']);

        $loan->refresh();
        $this->assertSame(LoanStatus::Liquidated, $loan->status);
        $this->assertSame(0, $loan->outstanding_principal);
        $this->assertNotNull($loan->closed_at);

        // Kolateral sistem kosong kembali
        $this->assertSame(0.0, (float) $this->balance('collateral:crypto:BTC'));

        // Sisa hasil penjualan masuk dompet IDR user, sisa koin kembali ke dompet kripto
        $idr = (int) $this->balance("wallet:user:{$user->id}:IDR");
        $btc = BigDecimal::of($this->balance("wallet:user:{$user->id}:BTC"));

        $soldQty = BigDecimal::of((string) $outstanding)
            ->dividedBy(BigDecimal::of('600000000'), 8, RoundingMode::Up);
        $expectedBtc = BigDecimal::of('2')->minus($collateralQty)->plus($collateralQty->minus($soldQty));

        $this->assertTrue($btc->isEqualTo($expectedBtc), "BTC user: {$btc->__toString()}, diharapkan {$expectedBtc->__toString()}");
        $this->assertGreaterThanOrEqual(0, $idr);
        $this->assertSame(0, (int) $this->balance('loan_receivable:IDR'));

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_risk_monitor_berjalan_otomatis_setelah_crypto_tick(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '2');
        $product = $this->carProduct();

        $loan = $this->openLoan($user, $product);

        // Paksa harga jatuh lalu jalankan tick: listener harus mengevaluasi risiko
        $this->setBtcPrice('600000000');
        $this->artisan('crypto:tick')->assertSuccessful();

        $loan->refresh();
        $this->assertContains($loan->status, [LoanStatus::MarginCall, LoanStatus::Liquidated]);

        $this->artisan('bank:reconcile')->assertSuccessful();
    }

    public function test_alur_pengajuan_pembiayaan_lewat_http(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '2');
        $product = $this->carProduct();

        $this->actingAs($user)
            ->get(route('finance.loans.simulate', $product))
            ->assertOk()
            ->assertSee('HODL-to-Drive');

        $this->actingAs($user)
            ->postJson(route('finance.loans.quote'), [
                'principal' => 100_000_000,
                'tenor_months' => 12,
                'symbol' => 'BTC',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('simulation.total_interest', 8_000_000);

        $response = $this->actingAs($user)->post(route('finance.loans.store', $product), [
            ...$this->shippingAddress(),
            'recipient_name' => 'Peminjam Uji',
            'down_payment' => self::DP,
            'tenor_months' => 12,
            'collateral_symbol' => 'BTC',
            'pin' => '123456',
        ]);

        $loan = Loan::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('finance.loans.show', $loan));

        $this->actingAs($user)
            ->get(route('finance.loans.show', $loan))
            ->assertOk()
            ->assertSee('LTV');

        $this->actingAs($user)
            ->get(route('finance.loans.index'))
            ->assertOk();
    }

    public function test_pengguna_lain_tidak_dapat_melihat_pembiayaan_bukan_miliknya(): void
    {
        $this->btcAsset();
        $user = $this->makeUser(self::DP, '2');
        $product = $this->carProduct();
        $loan = $this->openLoan($user, $product);

        $orangLain = User::factory()->create(['role' => 'customer']);

        $this->actingAs($orangLain)
            ->get(route('finance.loans.show', $loan))
            ->assertForbidden();
    }
}

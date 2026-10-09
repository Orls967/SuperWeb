<?php

declare(strict_types=1);

namespace Modules\Treasury\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Models\Currency;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TreasuryService::class);
    }

    public function test_48_1_currency_and_exchange_rate_registration(): void
    {
        $usd = $this->service->registerCurrency('USD', 'US Dollar', '$', 2);
        $idr = $this->service->registerCurrency('IDR', 'Indonesian Rupiah', 'Rp', 0);

        $this->assertDatabaseHas('trs_currencies', ['code' => 'USD']);
        $this->assertDatabaseHas('trs_currencies', ['code' => 'IDR']);

        // Record rate: 1 USD = 15,500 IDR (scaled 15,500,000,000 / 1,000,000)
        $rate = $this->service->recordExchangeRate(
            from: 'USD',
            to: 'IDR',
            rateDate: '2026-10-06',
            rateNumerator: 15_500_000_000,
            rateDenominator: 1_000_000,
            rateType: 'spot'
        );

        $this->assertDatabaseHas('trs_exchange_rates', [
            'from_currency' => 'USD',
            'to_currency' => 'IDR',
            'rate_numerator' => 15_500_000_000,
        ]);

        // Convert 100 USD (minor units: 10000 cents) -> 100 * 15,500 = 1,550,000 IDR
        $converted = $this->service->convertAmount(
            amountMinorUnits: 100,
            fromCurrency: 'USD',
            toCurrency: 'IDR',
            date: '2026-10-06'
        );

        $this->assertEquals(1_550_000, $converted);
    }

    public function test_48_2_multi_currency_posting_and_idempotency(): void
    {
        $this->service->registerCurrency('USD', 'US Dollar');
        $this->service->registerCurrency('IDR', 'Indonesian Rupiah');
        $this->service->recordExchangeRate('USD', 'IDR', '2026-10-06', 15_000_000_000, 1_000_000);

        $user = User::factory()->create();

        // Create ledger accounts for USD
        $accFrom = LedgerAccount::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TRS_USD_SRC',
            'asset_code' => 'USD',
            'kind' => 'ASSET',
            'name' => 'USD Source Account',
            'allow_negative' => true,
            'cached_balance' => '1000',
        ]);

        $accTo = LedgerAccount::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TRS_USD_DEST',
            'asset_code' => 'USD',
            'kind' => 'ASSET',
            'name' => 'USD Dest Account',
            'allow_negative' => true,
            'cached_balance' => '0',
        ]);

        // Post foreign currency transfer
        $res = $this->service->postMultiCurrency(
            fromAccountCode: 'TRS_USD_SRC',
            toAccountCode: 'TRS_USD_DEST',
            foreignAmount: 200,
            foreignCurrency: 'USD',
            idempotencyKey: 'TRS_TX_001',
            description: 'Vendor payment in USD',
            date: '2026-10-06'
        );

        $this->assertEquals(3_000_000, $res['functional_amount_idr']);
        $this->assertDatabaseHas('bank_ledger_transactions', [
            'idempotency_key' => 'TRS_TX_001_FX',
        ]);

        // Idempotency: re-post with same key
        $res2 = $this->service->postMultiCurrency(
            fromAccountCode: 'TRS_USD_SRC',
            toAccountCode: 'TRS_USD_DEST',
            foreignAmount: 200,
            foreignCurrency: 'USD',
            idempotencyKey: 'TRS_TX_001',
            description: 'Vendor payment in USD',
            date: '2026-10-06'
        );

        $this->assertEquals($res['foreign_tx']->id, $res2['foreign_tx']->id);
    }

    public function test_48_3_period_end_foreign_exchange_revaluation(): void
    {
        $this->service->registerCurrency('USD', 'US Dollar');
        $this->service->registerCurrency('IDR', 'Indonesian Rupiah');

        // Initial book rate was 15,000 -> Book functional IDR for 10,000 USD is 150,000,000 IDR
        // New rate is 16,000 IDR
        $this->service->recordExchangeRate('USD', 'IDR', '2026-10-31', 16_000_000_000, 1_000_000);

        $reval = $this->service->performRevaluation(
            period: '2026-10',
            currency: 'USD',
            foreignBalance: 10_000,
            bookFunctionalIdr: 150_000_000,
            rateDate: '2026-10-31'
        );

        $this->assertEquals(160_000_000, $reval->revalued_functional_idr);
        $this->assertEquals(10_000_000, $reval->gain_loss_idr); // Unrealized FX Gain
        $this->assertDatabaseHas('trs_revaluations', [
            'period' => '2026-10',
            'gain_loss_idr' => 10_000_000,
        ]);
    }

    public function test_48_4_bank_accounts_and_statement_reconciliation(): void
    {
        $account = $this->service->createBankAccount(
            accountNumber: '123-456-7890',
            bankName: 'Bank Mandiri',
            currency: 'IDR',
            initialBalance: 50_000_000
        );

        $stmt = $this->service->recordStatement(
            bankAccountId: $account->id,
            txDate: '2026-10-06',
            refNo: 'REF-TX-888',
            amount: 5_000_000,
            description: 'Customer payment transfer'
        );

        $this->assertFalse($stmt->is_reconciled);

        $reconResult = $this->service->autoReconcileStatements($account->id);

        $this->assertEquals(1, $reconResult['reconciled']);
        $this->assertTrue($stmt->fresh()->is_reconciled);
    }

    public function test_48_5_to_48_8_cash_forecast_forward_credit_facility_and_pooling(): void
    {
        // 48.5 13-week Cash forecast
        $forecasts = $this->service->generate13WeekForecast(openingBalanceIdr: 100_000_000, startDate: '2026-10-06');
        $this->assertCount(13, $forecasts);
        $this->assertEquals(1, $forecasts->first()->week_number);

        // 48.6 Forward contract & MTM
        $this->service->registerCurrency('USD', 'US Dollar');
        $fwd = $this->service->createForwardContract(
            currency: 'USD',
            notionalForeignAmount: 50_000,
            forwardRateScaled: 15_000_000_000, // 15,000 IDR
            maturityDate: '2026-12-31'
        );

        // Spot rate rises to 15,500 IDR (scaled 15,500,000,000) -> MTM gain = 50,000 * 500 = 25,000,000 IDR
        $mtm = $this->service->markToMarket($fwd, 15_500_000_000);
        $this->assertEquals(25_000_000, $mtm);
        $this->assertEquals(25_000_000, $fwd->fresh()->mtm_value_idr);

        // 48.7 Credit Facility & covenant monitoring
        $facility = $this->service->createCreditFacility(
            code: 'FAC-BCA-01',
            bankName: 'BCA',
            limitIdr: 500_000_000,
            interestPercent: 8.5,
            maxDebtEquity: 2.5
        );

        // Draw within limit with healthy DER (2.0)
        $drawRes = $this->service->drawFacility($facility, 200_000_000, 2.0);
        $this->assertEquals(200_000_000, $drawRes['facility']->drawn_amount_idr);
        $this->assertFalse($drawRes['covenant_breached']);

        // Draw exceeding limit throws exception
        $this->expectException(\InvalidArgumentException::class);
        $this->service->drawFacility($facility, 400_000_000, 2.0);
    }

    public function test_48_8_cash_pooling_sweep(): void
    {
        $header = $this->service->createBankAccount('ACC-HEAD-01', 'Bank Central', 'IDR', 10_000_000);
        $sub = $this->service->createBankAccount('ACC-SUB-01', 'Bank Branch', 'IDR', 80_000_000);

        $pool = $this->service->setupCashPool(
            poolName: 'Main Corporate Cash Pool',
            headerAccountId: $header->id,
            subAccountId: $sub->id,
            targetBalanceIdr: 50_000_000
        );

        // Sub has 80m, target is 50m -> sweep 30m to header
        $swept = $this->service->sweepCashPool($pool);

        $this->assertEquals(30_000_000, $swept);
        $this->assertEquals(50_000_000, $sub->fresh()->balance);
        $this->assertEquals(40_000_000, $header->fresh()->balance);
    }

    public function test_48_9_audit_treasury_command(): void
    {
        $this->service->registerCurrency('IDR', 'Indonesian Rupiah');
        $this->service->createBankAccount('111-222-333', 'Bank Mandiri', 'IDR', 100_000_000);

        $audit = $this->service->auditTreasury();
        $this->assertEquals('OK', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        $this->artisan('treasury:audit')
            ->expectsOutputToContain('Treasury audit PASSED with 0 discrepancy.')
            ->assertExitCode(0);
    }

    public function test_treasury_web_route_accessible_by_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertOk()
            ->assertSee('Treasury & Multi-Currency Management');
    }
}

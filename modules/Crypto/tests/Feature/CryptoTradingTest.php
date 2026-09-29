<?php

declare(strict_types=1);

namespace Modules\Crypto\tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Exceptions\InsufficientFundsException;
use Modules\Banking\Domain\Exceptions\InvalidPinException;
use Modules\Crypto\Application\Services\PortfolioService;
use Modules\Crypto\Application\Services\TradeExecutionService;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Crypto\database\seeders\CryptoSeeder;
use Modules\Crypto\Domain\Enums\AlertCondition;
use Modules\Crypto\Domain\Enums\TradeSide;
use Modules\Crypto\Domain\Enums\TradeStatus;
use Modules\Crypto\Domain\Exceptions\InsufficientCryptoHoldingException;
use Modules\Crypto\Domain\Exceptions\QuoteExpiredException;
use Modules\Crypto\Domain\Models\CryptoAlert;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Crypto\Domain\Models\CryptoQuote;
use Modules\Crypto\Domain\Models\CryptoTrade;
use Tests\TestCase;

class CryptoTradingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PriceFeed $priceFeed;

    private TradeExecutionService $tradeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(CryptoSeeder::class);

        $this->user = User::factory()->create([
            'role' => 'customer',
        ]);

        app(SetPinAction::class)->execute($this->user, '123456');
        app(TopUpAction::class)->execute($this->user, '50000000', 'initial_topup'); // 50 Juta IDR

        $this->priceFeed = app(PriceFeed::class);
        $this->tradeService = app(TradeExecutionService::class);
    }

    public function test_price_engine_quotes_price_with_fee_and_expiration(): void
    {
        $quote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'BTC',
            side: TradeSide::BUY,
            amountIdr: '10000000' // 10 Juta IDR
        );

        $this->assertInstanceOf(CryptoQuote::class, $quote);
        $this->assertSame('BTC', $quote->asset->symbol);
        $this->assertSame(TradeSide::BUY, $quote->side);
        $this->assertFalse($quote->is_executed);
        $this->assertFalse($quote->isExpired());

        // Check fee is 0.2%
        $gross = BigDecimal::of($quote->gross_idr);
        $fee = BigDecimal::of($quote->fee_idr);
        $expectedFee = $gross->multipliedBy(BigDecimal::of('0.002'))->toScale(2, RoundingMode::HalfUp);
        $this->assertSame((string) $expectedFee, (string) $fee);
    }

    public function test_buy_flow_executes_five_ledger_entries(): void
    {
        $quote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'BTC',
            side: TradeSide::BUY,
            amountIdr: '15500000'
        );

        $initialIdr = BigDecimal::of($this->user->walletAccount('IDR')->cached_balance);
        $initialBtc = BigDecimal::of($this->user->walletAccount('BTC')->cached_balance ?: '0');

        $trade = $this->tradeService->executeTrade($this->user, $quote->uuid, '123456');

        $this->assertInstanceOf(CryptoTrade::class, $trade);
        $this->assertSame(TradeStatus::COMPLETED, $trade->status);
        $this->assertSame(TradeSide::BUY, $trade->side);

        $this->user->walletAccount('IDR')->refresh();
        $this->user->walletAccount('BTC')->refresh();

        $finalIdr = BigDecimal::of($this->user->walletAccount('IDR')->cached_balance);
        $finalBtc = BigDecimal::of($this->user->walletAccount('BTC')->cached_balance);

        $totalDeductedIdr = BigDecimal::of($quote->gross_idr)->plus(BigDecimal::of($quote->fee_idr));
        $this->assertTrue($totalDeductedIdr->isEqualTo($initialIdr->minus($finalIdr)));
        $this->assertTrue(BigDecimal::of($quote->quantity)->isEqualTo($finalBtc->minus($initialBtc)));

        // Reconcile passes
        $this->assertSame(0, Artisan::call('bank:reconcile'));
    }

    public function test_buy_flow_rejected_if_user_idr_balance_is_insufficient(): void
    {
        $quote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'BTC',
            side: TradeSide::BUY,
            amountIdr: '100000000' // 100 Juta IDR (user only has 50 Juta)
        );

        $this->expectException(InsufficientFundsException::class);
        $this->tradeService->executeTrade($this->user, $quote->uuid, '123456');
    }

    public function test_sell_flow_executes_five_ledger_entries(): void
    {
        // First buy some BTC
        $buyQuote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'BTC',
            side: TradeSide::BUY,
            amountIdr: '10000000'
        );
        $this->tradeService->executeTrade($this->user, $buyQuote->uuid, '123456');

        $btcAccount = $this->user->walletAccount('BTC')->refresh();
        $boughtBtc = $btcAccount->cached_balance;

        // Now sell half of the bought BTC
        $sellQty = (string) BigDecimal::of($boughtBtc)->dividedBy(2, 8, RoundingMode::Down);
        $sellQuote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'BTC',
            side: TradeSide::SELL,
            cryptoQty: $sellQty
        );

        $idrBeforeSell = BigDecimal::of($this->user->walletAccount('IDR')->refresh()->cached_balance);
        $btcBeforeSell = BigDecimal::of($btcAccount->cached_balance);

        $trade = $this->tradeService->executeTrade($this->user, $sellQuote->uuid, '123456');

        $this->assertSame(TradeStatus::COMPLETED, $trade->status);
        $this->assertSame(TradeSide::SELL, $trade->side);

        $idrAfterSell = BigDecimal::of($this->user->walletAccount('IDR')->refresh()->cached_balance);
        $btcAfterSell = BigDecimal::of($this->user->walletAccount('BTC')->refresh()->cached_balance);

        $netReceivedIdr = BigDecimal::of($sellQuote->gross_idr)->minus(BigDecimal::of($sellQuote->fee_idr));
        $this->assertTrue($netReceivedIdr->isEqualTo($idrAfterSell->minus($idrBeforeSell)));
        $this->assertTrue(BigDecimal::of($sellQuote->quantity)->isEqualTo($btcBeforeSell->minus($btcAfterSell)));

        // Reconcile passes
        $this->assertSame(0, Artisan::call('bank:reconcile'));
    }

    public function test_sell_flow_rejected_if_crypto_holding_is_insufficient(): void
    {
        $sellQuote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'ETH',
            side: TradeSide::SELL,
            cryptoQty: '5.0' // User has 0 ETH
        );

        $this->expectException(InsufficientCryptoHoldingException::class);
        $this->tradeService->executeTrade($this->user, $sellQuote->uuid, '123456');
    }

    public function test_expired_quote_rejected_with_exception(): void
    {
        $quote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'SOL',
            side: TradeSide::BUY,
            amountIdr: '1000000'
        );

        // Travel 20 seconds forward in time
        $this->travel(20)->seconds();

        $this->assertTrue($quote->isExpired());

        $this->expectException(QuoteExpiredException::class);
        $this->tradeService->executeTrade($this->user, $quote->uuid, '123456');
    }

    public function test_trade_execution_verifies_pin_correctly(): void
    {
        $quote = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'SOL',
            side: TradeSide::BUY,
            amountIdr: '1000000'
        );

        $this->expectException(InvalidPinException::class);
        $this->tradeService->executeTrade($this->user, $quote->uuid, '999999');
    }

    public function test_price_engine_tick_command_updates_prices_and_triggers_alerts(): void
    {
        $sol = CryptoAsset::where('symbol', 'SOL')->first();

        // Create alert for SOL above 100 IDR (which will definitely trigger)
        $alert = CryptoAlert::create([
            'user_id' => $this->user->id,
            'asset_id' => $sol->id,
            'condition' => AlertCondition::ABOVE,
            'target_price_idr' => '100',
            'is_triggered' => false,
        ]);

        $initialTicksCount = CryptoPriceTick::where('asset_id', $sol->id)->count();

        Artisan::call('crypto:tick');

        $this->assertGreaterThan($initialTicksCount, CryptoPriceTick::where('asset_id', $sol->id)->count());

        $alert->refresh();
        $this->assertTrue($alert->is_triggered);
        $this->assertNotNull($alert->triggered_at);
    }

    public function test_portfolio_service_calculates_holdings_and_unrealized_pnl(): void
    {
        $buyQuote1 = $this->priceFeed->generateQuote(
            user: $this->user,
            symbol: 'BTC',
            side: TradeSide::BUY,
            amountIdr: '10000000'
        );
        $this->tradeService->executeTrade($this->user, $buyQuote1->uuid, '123456');

        $portfolioService = app(PortfolioService::class);
        $summary = $portfolioService->getPortfolioSummary($this->user);

        $btcItem = collect($summary['items'])->firstWhere('symbol', 'BTC');

        $this->assertTrue(BigDecimal::of($btcItem['holding'])->isPositive());
        $this->assertTrue(BigDecimal::of($btcItem['avg_buy_price'])->isPositive());
        $this->assertNotSame('0', $summary['total_net_worth']);
    }

    public function test_http_endpoints_return_successful_responses(): void
    {
        $this->actingAs($this->user);

        // 1. Market index
        $res = $this->get(route('crypto.market.index'));
        $res->assertOk();
        $res->assertSee('BTC');

        // 2. Coin detail & chart
        $res = $this->get(route('crypto.market.show', 'BTC'));
        $res->assertOk();
        $res->assertSee('Bitcoin');

        // 3. API prices
        $res = $this->get(route('crypto.api.prices'));
        $res->assertOk();
        $res->assertJsonPath('status', 'success');

        // 4. API chart
        $res = $this->get(route('crypto.api.chart', 'BTC').'?range=24h');
        $res->assertOk();
        $res->assertJsonStructure(['symbol', 'labels', 'prices']);

        // 5. POST quote
        $res = $this->postJson(route('crypto.trade.quote'), [
            'symbol' => 'USDT',
            'side' => 'buy',
            'amount_idr' => 100000,
        ]);
        $res->assertOk();
        $res->assertJsonPath('success', true);
        $quoteUuid = $res->json('quote.uuid');

        // 6. POST execute trade
        $res = $this->postJson(route('crypto.trade.execute'), [
            'quote_uuid' => $quoteUuid,
            'pin' => '123456',
        ]);
        $res->assertOk();
        $res->assertJsonPath('success', true);

        // 7. Portfolio page
        $res = $this->get(route('crypto.portfolio.index'));
        $res->assertOk();
        $res->assertSee('Portofolio Kripto');

        // 8. Alerts page
        $res = $this->get(route('crypto.alerts.index'));
        $res->assertOk();
    }
}

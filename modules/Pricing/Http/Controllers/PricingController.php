<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Pricing\Application\Services\AlgorithmicSurgePricingService;
use Modules\Pricing\Application\Services\PricingService;
use Modules\Pricing\Domain\Models\AnalyticsSnapshot;
use Modules\Pricing\Domain\Models\DiscountRule;
use Modules\Pricing\Domain\Models\FrozenQuote;
use Modules\Pricing\Domain\Models\MarginPolicy;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceLock;
use Modules\Pricing\Domain\Models\PriceOverride;
use Modules\Pricing\Domain\Models\PriceTick;
use Modules\Pricing\Domain\Models\PriceTickAggregate;
use Modules\Pricing\Domain\Models\Promotion;
use Modules\Pricing\Domain\Models\PromotionClaim;

class PricingController extends Controller
{
    public function __construct(
        private readonly PricingService $service,
        private readonly AlgorithmicSurgePricingService $surgeService
    ) {}

    public function index(): View
    {
        return view('pricing::index', [
            'priceLists' => PriceList::withCount('items')->orderBy('created_at')->get(),
            'discountRules' => DiscountRule::orderBy('order')->get(),
            'promotions' => Promotion::orderBy('created_at')->get(),
            'claims' => PromotionClaim::with('promotion')->orderBy('created_at')->limit(30)->get(),
            'overrides' => PriceOverride::orderBy('created_at')->limit(30)->get(),
            'locks' => PriceLock::orderBy('locked_at')->limit(50)->get(),
            'analytics' => AnalyticsSnapshot::orderBy('period')->limit(12)->get(),
            'channels' => ['general', 'retail', 'horeca', 'modern', 'export'],
        ]);
    }

    public function storePriceList(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:160',
            'channel' => 'required|in:general,retail,horeca,modern,export',
            'segment' => 'nullable|string|max:24', 'region_code' => 'nullable|string|max:40',
            'currency' => 'required|string|max:3', 'priority' => 'required|integer|min:1|max:1000',
            'valid_from' => 'required|date', 'valid_until' => 'nullable|date|after:valid_from',
            'notes' => 'nullable|string|max:500',
        ]);
        $this->service->createPriceList($data, $request->user());

        return back()->with('success', 'Price list draft dibuat.');
    }

    public function storePriceListItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'price_list_id' => 'required|uuid|exists:pric_price_lists,id',
            'sku' => 'required|string|max:60', 'price_idr' => 'required|integer|min:0',
            'min_qty' => 'required|numeric|gt:0',
        ]);
        $list = PriceList::findOrFail($data['price_list_id']);
        try {
            $this->service->setPriceListItem($list, $data['sku'], (int) $data['price_idr'], (float) $data['min_qty']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['item' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Item harga disimpan.');
    }

    public function activatePriceList(PriceList $list): RedirectResponse
    {
        try {
            $this->service->activatePriceList($list);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['list' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "Price list {$list->code} aktif.");
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:160',
            'kind' => 'required|in:volume,bundle,combo,coupon',
            'channel' => 'required|in:general,retail,horeca,modern,export',
            'threshold_qty' => 'nullable|numeric|min:0', 'threshold_amount_idr' => 'nullable|integer|min:0',
            'percent_off' => 'nullable|numeric|between:0,100', 'amount_off_idr' => 'nullable|integer|min:0',
            'order' => 'required|integer|min:1|max:1000', 'coupon_code' => 'nullable|string|max:60',
            'valid_from' => 'required|date', 'valid_until' => 'nullable|date|after:valid_from',
            'stackable' => 'sometimes|boolean',
        ]);
        DiscountRule::create(array_merge($data, ['is_active' => true]));

        return back()->with('success', 'Aturan diskon dibuat.');
    }

    public function storePromotion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:160',
            'mechanic' => 'required|in:off_invoice,bill_back,scan_back',
            'budget_idr' => 'required|integer|min:0', 'percent_off' => 'nullable|numeric|between:0,100',
            'amount_off_idr' => 'nullable|integer|min:0', 'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
        ]);
        $this->service->createPromotion($data);

        return back()->with('success', 'Promo dibuat.');
    }

    public function storeClaim(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'promotion_id' => 'required|uuid|exists:pric_promotions,id',
            'source_ref' => 'required|string|max:80', 'amount_idr' => 'required|integer|gt:0',
            'evidence_note' => 'required|string|max:1000',
        ]);
        $promotion = Promotion::findOrFail($data['promotion_id']);
        try {
            $claim = $this->service->submitPromotionClaim(
                $promotion, null, $data['source_ref'], (int) $data['amount_idr'],
                $request->user(), $data['evidence_note'],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['claim' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "Klaim {$claim->source_ref} terkirim.");
    }

    public function validateClaim(PromotionClaim $claim, Request $request): RedirectResponse
    {
        try {
            $this->service->approvePromotionClaim($claim, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['claim' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Klaim tervalidasi (menunggu settlement four-eyes).');
    }

    public function settleClaim(PromotionClaim $claim, Request $request): RedirectResponse
    {
        try {
            $this->service->settlePromotionClaim($claim, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['claim' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Klaim promo disettle.');
    }

    public function storeMarginPolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => 'required|in:general,retail,horeca,modern,export',
            'sku' => 'nullable|string|max:60', 'min_margin_percent' => 'required|numeric|min:0|max:100',
            'floor_cost_idr' => 'required|integer|min:0',
        ]);
        MarginPolicy::updateOrCreate(
            ['channel' => $data['channel'], 'sku' => $data['sku'] ?? null],
            ['min_margin_percent' => $data['min_margin_percent'], 'floor_cost_idr' => $data['floor_cost_idr'], 'is_active' => true],
        );

        return back()->with('success', 'Kebijakan margin disimpan.');
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => 'required|string|max:60', 'channel' => 'required|in:general,retail,horeca,modern,export',
            'proposed_price_idr' => 'required|integer|min:0', 'reason' => 'required|string|max:300',
        ]);
        try {
            $override = $this->service->requestOverride(
                $data['sku'], $data['channel'], (int) $data['proposed_price_idr'], $data['reason'], $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['override' => $e->getMessage()])->withInput();
        }

        return back()->with('success', "Override #{$override->id} diajukan (four-eyes).");
    }

    public function decideOverride(PriceOverride $override, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,reject', 'note' => 'nullable|string|max:300',
        ]);
        try {
            $this->service->decideOverride($override, $request->user(), $data['decision'] === 'approve', $data['note'] ?? null);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['override' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Keputusan override tercatat.');
    }

    public function computeAnalytics(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => 'required|string|max:7', 'channel' => 'required|in:general,retail,horeca,modern,export',
        ]);
        $snapshot = $this->service->computeAnalytics($data['period'], $data['channel']);

        return back()->with('success', sprintf(
            'Analitik %s: realisasi %.1f%% dari list, leakage %s, rata-rata diskon %.1f%%.',
            $data['period'], (float) $snapshot->realized_vs_list_percent,
            number_format((int) $snapshot->discount_leakage_idr), (float) $snapshot->avg_discount_percent,
        ));
    }

    public function ticks(Request $request): View
    {
        $sku = (string) $request->query('sku', 'PART-BRAKE-01');
        $ticks = PriceTick::where('sku', $sku)->orderByDesc('tick_time')->limit(50)->get();
        $aggregates = PriceTickAggregate::where('sku', $sku)->orderByDesc('bucket_minute')->limit(30)->get();
        $quotes = FrozenQuote::where('sku', $sku)->orderByDesc('created_at')->limit(30)->get();
        $analytics = $this->surgeService->getAnalyticsSummary($sku);

        return view('pricing::ticks', [
            'selectedSku' => $sku,
            'ticks' => $ticks,
            'aggregates' => $aggregates,
            'quotes' => $quotes,
            'analytics' => $analytics,
        ]);
    }

    public function storeTick(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => 'required|string|max:64',
            'price_idr' => 'required|integer|min:0',
            'demand_index' => 'required|numeric|min:0.1|max:10.0',
            'driver_source' => 'required|string|max:32',
        ]);

        $this->surgeService->recordPriceTick(
            sku: $data['sku'],
            tickTime: now(),
            priceIdr: (int) $data['price_idr'],
            demandIndex: (float) $data['demand_index'],
            driverSource: $data['driver_source'],
        );

        return back()->with('success', "Price tick tercatat untuk {$data['sku']}.");
    }

    public function freezeQuote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => 'required|string|max:64',
            'base_hpp_idr' => 'required|integer|min:1',
            'het_ceiling_idr' => 'required|integer|min:1',
            'demand_index' => 'required|numeric|min:0.1|max:10.0',
            'channel' => 'nullable|string|in:STORE_B2C,DISTRIBUTOR_WHOLESALE,B2B_MARKETPLACE,EXPORT,VENDING',
            'contract_price_idr' => 'nullable|integer|min:0',
            'lock_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $channel = $data['channel'] ?? 'STORE_B2C';
        $calc = $this->surgeService->quoteForChannel(
            channel: $channel,
            sku: $data['sku'],
            baseHppIdr: (int) $data['base_hpp_idr'],
            hetCeilingIdr: (int) $data['het_ceiling_idr'],
            demandIndex: (float) $data['demand_index'],
            contractPriceIdr: isset($data['contract_price_idr']) && $data['contract_price_idr'] > 0 ? (int) $data['contract_price_idr'] : null,
        );

        $quote = $this->surgeService->freezePriceQuote(
            sku: $data['sku'],
            effectivePriceIdr: (int) $calc['effective_price_idr'],
            floorPriceIdr: (int) $calc['floor_price_idr'],
            ceilingPriceIdr: (int) $calc['ceiling_price_idr'],
            lockMinutes: (int) ($data['lock_minutes'] ?? 30),
            channel: $channel,
        );

        return back()->with('success', "Quote {$quote->quote_code} berhasil dibekukan pada Rp ".number_format($quote->frozen_price_idr).'.');
    }

    public function convertQuote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quote_code' => 'required|string|max:32',
            'order_ref' => 'required|string|max:64',
        ]);

        try {
            $quote = $this->surgeService->convertFrozenQuoteToOrder($data['quote_code'], $data['order_ref']);

            return back()->with('success', "Quote {$quote->quote_code} berhasil dikonversi ke order {$quote->order_ref}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['quote' => $e->getMessage()]);
        }
    }
}

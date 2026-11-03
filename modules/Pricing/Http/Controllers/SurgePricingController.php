<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Contracts\ClockInterface;
use Modules\Pricing\Application\Services\DynamicTickEngine;
use Modules\Pricing\Domain\Models\FrozenQuote;
use Modules\Pricing\Domain\Models\PriceTick;
use Modules\Pricing\Domain\Models\PriceTickRejection;
use Modules\Pricing\Domain\Models\SkuParameter;

class SurgePricingController extends Controller
{
    public function __construct(
        private readonly DynamicTickEngine $engine,
        private readonly ClockInterface $clock
    ) {}

    public function surge(Request $request): View
    {
        $parameters = SkuParameter::orderBy('sku')->limit(50)->get();
        $ticks = PriceTick::orderByDesc('tick_time')->limit(50)->get();

        return view('pricing::surge', [
            'parameters' => $parameters,
            'ticks' => $ticks,
        ]);
    }

    public function calculate(Request $request): RedirectResponse
    {
        $sku = (string) $request->input('sku');
        if (trim($sku) === '') {
            return redirect()->route('pricing.surge')->with('error', 'SKU wajib diisi.');
        }

        $param = SkuParameter::where('sku', $sku)->first();
        $basePrice = $param ? (int) $param->base_price_idr : (int) $request->input('base_price_idr', 10_000);
        $floorPrice = $param ? (int) $param->floor_price_idr : (int) $request->input('floor_price_idr', 8_000);
        $ceilingPrice = $param ? (int) $param->ceiling_price_idr : (int) $request->input('ceiling_price_idr', 15_000);
        $hetPrice = $param ? (int) $param->het_price_idr : (int) $request->input('het_price_idr', 14_000);
        $priceStep = $param ? (int) $param->price_step_idr : 50;

        $lastTick = PriceTick::where('sku', $sku)->orderByDesc('tick_time')->first();
        $lastPrice = $lastTick ? (int) $lastTick->price_idr : $basePrice;

        $result = $this->engine->calculateTick([
            'sku' => $sku,
            'base_price_idr' => $basePrice,
            'last_price_idr' => $lastPrice,
            'floor_price_idr' => $floorPrice,
            'ceiling_price_idr' => $ceilingPrice,
            'het_price_idr' => $hetPrice,
            'price_step_idr' => $priceStep,
            'factors' => $request->input('factors', []),
            'stock_cover_days' => $param?->stock_cover_days,
            'demand_ratio_bps' => $param?->demand_ratio_bps,
            'shelf_life_ratio_bps' => $param?->shelf_life_ratio_bps,
            'is_perishable' => (bool) ($param?->is_perishable ?? false),
        ]);

        if ($result['accepted']) {
            $this->engine->calculateAndPersistTick(
                sku: $sku,
                priceIdr: (int) $result['effective_price_idr'],
                status: (string) $result['status'],
                basePriceIdr: (int) $result['base_price_idr'],
                rawPriceIdr: (int) $result['raw_price_idr'],
                factorTotalBps: (int) $result['factor_total_bps']
            );

            return redirect()->route('pricing.surge')
                ->with('success', "Tick harga untuk {$sku} berhasil dihitung: Rp ".number_format($result['effective_price_idr']));
        }

        return redirect()->route('pricing.surge')
            ->with('warning', "Tick harga untuk {$sku} ditolak: {$result['rejection_reason']}. Harga tetap Rp ".number_format($result['effective_price_idr']));
    }

    public function rejections(): View
    {
        $rejections = PriceTickRejection::orderByDesc('created_at')->limit(50)->get();

        return view('pricing::rejections', [
            'rejections' => $rejections,
        ]);
    }

    public function checkoutQuote(Request $request): JsonResponse
    {
        $quoteCode = (string) $request->input('quote_code');
        $orderRef = (string) $request->input('order_ref');

        if ($quoteCode === '' || $orderRef === '') {
            return response()->json(['error' => 'quote_code dan order_ref wajib diisi'], 422);
        }

        $quote = FrozenQuote::where('quote_code', $quoteCode)->first();
        if (! $quote) {
            return response()->json(['error' => "Quote {$quoteCode} tidak ditemukan"], 404);
        }

        $now = $this->clock->now();

        // Cek kedaluwarsa (TTL 15 menit)
        if ($quote->status === 'EXPIRED' || ($quote->locked_until && $now->greaterThan($quote->locked_until))) {
            $quote->update(['status' => 'EXPIRED']);

            // Dapatkan penawaran harga terbaru
            $lastTick = PriceTick::where('sku', $quote->sku)->orderByDesc('tick_time')->first();
            $newPrice = $lastTick ? (int) $lastTick->price_idr : (int) $quote->frozen_price_idr;

            return response()->json([
                'error' => 'Quote expired and cannot be converted to order.',
                'new_quote' => [
                    'sku' => $quote->sku,
                    'price_idr' => $newPrice,
                    'locked_until' => $now->addMinutes(15)->toIso8601String(),
                ],
            ], 409);
        }

        $quote->update([
            'order_ref' => $orderRef,
            'converted_at' => $now,
            'status' => 'CONVERTED',
        ]);

        return response()->json([
            'message' => "Quote {$quoteCode} berhasil dikonversi ke order {$orderRef}",
            'quote' => $quote,
        ]);
    }
}

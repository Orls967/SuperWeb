<?php

declare(strict_types=1);

namespace Modules\Crypto\Http\Controllers;

use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crypto\Application\Services\TradeExecutionService;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Crypto\Domain\Enums\TradeSide;

class CryptoTradeController extends Controller
{
    public function __construct(
        private readonly PriceFeed $priceFeed,
        private readonly TradeExecutionService $tradeExecutionService
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'required|string',
            'side' => 'required|string|in:buy,sell',
            'amount_idr' => 'nullable|numeric|min:0.01',
            'crypto_qty' => 'nullable|numeric|min:0.00000001',
        ]);

        if (empty($validated['amount_idr']) && empty($validated['crypto_qty'])) {
            return response()->json([
                'message' => 'Masukkan nominal Rupiah atau jumlah koin.',
            ], 422);
        }

        try {
            $quote = $this->priceFeed->generateQuote(
                user: $request->user(),
                symbol: $validated['symbol'],
                side: TradeSide::from($validated['side']),
                amountIdr: isset($validated['amount_idr']) ? (string) $validated['amount_idr'] : null,
                cryptoQty: isset($validated['crypto_qty']) ? (string) $validated['crypto_qty'] : null
            );

            return response()->json([
                'success' => true,
                'quote' => [
                    'uuid' => $quote->uuid,
                    'symbol' => $quote->asset->symbol,
                    'name' => $quote->asset->name,
                    'side' => $quote->side->value,
                    'price_idr' => (string) $quote->price_idr,
                    'price_formatted' => number_format((float) (string) $quote->price_idr, 0, ',', '.'),
                    'quantity' => (string) $quote->quantity,
                    'gross_idr' => (string) $quote->gross_idr,
                    'gross_formatted' => number_format((float) (string) $quote->gross_idr, 0, ',', '.'),
                    'fee_idr' => (string) $quote->fee_idr,
                    'fee_formatted' => number_format((float) (string) $quote->fee_idr, 0, ',', '.'),
                    'total_idr' => $quote->side === TradeSide::BUY
                        ? (string) (BigDecimal::of($quote->gross_idr)->plus(BigDecimal::of($quote->fee_idr)))
                        : (string) (BigDecimal::of($quote->gross_idr)->minus(BigDecimal::of($quote->fee_idr))),
                    'expires_at' => $quote->expires_at->toIso8601String(),
                    'seconds_remaining' => $quote->secondsRemaining(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quote_uuid' => 'required|uuid',
            'pin' => 'required|string|size:6',
        ]);

        try {
            $trade = $this->tradeExecutionService->executeTrade(
                user: $request->user(),
                quoteUuid: $validated['quote_uuid'],
                pin: $validated['pin']
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi trade kripto berhasil diselesaikan!',
                'trade' => [
                    'uuid' => $trade->uuid,
                    'symbol' => $trade->asset->symbol,
                    'side' => $trade->side->value,
                    'side_label' => $trade->side->label(),
                    'quantity' => (string) $trade->quantity,
                    'price_idr' => (string) $trade->price_idr,
                    'price_formatted' => number_format((float) (string) $trade->price_idr, 0, ',', '.'),
                    'gross_idr' => (string) $trade->gross_idr,
                    'gross_formatted' => number_format((float) (string) $trade->gross_idr, 0, ',', '.'),
                    'fee_idr' => (string) $trade->fee_idr,
                    'fee_formatted' => number_format((float) (string) $trade->fee_idr, 0, ',', '.'),
                    'created_at' => $trade->created_at->format('d M Y H:i:s'),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

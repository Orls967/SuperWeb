<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOrderbookOrder;

class RwaOrderbookService
{
    public const PLATFORM_FEE_BPS = 25; // 0.25%

    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function placeOrder(
        RwaAsset $asset,
        int $userId,
        string $side,
        int $quantity,
        int $pricePerTokenIdr
    ): RwaOrderbookOrder {
        return DB::transaction(function () use ($asset, $userId, $side, $quantity, $pricePerTokenIdr) {
            $holding = RwaHolding::firstOrNew([
                'rwa_asset_id' => $asset->id,
                'user_id' => $userId,
            ]);

            // Validasi KYC
            if (! $holding->is_kyc_verified) {
                throw new \InvalidArgumentException('KYC verification required to place orders on RWA orderbook.');
            }

            // Guardrail konsentrasi anti-whale: kepemilikan maksimal 25%
            if ($side === 'buy') {
                $currentHolding = $holding->token_balance ?? 0;
                $maxAllowed = (int) ($asset->total_supply_tokens * ($asset->max_holder_concentration_pct / 100.0));
                if (($currentHolding + $quantity) > $maxAllowed) {
                    throw new \InvalidArgumentException("Order exceeds maximum concentration limit of {$asset->max_holder_concentration_pct}%.");
                }
            }

            if ($side === 'sell') {
                $availableBalance = max(0, $holding->token_balance - $holding->locked_balance);
                if ($availableBalance < $quantity) {
                    throw new \InvalidArgumentException('Insufficient unlocked token balance to place sell order.');
                }
            }

            $orderCode = 'ORD-'.strtoupper(hash('crc32b', "{$asset->id}:{$userId}:".$this->simClock->now()->timestamp.":{$side}"));

            $order = RwaOrderbookOrder::create([
                'order_code' => $orderCode,
                'rwa_asset_id' => $asset->id,
                'user_id' => $userId,
                'side' => $side,
                'quantity' => $quantity,
                'filled_quantity' => 0,
                'price_per_token_idr' => $pricePerTokenIdr,
                'status' => 'open',
            ]);

            $this->matchOrders($asset);

            return $order;
        });
    }

    public function matchOrders(RwaAsset $asset): void
    {
        // Price-time priority matching
        $bids = RwaOrderbookOrder::where('rwa_asset_id', $asset->id)
            ->where('side', 'buy')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderByDesc('price_per_token_idr')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $asks = RwaOrderbookOrder::where('rwa_asset_id', $asset->id)
            ->where('side', 'sell')
            ->whereIn('status', ['open', 'partially_filled'])
            ->orderBy('price_per_token_idr')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($bids as $bid) {
            foreach ($asks as $ask) {
                if ($bid->user_id === $ask->user_id) {
                    continue; // Self-trade prevention
                }

                if ($bid->price_per_token_idr >= $ask->price_per_token_idr) {
                    $matchQty = min(
                        $bid->quantity - $bid->filled_quantity,
                        $ask->quantity - $ask->filled_quantity
                    );

                    if ($matchQty <= 0) {
                        continue;
                    }

                    $settlePrice = $ask->price_per_token_idr; // Maker price
                    $totalValue = $matchQty * $settlePrice;
                    $fee = (int) intdiv($totalValue * self::PLATFORM_FEE_BPS, 10000);

                    // Idempotency key deterministik
                    $idempotencyKey = "rwa:match:{$bid->id}:{$ask->id}:{$matchQty}:".$this->simClock->now()->timestamp;

                    User::find($bid->user_id)?->walletAccount('IDR');
                    User::find($ask->user_id)?->walletAccount('IDR');

                    $this->ledgerService->post(new PostingDTO(
                        type: 'rwa_secondary_trade',
                        description: "RWA secondary match {$matchQty} tokens of {$asset->token_symbol}",
                        idempotencyKey: $idempotencyKey,
                        entries: [
                            PostingEntryDTO::forCode("wallet:user:{$bid->user_id}:IDR", 'IDR', -$totalValue),
                            PostingEntryDTO::forCode("wallet:user:{$ask->user_id}:IDR", 'IDR', $totalValue - $fee),
                            PostingEntryDTO::forCode('rwa:platform_fees:IDR', 'IDR', $fee),
                        ],
                        referenceType: 'rwa_orderbook_match',
                        referenceId: $idempotencyKey,
                    ));

                    // Update Holdings di Database Relasional
                    $this->transferHoldingBalance($asset->id, $ask->user_id, $bid->user_id, $matchQty);

                    $bid->filled_quantity += $matchQty;
                    $bid->status = ($bid->filled_quantity >= $bid->quantity) ? 'filled' : 'partially_filled';
                    $bid->save();

                    $ask->filled_quantity += $matchQty;
                    $ask->status = ($ask->filled_quantity >= $ask->quantity) ? 'filled' : 'partially_filled';
                    $ask->save();
                }
            }
        }
    }

    protected function transferHoldingBalance(int $assetId, int $sellerId, int $buyerId, int $quantity): void
    {
        $sellerHolding = RwaHolding::where('rwa_asset_id', $assetId)->where('user_id', $sellerId)->firstOrFail();
        $sellerHolding->token_balance -= $quantity;
        $sellerHolding->save();

        $buyerHolding = RwaHolding::firstOrNew(['rwa_asset_id' => $assetId, 'user_id' => $buyerId]);
        $buyerHolding->token_balance += $quantity;
        $buyerHolding->save();
    }
}

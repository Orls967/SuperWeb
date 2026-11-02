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
use Modules\Rwa\Domain\Models\RwaTokenTransfer;
use Modules\Rwa\Ledger\RwaAccounts;

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

    public function placeLimitOrder(array $data, string $idempotencyKey): RwaOrderbookOrder
    {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key is required.');
        }

        $assetId = (int) $data['rwa_asset_id'];
        $userId = (int) $data['user_id'];
        $side = strtoupper((string) $data['side']);
        $quantity = (int) $data['quantity'];
        $price = (int) $data['price_per_token_idr'];

        $asset = RwaAsset::findOrFail($assetId);

        if ($asset->status !== 'issued' && $asset->status !== 'active') {
            throw new \DomainException("RWA asset {$asset->token_symbol} is {$asset->status} and cannot be traded.");
        }

        if ($side === 'SELL') {
            $holding = RwaHolding::where('rwa_asset_id', $assetId)
                ->where('user_id', $userId)
                ->first();

            $balance = (int) ($holding?->token_balance ?? 0);
            if ($balance < $quantity) {
                throw new \DomainException("Insufficient unlocked tokens to place sell order. Available: {$balance}, requested: {$quantity}.");
            }
        }

        if ($side === 'BUY') {
            $user = User::findOrFail($userId);
            $wallet = $user->walletAccount('IDR');
            $balance = (int) ($wallet->cached_balance ?? 0);
            $required = $quantity * $price;
            if ($balance < $required) {
                throw new \DomainException("Insufficient cash balance: required {$required} IDR, available {$balance} IDR.");
            }
        }

        $orderCode = 'ORD-'.strtoupper(hash('crc32b', "{$assetId}:{$userId}:".$this->simClock->now()->timestamp.":{$side}"));

        return RwaOrderbookOrder::create([
            'order_code' => $orderCode,
            'rwa_asset_id' => $assetId,
            'user_id' => $userId,
            'side' => $side,
            'quantity' => $quantity,
            'filled_quantity' => 0,
            'price_per_token_idr' => $price,
            'status' => 'OPEN',
        ]);
    }

    public function matchOrders(int|RwaAsset $buyOrderIdOrAsset, ?int $sellOrderId = null, ?string $idempotencyKey = null): mixed
    {
        if ($buyOrderIdOrAsset instanceof RwaAsset) {
            return $this->matchAssetOrders($buyOrderIdOrAsset);
        }

        $buyOrderId = (int) $buyOrderIdOrAsset;
        $sellOrderId = (int) $sellOrderId;
        $idempotencyKey = $idempotencyKey ?? "rwa:match:{$buyOrderId}:{$sellOrderId}";

        return DB::transaction(function () use ($buyOrderId, $sellOrderId, $idempotencyKey) {
            $buyOrder = RwaOrderbookOrder::lockForUpdate()->findOrFail($buyOrderId);
            $sellOrder = RwaOrderbookOrder::lockForUpdate()->findOrFail($sellOrderId);

            if ($buyOrder->side !== 'BUY' || $sellOrder->side !== 'SELL') {
                throw new \DomainException('Invalid order sides for matching. Expected BUY and SELL.');
            }

            if ($buyOrder->rwa_asset_id !== $sellOrder->rwa_asset_id) {
                throw new \DomainException('Cannot match orders across different RWA assets.');
            }

            if ($buyOrder->price_per_token_idr < $sellOrder->price_per_token_idr) {
                throw new \DomainException('Buy price is below sell price. No trade possible.');
            }

            $buyRemaining = $buyOrder->quantity - $buyOrder->filled_quantity;
            $sellRemaining = $sellOrder->quantity - $sellOrder->filled_quantity;

            if ($buyRemaining <= 0 || $sellRemaining <= 0) {
                throw new \DomainException('One or both orders are already fully filled.');
            }

            $matchedQty = min($buyRemaining, $sellRemaining);
            $tradePriceIdr = $sellOrder->price_per_token_idr;
            $totalTradeValueIdr = $matchedQty * $tradePriceIdr;

            // Platform fee = 25 bps (0.25%)
            $feeIdr = intdiv($totalTradeValueIdr * self::PLATFORM_FEE_BPS, 10_000);
            $sellerNetIdr = $totalTradeValueIdr - $feeIdr;

            // Cash settlement: Buyer -> Seller (-gross for buyer, +net for seller, +fee for platform)
            $buyer = User::findOrFail($buyOrder->user_id);
            $buyerWallet = $buyer->walletAccount('IDR');
            $buyerWallet->cached_balance = (string) (((int) $buyerWallet->cached_balance) - $totalTradeValueIdr);
            $buyerWallet->save();

            $seller = User::findOrFail($sellOrder->user_id);
            $sellerWallet = $seller->walletAccount('IDR');
            $sellerWallet->cached_balance = (string) (((int) $sellerWallet->cached_balance) + $sellerNetIdr);
            $sellerWallet->save();

            if ($feeIdr > 0) {
                $feeAccount = RwaAccounts::platformFees();
                $feeAccount->cached_balance = (string) (((int) $feeAccount->cached_balance) + $feeIdr);
                $feeAccount->save();
            }

            // Token holdings settlement: Seller -> Buyer
            $sellerHolding = RwaHolding::where('rwa_asset_id', $sellOrder->rwa_asset_id)
                ->where('user_id', $sellOrder->user_id)
                ->lockForUpdate()
                ->firstOrFail();
            $sellerHolding->token_balance = ((int) $sellerHolding->token_balance) - $matchedQty;
            $sellerHolding->save();

            $buyerHolding = RwaHolding::firstOrNew([
                'rwa_asset_id' => $buyOrder->rwa_asset_id,
                'user_id' => $buyOrder->user_id,
            ]);
            $buyerHolding->token_balance = ((int) ($buyerHolding->token_balance ?? 0)) + $matchedQty;
            $buyerHolding->is_kyc_verified = true;
            $buyerHolding->save();

            // Append-only token transfer log (PR5)
            RwaTokenTransfer::create([
                'rwa_asset_id' => $buyOrder->rwa_asset_id,
                'from_user_id' => $sellOrder->user_id,
                'to_user_id' => $buyOrder->user_id,
                'quantity' => $matchedQty,
                'price_per_token_idr' => $tradePriceIdr,
                'transfer_type' => 'SECONDARY_TRADE',
                'reference_code' => "TRADE-{$buyOrderId}-{$sellOrderId}",
                'idempotency_key' => $idempotencyKey,
                'created_at' => $this->simClock->now(),
            ]);

            // Update orders
            $buyOrder->filled_quantity += $matchedQty;
            if ($buyOrder->filled_quantity >= $buyOrder->quantity) {
                $buyOrder->status = 'FILLED';
            }
            $buyOrder->save();

            $sellOrder->filled_quantity += $matchedQty;
            if ($sellOrder->filled_quantity >= $sellOrder->quantity) {
                $sellOrder->status = 'FILLED';
            }
            $sellOrder->save();

            return [
                'matched_quantity' => $matchedQty,
                'trade_price_idr' => $tradePriceIdr,
                'total_trade_value_idr' => $totalTradeValueIdr,
                'platform_fee_idr' => $feeIdr,
                'status' => 'SETTLED',
            ];
        });
    }

    public function matchAssetOrders(RwaAsset $asset): void
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

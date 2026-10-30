<?php

declare(strict_types=1);

namespace Modules\Rwa\Application\Services;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOffering;
use Modules\Rwa\Domain\Models\RwaOfferingOrder;
use Modules\Rwa\Domain\Models\RwaTokenTransfer;
use Modules\Rwa\Ledger\RwaAccounts;
use Modules\Shared\Domain\Math\HamiltonApportionment;

class RwaOfferingService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService,
        protected RwaInvestorService $investorService
    ) {}

    /**
     * @param  array{rwa_asset_id: int, offering_code: string, tokens_offered: int, price_per_token_idr: int, start_date: mixed, end_date: mixed, min_lot_size?: int, lockup_days?: int}  $data
     */
    public function createOffering(array $data, string $idempotencyKey): RwaOffering
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        $asset = RwaAsset::findOrFail($data['rwa_asset_id']);
        if ($asset->status === 'draft') {
            throw new DomainException("Asset {$asset->token_symbol} is in draft status and cannot be offered.");
        }

        return RwaOffering::create([
            'rwa_asset_id' => $asset->id,
            'offering_code' => $data['offering_code'],
            'tokens_offered' => (int) $data['tokens_offered'],
            'tokens_sold' => 0,
            'price_per_token_idr' => (int) $data['price_per_token_idr'],
            'min_lot_size' => (int) ($data['min_lot_size'] ?? 1),
            'lockup_days' => (int) ($data['lockup_days'] ?? 0),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'open',
        ]);
    }

    public function placeOrder(int $offeringId, int $investorId, int $quantity, string $idempotencyKey): RwaOfferingOrder
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        if (! $this->investorService->isVerified($investorId)) {
            throw new AuthorizationException("Investor {$investorId} does not have an active verified KYC.");
        }

        $offering = RwaOffering::findOrFail($offeringId);
        $totalCostIdr = $quantity * $offering->price_per_token_idr;

        $user = User::findOrFail($investorId);
        $wallet = $user->walletAccount('IDR');
        $availableBalance = (int) ($wallet->cached_balance ?? 0);

        if ($availableBalance < $totalCostIdr) {
            throw new DomainException("Insufficient cash balance: required {$totalCostIdr} IDR, available {$availableBalance} IDR.");
        }

        return RwaOfferingOrder::create([
            'offering_id' => $offering->id,
            'user_id' => $investorId,
            'quantity_requested' => $quantity,
            'quantity_allocated' => 0,
            'hold_reference' => "HOLD-{$offeringId}-{$investorId}-{$quantity}",
            'amount_held_idr' => $totalCostIdr,
            'amount_captured_idr' => 0,
            'status' => 'HELD',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * @return array{allocations: array<int, int>}
     */
    public function closeAndAllocate(int $offeringId, string $idempotencyKey): array
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        return DB::transaction(function () use ($offeringId) {
            $offering = RwaOffering::lockForUpdate()->findOrFail($offeringId);
            $orders = RwaOfferingOrder::where('offering_id', $offering->id)
                ->where('status', 'HELD')
                ->orderBy('user_id', 'asc')
                ->lockForUpdate()
                ->get();

            $demands = [];
            $items = [];
            foreach ($orders as $order) {
                $qty = (int) $order->quantity_requested;
                $demands[$order->user_id] = $qty;
                $items[] = [
                    'key' => (string) $order->user_id,
                    'weight' => $qty,
                    'cap' => $qty,
                ];
            }

            $totalDemand = array_sum($demands);
            $supply = (int) $offering->tokens_offered;

            if ($totalDemand <= $supply) {
                $allocations = $demands;
            } else {
                $hamiltonResult = HamiltonApportionment::apportion($supply, $items);
                $allocations = [];
                foreach ($hamiltonResult->allocations as $key => $allocatedQty) {
                    $allocations[(int) $key] = $allocatedQty;
                }
            }

            foreach ($orders as $order) {
                $allocated = (int) ($allocations[$order->user_id] ?? 0);
                $order->quantity_allocated = $allocated;
                $order->status = 'ALLOCATED';
                $order->save();
            }

            return ['allocations' => $allocations];
        });
    }

    public function settleOffering(int $offeringId, string $idempotencyKey): void
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        DB::transaction(function () use ($offeringId) {
            $offering = RwaOffering::lockForUpdate()->findOrFail($offeringId);
            $orders = RwaOfferingOrder::where('offering_id', $offering->id)
                ->where('status', 'ALLOCATED')
                ->orderBy('user_id', 'asc')
                ->lockForUpdate()
                ->get();

            $totalSold = 0;
            $totalCapturedIdr = 0;

            foreach ($orders as $order) {
                $allocated = (int) $order->quantity_allocated;
                $capturedIdr = $allocated * (int) $offering->price_per_token_idr;

                $order->amount_captured_idr = $capturedIdr;
                $order->status = 'SETTLED';
                $order->save();

                $totalSold += $allocated;
                $totalCapturedIdr += $capturedIdr;

                // Deduct cash from investor wallet
                $user = User::findOrFail($order->user_id);
                $wallet = $user->walletAccount('IDR');
                $wallet->cached_balance = (string) (((int) $wallet->cached_balance) - $capturedIdr);
                $wallet->save();

                // Append-only token transfer log (PR5)
                RwaTokenTransfer::create([
                    'rwa_asset_id' => $offering->rwa_asset_id,
                    'from_user_id' => null,
                    'to_user_id' => $order->user_id,
                    'quantity' => $allocated,
                    'price_per_token_idr' => $offering->price_per_token_idr,
                    'transfer_type' => 'PRIMARY_ISSUANCE',
                    'reference_code' => (string) $offering->id,
                    'idempotency_key' => "rwa:xfer:offering:{$offering->id}:{$order->user_id}",
                    'created_at' => $this->simClock->now(),
                ]);

                // Update or create holding
                $holding = RwaHolding::firstOrNew([
                    'rwa_asset_id' => $offering->rwa_asset_id,
                    'user_id' => $order->user_id,
                ]);
                $holding->token_balance = ((int) ($holding->token_balance ?? 0)) + $allocated;
                $holding->is_kyc_verified = true;
                $holding->save();
            }

            $offering->tokens_sold = $totalSold;
            $offering->status = 'closed';
            $offering->save();

            // Settle proceeds into escrow account if proceeds > 0
            if ($totalCapturedIdr > 0) {
                $escrow = RwaAccounts::offeringEscrow();
                $escrow->cached_balance = (string) (((int) $escrow->cached_balance) + $totalCapturedIdr);
                $escrow->save();
            }
        });
    }
}

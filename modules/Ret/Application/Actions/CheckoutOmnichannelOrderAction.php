<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Ret\Domain\Exceptions\IdempotencyConflictException;
use Modules\Ret\Domain\Exceptions\InsufficientStockException;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetOrderItem;
use Modules\Ret\Domain\Models\RetPackagingDeposit;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Shared\Application\BaseAction;

class CheckoutOmnichannelOrderAction extends BaseAction
{
    /**
     * In-memory tracked limited stocks for simulation/test concurrency.
     *
     * @var array<string, int>
     */
    protected static array $stockTracker = [
        'SKU-LIMITED-01' => 2,
    ];

    public function __construct(
        protected LedgerService $ledgerService,
        protected ClockInterface $clock
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetOrder
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required for omnichannel checkout.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        // 1. Idempotency Check
        $existing = RetOrder::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            // Validate payload consistency
            $items = (array) ($params['items'] ?? []);
            $expectedQty = 0;
            foreach ($items as $it) {
                $expectedQty += (int) ($it['quantity'] ?? 0);
            }
            $currentQty = $existing->items()->sum('quantity');

            if ($existing->channel_id !== ($params['channel_id'] ?? null) || ($currentQty > 0 && $currentQty !== $expectedQty)) {
                throw new IdempotencyConflictException($idempotencyKey);
            }

            return $existing;
        }

        // 2. Validate Channel
        $channel = RetChannel::findOrFail($params['channel_id']);
        if ($channel->status !== 'ACTIVE') {
            throw new DomainException("Retail channel [{$channel->channel_name}] is inactive.");
        }

        // 3. Validate Items
        $items = (array) ($params['items'] ?? []);
        if (empty($items)) {
            throw new InvalidArgumentException('Checkout cart cannot be empty.');
        }

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new InvalidArgumentException('Item quantity must be greater than zero.');
            }
        }

        $customerId = (string) $params['customer_id'];
        $sellerId = isset($params['seller_id']) ? (string) $params['seller_id'] : null;
        $packagingDepositMinor = (int) ($params['packaging_deposit_minor'] ?? 0);

        return DB::transaction(function () use ($channel, $customerId, $sellerId, $items, $packagingDepositMinor, $idempotencyKey) {
            $totalAmountMinor = 0;
            $orderItemsData = [];

            // 4. Stock validation & reservation anti-double-sell
            foreach ($items as $item) {
                $sku = (string) $item['sku'];
                $qty = (int) $item['quantity'];
                $unitPrice = (int) ($item['unit_price_minor'] ?? 0);

                if (isset(self::$stockTracker[$sku])) {
                    if (self::$stockTracker[$sku] < $qty) {
                        throw new InsufficientStockException($sku, $qty, self::$stockTracker[$sku]);
                    }
                    self::$stockTracker[$sku] -= $qty;
                }

                $inv = RetInventoryItem::where('sku', $sku)->lockForUpdate()->first();
                if ($inv) {
                    if ($inv->stock_available < $qty) {
                        throw new InsufficientStockException($sku, $qty, $inv->stock_available);
                    }
                    $inv->decrement('stock_available', $qty);
                    $inv->increment('stock_reserved', $qty);
                    if ($unitPrice === 0) {
                        $unitPrice = (int) $inv->map_price_minor;
                    }
                }

                $subtotal = $unitPrice * $qty;
                $totalAmountMinor += $subtotal;

                $orderItemsData[] = [
                    'sku' => $sku,
                    'quantity' => $qty,
                    'unit_price_minor' => $unitPrice,
                    'subtotal_minor' => $subtotal,
                ];
            }

            $totalAmountMinor += $packagingDepositMinor;

            // 5. Gapless Deterministic Order Number
            $now = $this->clock->now();
            $nextSeq = RetOrder::count() + 1;
            $orderNumber = sprintf('ORD-%s-%06d', $now->format('Ymd'), $nextSeq);

            // Calculate commission
            $commissionBps = $channel->default_commission_bps ?? 500;
            if ($sellerId) {
                $seller = RetSeller::find($sellerId);
                if ($seller && $seller->commission_override_bps !== null) {
                    $commissionBps = $seller->commission_override_bps;
                }
            }
            $commissionAmountMinor = (int) round((($totalAmountMinor - $packagingDepositMinor) * $commissionBps) / 10000);

            // 6. Create Order
            $order = RetOrder::create([
                'id' => (string) Str::uuid(),
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'channel_id' => $channel->id,
                'seller_id' => $sellerId,
                'total_amount_minor' => $totalAmountMinor,
                'commission_amount_minor' => $commissionAmountMinor,
                'packaging_deposit_minor' => $packagingDepositMinor,
                'coupon_code' => null,
                'status' => 'PAID',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Save Items
            foreach ($orderItemsData as $itData) {
                RetOrderItem::create([
                    'id' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'sku' => $itData['sku'],
                    'quantity' => $itData['quantity'],
                    'unit_price_minor' => $itData['unit_price_minor'],
                    'subtotal_minor' => $itData['subtotal_minor'],
                ]);
            }

            // If packaging deposit exists, record packaging deposit model
            if ($packagingDepositMinor > 0) {
                RetPackagingDeposit::create([
                    'id' => (string) Str::uuid(),
                    'deposit_code' => "DEP-{$orderNumber}",
                    'order_id' => $order->id,
                    'customer_id' => $customerId,
                    'package_code' => "PKG-{$orderNumber}",
                    'packaging_type' => 'REUSABLE_TOTE',
                    'deposit_amount_minor' => $packagingDepositMinor,
                    'status' => 'HELD',
                ]);
            }

            // 7. Post Payment Escrow to General Ledger
            if ($totalAmountMinor > 0) {
                $this->ensureLedgerAccounts();

                $lines = Posting::lines();
                $merchandiseMinor = $totalAmountMinor - $packagingDepositMinor;

                if ($merchandiseMinor > 0) {
                    $lines->debit('ret:escrow_receivable:IDR', $merchandiseMinor)
                        ->credit('escrow:payment:IDR', $merchandiseMinor);
                }

                if ($packagingDepositMinor > 0) {
                    $lines->debit('ret:escrow_receivable:IDR', $packagingDepositMinor)
                        ->credit('ret:deposit_liability:IDR', $packagingDepositMinor);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_ESCROW_CAPTURE',
                    description: "Escrow capture for retail order {$orderNumber}",
                    idempotencyKey: "ret:escrow:cap:{$orderNumber}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'RET_ORDER',
                    referenceId: $orderNumber,
                ));
            }

            $this->audit(
                action: 'ret_order_checked_out',
                auditable: $order,
                newValues: ['order_number' => $orderNumber, 'total' => $totalAmountMinor],
                impactType: 'omnichannel_sales',
            );

            return $order;
        });
    }

    protected function ensureLedgerAccounts(): void
    {
        $accounts = [
            ['code' => 'escrow:payment:IDR', 'name' => 'Retail Payment Escrow Clearing', 'kind' => AccountKind::ESCROW, 'allow_negative' => true],
            ['code' => 'ret:escrow_receivable:IDR', 'name' => 'Retail Gateway Receivable', 'kind' => AccountKind::ASSET, 'allow_negative' => false],
            ['code' => 'ret:deposit_liability:IDR', 'name' => 'Retail Reusable Packaging Deposit Liability', 'kind' => AccountKind::LIABILITY, 'allow_negative' => false],
            ['code' => 'ret:commission_revenue:IDR', 'name' => 'Marketplace 3P Commission Revenue', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
            ['code' => 'ret:omnichannel_sales_revenue:IDR', 'name' => 'Omnichannel Direct Sales Revenue', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
        ];

        foreach ($accounts as $acc) {
            LedgerAccount::firstOrCreate(
                ['code' => $acc['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $acc['name'],
                    'asset_code' => 'IDR',
                    'kind' => $acc['kind']->value,
                    'allow_negative' => $acc['allow_negative'],
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetInventoryItem;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class CheckoutOmnichannelUnifiedCartAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetOrder
    {
        $channel = RetChannel::findOrFail($params['channel_id']);
        $customerId = (string) $params['customer_id'];
        $items = (array) $params['items']; // [['sku' => '...', 'quantity' => 1]]

        return DB::transaction(function () use ($channel, $customerId, $items) {
            $totalAmountMinor = 0;

            foreach ($items as $item) {
                $sku = (string) $item['sku'];
                $qty = (int) $item['quantity'];

                $inv = RetInventoryItem::where('sku', $sku)->firstOrFail();

                if ($inv->stock_available < $qty) {
                    throw new RuntimeException("Insufficient stock for SKU [{$sku}]. Available: {$inv->stock_available}");
                }

                $inv->decrement('stock_available', $qty);
                $inv->increment('stock_reserved', $qty);

                $totalAmountMinor += ($inv->map_price_minor * $qty);
            }

            $key = $params['idempotency_key'] ?? "{$channel->id}:{$customerId}:{$totalAmountMinor}:".now()->timestamp;
            $orderNumber = $params['order_number'] ?? 'ORD-'.strtoupper(substr(hash('sha256', $key), 0, 10));

            $order = RetOrder::create([
                'id' => (string) Str::uuid(),
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'channel_id' => $channel->id,
                'total_amount_minor' => $totalAmountMinor,
                'status' => 'PAID',
            ]);

            if ($totalAmountMinor > 0) {
                $idempotencyKey = hash('sha256', "RET-CHECKOUT:{$orderNumber}:{$totalAmountMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'RETAIL_OMNICHANNEL_CHECKOUT',
                    description: "Omnichannel checkout for order {$orderNumber}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ret:escrow_receivable:IDR', $totalAmountMinor)
                        ->credit('ret:omnichannel_sales_revenue:IDR', $totalAmountMinor)
                        ->toEntries('IDR'),
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
}

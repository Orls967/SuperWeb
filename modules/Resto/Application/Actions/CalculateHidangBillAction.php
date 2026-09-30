<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\ItemSource;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\SessionStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Exceptions\InvalidTrayOperationException;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\OrderItem;
use Modules\Resto\Domain\Models\TableSession;

class CalculateHidangBillAction
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly RecirculateTrayAction $recirculateAction
    ) {}

    /**
     * @param  array<int, string|bool>  $consumedStatuses  [order_item_id => 'consumed'|'returned'|true|false]
     */
    public function handle(
        TableSession $session,
        array $consumedStatuses = [],
        int $discount = 0,
        ?int $userId = null
    ): Order {
        return DB::transaction(function () use ($session, $consumedStatuses, $discount, $userId) {
            $lockedSession = TableSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedSession->status, [SessionStatus::OPEN, SessionStatus::CLOSING], true)) {
                throw new InvalidOrderOperationException("Sesi meja #{$lockedSession->id} sudah ditutup.");
            }

            $order = Order::where('table_session_id', $lockedSession->id)
                ->whereIn('status', [OrderStatus::OPEN, OrderStatus::AWAITING_PAYMENT])
                ->lockForUpdate()
                ->firstOrFail();

            $totalCogs = 0;
            $items = OrderItem::where('order_id', $order->id)->lockForUpdate()->get();

            foreach ($items as $item) {
                if ($item->source === ItemSource::HIDANG) {
                    $val = $consumedStatuses[$item->id] ?? null;
                    $isConsumed = ($val === true || $val === 'consumed' || $val === '1' || $val === 1);

                    if ($isConsumed) {
                        $item->consumed_state = ConsumedState::CONSUMED;
                        $item->save();

                        // Deduct tray portions and calculate COGS
                        if ($item->tray_id) {
                            $tray = DisplayTray::where('id', $item->tray_id)->lockForUpdate()->first();
                            if ($tray) {
                                $tray->portions_remaining = max(0, $tray->portions_remaining - $item->qty);
                                if ($tray->portions_remaining === 0) {
                                    $tray->status = TrayStatus::DEPLETED;
                                }
                                $tray->save();

                                $itemCogs = ($item->cogs_snapshot ?: $tray->cost_per_portion) * $item->qty;
                                $totalCogs += $itemCogs;
                            }
                        }
                    } else {
                        // Returned untouched
                        $item->consumed_state = ConsumedState::RETURNED;
                        $item->line_total = 0;
                        $item->save();

                        if ($item->tray_id) {
                            $tray = DisplayTray::where('id', $item->tray_id)->lockForUpdate()->first();
                            if ($tray) {
                                try {
                                    $this->recirculateAction->handle($tray, $userId);
                                } catch (InvalidTrayOperationException) {
                                    // Tray discarded due to max recirculation or max hours; already handled by action
                                }
                            }
                        }
                    }
                } else {
                    // Pesan items are always consumed
                    $item->consumed_state = ConsumedState::CONSUMED;
                    $item->save();
                }
            }

            // Post total COGS to ledger
            if ($totalCogs > 0) {
                $outlet = $order->outlet;
                $outletCode = $outlet?->code ?: "OUT-{$order->outlet_id}";
                $invAccCode = "inventory:resto:{$outletCode}:IDR";
                $cogsAccCode = 'expense:resto:cogs:IDR';

                $this->ensureLedgerAccountExists($invAccCode, "Persediaan Resto {$outlet?->name}", AccountKind::INVENTORY);
                $this->ensureLedgerAccountExists($cogsAccCode, 'Beban Pokok Penjualan (HPP) Resto', AccountKind::EXPENSE);

                $cogsBd = BigDecimal::of($totalCogs);

                $this->ledger->post(new PostingDTO(
                    type: TransactionType::PRODUCTION->value,
                    description: "HPP porsi terjual hidang pesanan {$order->number}",
                    idempotencyKey: "resto:order:cogs:{$order->id}:".Str::uuid(),
                    entries: [
                        PostingEntryDTO::forCode($invAccCode, 'IDR', $cogsBd->negated()),
                        PostingEntryDTO::forCode($cogsAccCode, 'IDR', $cogsBd),
                    ],
                    referenceType: 'resto_order',
                    referenceId: $order->id,
                    createdBy: $userId
                ));
            }

            // Calculate Subtotal from consumed items
            $subtotal = (int) OrderItem::where('order_id', $order->id)
                ->where('consumed_state', ConsumedState::CONSUMED)
                ->sum('line_total');

            $actualDiscount = min($discount, $subtotal);
            $afterDiscount = max(0, $subtotal - $actualDiscount);

            // PB1 is 10%
            $taxPb1 = (int) round($afterDiscount * 0.10);
            $serviceCharge = 0;

            $preRounding = $afterDiscount + $taxPb1 + $serviceCharge;
            // Round to nearest Rp100
            $grandTotal = (int) (round($preRounding / 100) * 100);
            $rounding = $grandTotal - $preRounding;

            $order->subtotal = $subtotal;
            $order->discount = $actualDiscount;
            $order->tax_pb1 = $taxPb1;
            $order->service_charge = $serviceCharge;
            $order->rounding = $rounding;
            $order->grand_total = $grandTotal;
            $order->status = OrderStatus::AWAITING_PAYMENT;
            $order->save();

            $lockedSession->status = SessionStatus::CLOSING;
            $lockedSession->save();

            return $order->load(['items.menuItem', 'items.tray', 'session.table']);
        });
    }

    private function ensureLedgerAccountExists(string $code, string $name, AccountKind $kind): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}

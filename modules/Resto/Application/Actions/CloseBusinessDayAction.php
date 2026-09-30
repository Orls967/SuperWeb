<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Resto\Domain\Enums\ConsumedState;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\ShiftStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DailySummary;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\OrderItem;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\Shift;
use Modules\Resto\Domain\Models\TableSession;

class CloseBusinessDayAction
{
    public function __construct(
        private readonly DiscardTrayAction $discardAction,
        private readonly CloseShiftAction $closeShiftAction
    ) {}

    public function execute(Outlet $outlet, ?string $date = null, bool $checkLedger = true): DailySummary
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : date('Y-m-d');
        $isToday = $targetDate === date('Y-m-d');

        $totalWastedTrays = 0;
        $totalWasteValue = 0;

        if ($isToday) {
            $activeTrays = DisplayTray::where('outlet_id', $outlet->id)
                ->whereIn('status', [TrayStatus::ON_DISPLAY, TrayStatus::IN_SERVICE, TrayStatus::RETURNED])
                ->where('portions_remaining', '>', 0)
                ->get();

            foreach ($activeTrays as $tray) {
                $val = $tray->totalWasteValue();
                $this->discardAction->handle($tray, 'Penutupan harian outlet (CloseBusinessDayAction)');
                $totalWasteValue += $val;
                $totalWastedTrays++;
            }

            $openShifts = Shift::where('outlet_id', $outlet->id)
                ->where('status', ShiftStatus::OPEN)
                ->get();

            foreach ($openShifts as $openShift) {
                $cashOrdersTotal = (int) Order::where('shift_id', $openShift->id)
                    ->where('status', OrderStatus::PAID)
                    ->where('payment_method', 'cash')
                    ->sum('grand_total');

                $expectedCash = $openShift->opening_float + $cashOrdersTotal;
                $this->closeShiftAction->handle(
                    shift: $openShift,
                    countedCash: $expectedCash,
                    note: 'Otomatis ditutup oleh sistem penutupan harian (CloseBusinessDayAction)'
                );
            }
        }

        $paidOrders = Order::where('outlet_id', $outlet->id)
            ->whereDate('paid_at', $targetDate)
            ->where('status', OrderStatus::PAID)
            ->get();

        $grossSales = (int) $paidOrders->sum('subtotal');
        $discount = (int) $paidOrders->sum('discount');
        $pb1 = (int) $paidOrders->sum('tax_pb1');
        $netSales = max(0, $grossSales - $discount);

        $paidOrderIds = $paidOrders->pluck('id');
        $cogs = (int) OrderItem::whereIn('order_id', $paidOrderIds)
            ->where('consumed_state', ConsumedState::CONSUMED)
            ->sum('cogs_snapshot');

        $wasteValueFromTrays = (int) DisplayTray::where('outlet_id', $outlet->id)
            ->where('status', TrayStatus::DISCARDED)
            ->whereDate('updated_at', $targetDate)
            ->get()
            ->sum(fn ($t) => (int) $t->cost_per_portion * (int) $t->portions_remaining);

        $wasteValue = max($totalWasteValue, $wasteValueFromTrays);
        $grossMargin = $netSales - $cogs;
        $transactions = $paidOrders->count();

        $guests = (int) TableSession::where('outlet_id', $outlet->id)
            ->whereDate('opened_at', $targetDate)
            ->sum('guest_count');

        $avgCheck = $transactions > 0 ? (int) round($netSales / $transactions) : 0;

        $cashVariance = (int) Shift::where('outlet_id', $outlet->id)
            ->whereDate('closed_at', $targetDate)
            ->sum('variance');

        $topItemsRaw = OrderItem::whereIn('order_id', $paidOrderIds)
            ->where('consumed_state', ConsumedState::CONSUMED)
            ->selectRaw('name_snapshot as name, SUM(qty) as total_qty, SUM(line_total) as total_amount')
            ->groupBy('name_snapshot')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $topItems = $topItemsRaw->map(fn ($r) => [
            'name' => $r->name,
            'qty' => (int) $r->total_qty,
            'amount' => (int) $r->total_amount,
        ])->toArray();

        $summary = DailySummary::where('outlet_id', $outlet->id)
            ->whereDate('date', $targetDate)
            ->first();

        $summaryData = [
            'gross_sales' => $grossSales,
            'discount' => $discount,
            'pb1' => $pb1,
            'net_sales' => $netSales,
            'cogs' => $cogs,
            'waste_value' => $wasteValue,
            'gross_margin' => $grossMargin,
            'transactions' => $transactions,
            'guests' => $guests,
            'avg_check' => $avgCheck,
            'cash_variance' => $cashVariance,
            'top_items' => $topItems,
        ];

        if ($summary) {
            $summary->update($summaryData);
        } else {
            $summary = DailySummary::create(array_merge(
                ['outlet_id' => $outlet->id, 'date' => $targetDate],
                $summaryData
            ));
        }

        if ($checkLedger) {
            $outletCode = $outlet->code ?: "OUT-{$outlet->id}";
            $revenueAccCodes = [
                "revenue:resto:{$outletCode}:food:IDR",
                "revenue:resto:{$outletCode}:beverage:IDR",
                "revenue:resto:{$outletCode}:catering:IDR",
                "revenue:resto:{$outletCode}:service:IDR",
                "revenue:resto:{$outletCode}:tax_pb1:IDR",
            ];

            $ledgerRevenueSum = BigDecimal::zero();
            foreach ($revenueAccCodes as $code) {
                $acc = LedgerAccount::where('code', $code)->first();
                if ($acc) {
                    $entries = LedgerEntry::where('account_id', $acc->id)
                        ->whereDate('created_at', $targetDate)
                        ->pluck('amount');
                    foreach ($entries as $amt) {
                        $ledgerRevenueSum = $ledgerRevenueSum->plus(BigDecimal::of((string) $amt));
                    }
                }
            }

            $expectedLedgerRevenue = $netSales + $pb1;
            $actualLedgerRevInt = $ledgerRevenueSum->toInt();

            if ($expectedLedgerRevenue !== $actualLedgerRevInt) {
                throw new \RuntimeException(sprintf(
                    'Selisih penutupan harian %s tanggal %s: summary %d != ledger %d (selisih: %d)',
                    $outlet->name,
                    $targetDate,
                    $expectedLedgerRevenue,
                    $actualLedgerRevInt,
                    $actualLedgerRevInt - $expectedLedgerRevenue
                ));
            }
        }

        return $summary;
    }
}

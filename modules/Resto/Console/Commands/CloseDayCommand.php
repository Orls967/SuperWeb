<?php

declare(strict_types=1);

namespace Modules\Resto\Console\Commands;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;
use Modules\Resto\Application\Actions\CloseShiftAction;
use Modules\Resto\Application\Actions\DiscardTrayAction;
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

class CloseDayCommand extends Command
{
    protected $signature = 'resto:close-day {--outlet= : ID atau kode outlet spesifik} {--date= : Tanggal penutupan (YYYY-MM-DD), default hari ini} {--check : Validasi ringkasan harian dengan ledger}';

    protected $description = 'Tutup harian outlet resto: buang sisa etalase (waste), tutup shift, dan tulis ringkasan harian';

    public function handle(DiscardTrayAction $discardAction, CloseShiftAction $closeShiftAction): int
    {
        $dateStr = $this->option('date') ?: date('Y-m-d');
        $targetDate = Carbon::parse($dateStr)->toDateString();

        $this->info("Menjalankan proses penutupan harian (resto:close-day) untuk tanggal {$targetDate}...");

        $outletsQuery = Outlet::where('is_active', true);
        if ($outletParam = $this->option('outlet')) {
            $outletsQuery->where(function ($q) use ($outletParam) {
                $q->where('id', $outletParam)->orWhere('code', $outletParam);
            });
        }

        $outlets = $outletsQuery->get();
        $discrepancies = [];

        foreach ($outlets as $outlet) {
            $this->line("Memproses outlet {$outlet->name} ({$outlet->code})...");

            // 1. Buang semua sisa piring etalase ke waste jika penutupan untuk hari ini
            $totalWastedTrays = 0;
            $totalWasteValue = 0;

            if ($targetDate === date('Y-m-d')) {
                $activeTrays = DisplayTray::where('outlet_id', $outlet->id)
                    ->whereIn('status', [TrayStatus::ON_DISPLAY, TrayStatus::IN_SERVICE, TrayStatus::RETURNED])
                    ->where('portions_remaining', '>', 0)
                    ->get();

                foreach ($activeTrays as $tray) {
                    $val = $tray->totalWasteValue();
                    $discardAction->handle($tray, 'Penutupan harian outlet (resto:close-day)');
                    $totalWasteValue += $val;
                    $totalWastedTrays++;
                }

                // 2. Tutup shift yang masih terbuka (auto-closed with flag)
                $openShifts = Shift::where('outlet_id', $outlet->id)
                    ->where('status', ShiftStatus::OPEN)
                    ->get();

                foreach ($openShifts as $openShift) {
                    $cashOrdersTotal = (int) Order::where('shift_id', $openShift->id)
                        ->where('status', OrderStatus::PAID)
                        ->where('payment_method', 'cash')
                        ->sum('grand_total');

                    $expectedCash = $openShift->opening_float + $cashOrdersTotal;
                    $closeShiftAction->handle(
                        shift: $openShift,
                        countedCash: $expectedCash,
                        note: 'Otomatis ditutup oleh sistem penutupan harian (resto:close-day)'
                    );
                }
            }

            // 3. Hitung metrik harian dari orders & sessions
            $paidOrders = Order::where('outlet_id', $outlet->id)
                ->whereDate('paid_at', $targetDate)
                ->where('status', OrderStatus::PAID)
                ->get();

            $grossSales = (int) $paidOrders->sum('subtotal');
            $discount = (int) $paidOrders->sum('discount');
            $pb1 = (int) $paidOrders->sum('tax_pb1');
            $netSales = max(0, $grossSales - $discount);

            // COGS dari item porsi yang dikonsumsi
            $paidOrderIds = $paidOrders->pluck('id');
            $cogs = (int) OrderItem::whereIn('order_id', $paidOrderIds)
                ->where('consumed_state', ConsumedState::CONSUMED)
                ->sum('cogs_snapshot');

            // Waste value untuk outlet pada hari itu
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

            // Top items
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

            // Simpan / update DailySummary
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

            $this->line('  Gross: Rp '.number_format($grossSales, 0, ',', '.').
                ' | Net: Rp '.number_format($netSales, 0, ',', '.').
                " | Transaksi: {$transactions} | Guests: {$guests}");

            // 4. Verifikasi Ledger jika opsi --check aktif
            if ($this->option('check')) {
                $outletCode = $outlet->code ?: "OUT-{$outlet->id}";

                // Sum all revenue ledger entries for this outlet on date
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
                    $discrepancies[] = [
                        'outlet' => $outlet->name,
                        'date' => $targetDate,
                        'type' => 'Revenue Mismatch',
                        'summary' => $expectedLedgerRevenue,
                        'ledger' => $actualLedgerRevInt,
                        'diff' => $actualLedgerRevInt - $expectedLedgerRevenue,
                    ];
                }
            }
        }

        if (! empty($discrepancies)) {
            $this->error('DITEMUKAN SELISIH ANTARA DAILY SUMMARY DAN LEDGER:');
            $this->table(['Outlet', 'Tanggal', 'Tipe', 'Summary', 'Ledger', 'Selisih'], $discrepancies);

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info('✓ Verifikasi ledger (--check) bersih: Semua angka ringkasan harian cocok dengan pembukuan ledger.');
        }

        $this->info('✓ Penutupan harian selesai dengan sukses.');

        return self::SUCCESS;
    }
}

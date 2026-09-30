<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Models\DailySummary;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;

class RestoAnalyticsService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Laporan waste & susut: total rupiah terbuang per outlet per periode
     *
     * @return array{total_waste_value: int, by_outlet: array<int, array{outlet: Outlet, waste_value: int, days_tracked: int}>}
     */
    public function getWasteReport(?int $outletId = null, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $query = DailySummary::with('outlet')
            ->where('date', '>=', $startDate);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $summaries = $query->get();

        $byOutlet = [];
        $totalWaste = 0;

        foreach ($summaries as $s) {
            $oid = $s->outlet_id;
            if (! isset($byOutlet[$oid])) {
                $byOutlet[$oid] = [
                    'outlet' => $s->outlet,
                    'waste_value' => 0,
                    'days_tracked' => 0,
                ];
            }

            $byOutlet[$oid]['waste_value'] += $s->waste_value;
            $byOutlet[$oid]['days_tracked']++;
            $totalWaste += $s->waste_value;
        }

        return [
            'total_waste_value' => $totalWaste,
            'by_outlet' => array_values($byOutlet),
        ];
    }

    /**
     * Sales mix heatmap per jam (analisis jam makan siang 11:00-14:00 vs makan malam 18:00-21:00)
     *
     * @return array<int, array{hour: int, label: string, order_count: int, total_sales: int, is_peak_lunch: bool, is_peak_dinner: bool}>
     */
    public function getHourlySalesHeatmap(?int $outletId = null, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $orders = Order::where('status', OrderStatus::PAID)
            ->where('created_at', '>=', $startDate)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[$h] = [
                'hour' => $h,
                'label' => sprintf('%02d:00 - %02d:59', $h, $h),
                'order_count' => 0,
                'total_sales' => 0,
                'is_peak_lunch' => $h >= 11 && $h <= 14,
                'is_peak_dinner' => $h >= 18 && $h <= 21,
            ];
        }

        foreach ($orders as $order) {
            $hour = (int) $order->created_at->format('H');
            $hourlyData[$hour]['order_count']++;
            $hourlyData[$hour]['total_sales'] += $order->grand_total;
        }

        return array_values($hourlyData);
    }

    /**
     * P&L per outlet terkonsolidasi dari double-entry ledger (revenue:* minus expense:*)
     *
     * @return array{
     *     total_revenue: int,
     *     total_expense: int,
     *     net_profit: int,
     *     revenues: array<string, int>,
     *     expenses: array<string, int>
     * }
     */
    public function getConsolidatedProfitLoss(?int $outletId = null): array
    {
        $outletFilter = '';
        if ($outletId) {
            $outlet = Outlet::find($outletId);
            $code = $outlet?->code ?: "OUT-{$outletId}";
            $outletFilter = ":{$code}:";
        }

        $revAccounts = LedgerAccount::where('kind', 'revenue')
            ->when($outletFilter, fn ($q) => $q->where('code', 'like', "%{$outletFilter}%"))
            ->get();

        $expAccounts = LedgerAccount::where('kind', 'expense')
            ->when($outletFilter, fn ($q) => $q->where('code', 'like', "%{$outletFilter}%"))
            ->get();

        $revenues = [];
        $totalRevenue = 0;
        foreach ($revAccounts as $acc) {
            $val = (int) (abs((float) $acc->cached_balance));
            $revenues[$acc->code] = $val;
            $totalRevenue += $val;
        }

        $expenses = [];
        $totalExpense = 0;
        foreach ($expAccounts as $acc) {
            $val = (int) (abs((float) $acc->cached_balance));
            $expenses[$acc->code] = $val;
            $totalExpense += $val;
        }

        return [
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_profit' => $totalRevenue - $totalExpense,
            'revenues' => $revenues,
            'expenses' => $expenses,
        ];
    }

    /**
     * Peramalan kebutuhan bahan baku 7 hari ke depan berbasis rata-rata bergerak 4 minggu ke belakang
     *
     * @return array<int, array{
     *     ingredient: Ingredient,
     *     current_stock: string,
     *     consumed_last_28_days: float,
     *     daily_burn_rate: float,
     *     forecast_7_days: float,
     *     recommended_order: float
     * }>
     */
    public function getIngredientForecast(int $outletId, int $forecastDays = 7): array
    {
        $outlet = Outlet::findOrFail($outletId);
        $startDate = Carbon::now()->subDays(28)->startOfDay();

        // Ambil pemakaian bahan dari resto_ingredient_movements
        $movements = DB::table('resto_ingredient_movements')
            ->where('outlet_id', $outlet->id)
            ->where('created_at', '>=', $startDate)
            ->whereIn('reason', ['production', 'sale', 'waste'])
            ->selectRaw('ingredient_id, SUM(ABS(qty_base_unit)) as total_consumed')
            ->groupBy('ingredient_id')
            ->get()
            ->keyBy('ingredient_id');

        $ingredients = Ingredient::where('is_active', true)->get();
        $forecast = [];

        foreach ($ingredients as $ing) {
            $currentStock = $this->inventoryService->availableIngredient($ing->id, $outlet->id);
            $stockFloat = (float) $currentStock;

            $consumed28Days = isset($movements[$ing->id]) ? (float) $movements[$ing->id]->total_consumed : 0.0;
            $dailyBurnRate = $consumed28Days / 28.0;
            $forecastNeeded = $dailyBurnRate * $forecastDays;
            $recommendedOrder = max(0.0, $forecastNeeded - $stockFloat);

            $forecast[] = [
                'ingredient' => $ing,
                'current_stock' => $currentStock,
                'consumed_last_28_days' => round($consumed28Days, 2),
                'daily_burn_rate' => round($dailyBurnRate, 2),
                'forecast_7_days' => round($forecastNeeded, 2),
                'recommended_order' => round($recommendedOrder, 2),
            ];
        }

        // Urutkan bahan baku yang paling butuh diorder
        usort($forecast, fn ($a, $b) => $b['recommended_order'] <=> $a['recommended_order']);

        return $forecast;
    }
}

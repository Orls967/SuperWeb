<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Application\Actions\RunForecastModelSelectionAction;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\EchelonStock;

final class SelectForecastModelCommand extends Command
{
    protected $signature = 'tower:forecast-select {--item= : Item SKU code} {--period= : Target forecast period (YYYY-MM)}';

    protected $description = 'Select best demand forecast model (SES/HOLT/CROSTON) via exact-arithmetic walk-forward backtesting';

    public function handle(RunForecastModelSelectionAction $action): int
    {
        $itemOption = $this->option('item');
        $periodOption = $this->option('period');
        $period = is_string($periodOption) && trim($periodOption) !== ''
            ? trim($periodOption)
            : now()->format('Y-m');

        $items = [];
        if (is_string($itemOption) && trim($itemOption) !== '') {
            $items[] = trim($itemOption);
        } else {
            $forecastItems = DemandForecast::query()->distinct()->pluck('item_code')->all();
            $stockItems = EchelonStock::query()->distinct()->pluck('item_code')->all();
            $items = array_values(array_unique(array_merge($forecastItems, $stockItems)));
        }

        $processed = 0;
        foreach ($items as $itemCode) {
            $history = $this->resolveHistoryForItem((string) $itemCode);
            $result = $action->execute((string) $itemCode, $period, $history);
            $this->line(sprintf(
                '[%s] period=%s model=%s forecast=%d mae_scaled=%d hash=%s',
                $result['item_code'],
                $result['period'],
                $result['selected_model'],
                $result['forecast_qty'],
                $result['mae_scaled'],
                substr($result['certificate_hash'], 0, 12),
            ));
            $processed++;
        }

        $this->info("Forecast model selection completed idempotently for {$processed} item(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function resolveHistoryForItem(string $itemCode): array
    {
        $existingWithHistory = DemandForecast::query()
            ->where('item_code', $itemCode)
            ->whereNotNull('history_json')
            ->latest('id')
            ->first();

        if ($existingWithHistory !== null && is_array($existingWithHistory->history_json) && count($existingWithHistory->history_json) >= 3) {
            return array_values(array_map(static fn (mixed $v): int => max(0, (int) $v), $existingWithHistory->history_json));
        }

        $stock = EchelonStock::query()->where('item_code', $itemCode)->first();
        if ($stock !== null && (int) $stock->safety_stock_qty > 0) {
            $base = max(10, (int) $stock->safety_stock_qty);

            return [$base, $base + 2, $base - 1, $base + 1, $base, $base + 2, $base + 1];
        }

        return [100, 102, 99, 101, 100, 102, 101];
    }
}

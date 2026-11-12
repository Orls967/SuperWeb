<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\GenerateSeasonalCashForecastAction;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\CashForecast;
use Modules\Treasury\Http\Requests\GenerateCashForecastRequest;
use Psr\Clock\ClockInterface;

final class CashForecastController extends Controller
{
    public function __construct(
        protected readonly GenerateSeasonalCashForecastAction $forecastAction,
        protected readonly ?ClockInterface $clock = null
    ) {}

    public function index(Request $request): JsonResponse
    {
        $scenario = (string) $request->query('scenario', 'base');

        $forecasts = CashForecast::query()
            ->where('scenario', $scenario)
            ->orderBy('week_number')
            ->limit(52)
            ->get();

        return response()->json([
            'status' => 'success',
            'scenario' => $scenario,
            'forecasts' => $forecasts,
        ]);
    }

    public function generate(GenerateCashForecastRequest $request): JsonResponse
    {
        $openingBalance = (int) BankAccount::where('currency', $request->getCurrency())->sum('balance');
        if ($openingBalance === 0) {
            $openingBalance = 500_000_000;
        }

        $historicalInflows = [
            140_000_000, 160_000_000, 150_000_000, 170_000_000,
            145_000_000, 165_000_000, 155_000_000, 175_000_000,
        ];

        $result = $this->forecastAction->execute(
            openingBalanceIdr: $openingBalance,
            startDate: $request->getStartDate(),
            historicalInflows: $historicalInflows,
            scenario: 'base',
            horizonWeeks: $request->getHorizonWeeks()
        );

        return response()->json([
            'status' => 'success',
            'forecast' => $result,
        ], 201);
    }
}

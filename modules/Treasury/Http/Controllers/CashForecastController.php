<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ClockInterface;
use Modules\Treasury\Application\Actions\Forecast13WeekCashAction;
use Modules\Treasury\Domain\Models\CashForecast;
use Modules\Treasury\Http\Requests\GenerateCashForecastRequest;

final class CashForecastController extends Controller
{
    public function __construct(
        protected readonly Forecast13WeekCashAction $forecastAction,
        protected readonly ClockInterface $clock
    ) {}

    public function index(Request $request): JsonResponse
    {
        $currency = $request->query('currency', 'IDR');

        $forecasts = CashForecast::query()
            ->where('currency', strtoupper((string) $currency))
            ->orderByDesc('forecast_date')
            ->limit(52)
            ->get();

        return response()->json([
            'status' => 'success',
            'currency' => $currency,
            'forecasts' => $forecasts,
        ]);
    }

    public function generate(GenerateCashForecastRequest $request): JsonResponse
    {
        $result = $this->forecastAction->execute(
            currency: $request->getCurrency(),
            startDate: $request->getStartDate(),
            seasonPeriod: $request->getSeasonPeriod(),
            horizonWeeks: $request->getHorizonWeeks()
        );

        return response()->json([
            'status' => 'success',
            'forecast' => $result,
        ], 201);
    }
}

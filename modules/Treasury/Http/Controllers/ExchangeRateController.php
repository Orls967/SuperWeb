<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Http\Requests\StoreExchangeRateRequest;
use Psr\Clock\ClockInterface;

final class ExchangeRateController extends Controller
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null,
        protected readonly ?TreasuryService $treasuryService = null
    ) {}

    public function index(Request $request): JsonResponse
    {
        $currency = $request->query('currency');
        $rateType = $request->query('rate_type');

        $query = ExchangeRate::query();
        if ($currency) {
            $query->where('from_currency', strtoupper((string) $currency));
        }
        if ($rateType) {
            $query->where('rate_type', strtolower((string) $rateType));
        }

        $rates = $query->orderByDesc('rate_date')->limit(100)->get();

        return response()->json([
            'status' => 'success',
            'count' => $rates->count(),
            'rates' => $rates,
        ]);
    }

    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $service = $this->treasuryService ?? app(TreasuryService::class);
        $service->registerCurrency($request->getCurrency(), $request->getCurrency());
        $service->registerCurrency('IDR', 'Indonesian Rupiah', 'Rp', 0);

        $rate = $service->recordExchangeRate(
            from: $request->getCurrency(),
            to: 'IDR',
            rateDate: $request->getRateDate(),
            rateNumerator: $request->getNumerator(),
            rateDenominator: $request->getDenominator(),
            rateType: strtolower($request->getRateType()),
            source: $request->getSource()
        );

        return response()->json([
            'status' => 'created',
            'rate' => $rate,
        ], 201);
    }

    public function show(ExchangeRate $rate): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'rate' => $rate,
        ]);
    }
}

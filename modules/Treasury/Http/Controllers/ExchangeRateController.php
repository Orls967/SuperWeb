<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\ClockInterface;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Http\Requests\StoreExchangeRateRequest;

final class ExchangeRateController extends Controller
{
    public function __construct(
        protected readonly ClockInterface $clock
    ) {}

    public function index(Request $request): JsonResponse
    {
        $currency = $request->query('currency');
        $rateType = $request->query('rate_type', 'SPOT');

        $query = ExchangeRate::query()->where('is_active', true);
        if ($currency) {
            $query->where('currency', strtoupper((string) $currency));
        }
        if ($rateType) {
            $query->where('rate_type', $rateType);
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
        $rate = ExchangeRate::create([
            'currency' => $request->getCurrency(),
            'rate_date' => $request->getRateDate(),
            'rate_numerator' => $request->getNumerator(),
            'rate_denominator' => $request->getDenominator(),
            'rate_type' => $request->getRateType(),
            'source' => $request->getSource(),
            'is_active' => true,
        ]);

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
